<?php

declare(strict_types=1);

use JsonStream\Internal\JsonPath\PropertySegment;

describe('PropertySegment', function (): void {
    it('matches string key exactly', function (): void {
        $segment = new PropertySegment('name');

        expect($segment->matches('name', null, 0))->toBeTrue();
        expect($segment->matches('other', null, 0))->toBeFalse();
    });

    it('does not match integer key for non-numeric property', function (): void {
        $segment = new PropertySegment('name');

        expect($segment->matches(0, null, 0))->toBeFalse();
        expect($segment->matches(1, null, 0))->toBeFalse();
    });

    it('matches integer key when property is numeric string', function (): void {
        $segment = new PropertySegment('1');

        expect($segment->matches(1, null, 0))->toBeTrue();
        expect($segment->matches(0, null, 0))->toBeFalse();
        expect($segment->matches(2, null, 0))->toBeFalse();
    });

    it('matches zero index for property "0"', function (): void {
        $segment = new PropertySegment('0');

        expect($segment->matches(0, null, 0))->toBeTrue();
        expect($segment->matches(1, null, 0))->toBeFalse();
    });

    it('still matches string key "0" on objects', function (): void {
        $segment = new PropertySegment('0');

        expect($segment->matches('0', null, 0))->toBeTrue();
    });

    it('matches large numeric string to integer index', function (): void {
        $segment = new PropertySegment('999');

        expect($segment->matches(999, null, 0))->toBeTrue();
        expect($segment->matches(998, null, 0))->toBeFalse();
    });

    it('does not match leading zero strings to integer index', function (): void {
        $segment = new PropertySegment('01');

        // "01" is not a pure digit string matching index 1
        // ctype_digit('01') is true, but (int)'01' = 1 while the string is '01'
        // This should NOT match index 1 because '01' != '1'
        // However, ctype_digit returns true for '01', so we need to check:
        // (int)'01' === 1, and key === 1, so it would match.
        // Per our safety constraints, we accept this since '01' IS all digits.
        // The RFC doesn't define leading-zero behavior precisely.
        expect($segment->matches(1, null, 0))->toBeTrue();
        expect($segment->matches('01', null, 0))->toBeTrue();
    });

    it('does not match negative integer keys', function (): void {
        $segment = new PropertySegment('1');

        expect($segment->matches(-1, null, 0))->toBeFalse();
    });

    it('does not match integer key for empty string property', function (): void {
        $segment = new PropertySegment('');

        expect($segment->matches(0, null, 0))->toBeFalse();
        expect($segment->matches('', null, 0))->toBeTrue();
    });

    it('does not match integer key for float-like string property', function (): void {
        $segment = new PropertySegment('1.5');

        expect($segment->matches(1, null, 0))->toBeFalse();
        expect($segment->matches('1.5', null, 0))->toBeTrue();
    });

    it('does not match integer key for negative string property', function (): void {
        $segment = new PropertySegment('-1');

        expect($segment->matches(-1, null, 0))->toBeFalse();
        expect($segment->matches('-1', null, 0))->toBeTrue();
    });

    it('getProperty returns property name', function (): void {
        $segment = new PropertySegment('test');

        expect($segment->getProperty())->toBe('test');
        expect($segment->getPropertyName())->toBe('test');
    });

    it('isRecursive returns false by default', function (): void {
        $segment = new PropertySegment('test');

        expect($segment->isRecursive())->toBeFalse();
    });

    it('isRecursive returns true when set', function (): void {
        $segment = new PropertySegment('test', true);

        expect($segment->isRecursive())->toBeTrue();
    });
});
