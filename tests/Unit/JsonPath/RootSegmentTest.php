<?php

use JsonStream\Internal\JsonPath\RootSegment;

describe('RootSegment', function (): void {
    it('matches at depth 0', function (): void {
        $segment = new RootSegment();

        // Root segment matches at depth 0 (line 16)
        expect($segment->matches('$', [], 0))->toBeTrue();
    });

    it('does not match at depth greater than 0', function (): void {
        $segment = new RootSegment();

        // Root segment does not match at nested depths
        expect($segment->matches('prop', 'value', 1))->toBeFalse();
        expect($segment->matches(0, 'value', 2))->toBeFalse();
        expect($segment->matches('nested', 'value', 5))->toBeFalse();
    });

    it('isRecursive returns false', function (): void {
        $segment = new RootSegment();

        expect($segment->isRecursive())->toBeFalse();
    });
});
