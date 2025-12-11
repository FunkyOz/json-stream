<?php

use JsonStream\Internal\JsonPath\FilterSegment;

describe('FilterSegment', function (): void {
    it('matches comparison filter with greater than', function (): void {
        $filter = new FilterSegment('@.price > 10');

        expect($filter->matches(0, ['price' => 15, 'name' => 'Book'], 1))->toBeTrue();
        expect($filter->matches(0, ['price' => 5, 'name' => 'Book'], 1))->toBeFalse();
    });

    it('matches comparison filter with less than', function (): void {
        $filter = new FilterSegment('@.age < 18');

        expect($filter->matches(0, ['age' => 16, 'name' => 'Alice'], 1))->toBeTrue();
        expect($filter->matches(0, ['age' => 21, 'name' => 'Bob'], 1))->toBeFalse();
    });

    it('matches equality filter', function (): void {
        $filter = new FilterSegment('@.status == "active"');

        expect($filter->matches(0, ['status' => 'active', 'id' => 1], 1))->toBeTrue();
        expect($filter->matches(0, ['status' => 'inactive', 'id' => 2], 1))->toBeFalse();
    });

    it('matches inequality filter', function (): void {
        $filter = new FilterSegment('@.type != "admin"');

        expect($filter->matches(0, ['type' => 'user', 'id' => 1], 1))->toBeTrue();
        expect($filter->matches(0, ['type' => 'admin', 'id' => 2], 1))->toBeFalse();
    });

    it('matches filter with numbers', function (): void {
        $filter = new FilterSegment('@.quantity >= 100');

        expect($filter->matches(0, ['quantity' => 150], 1))->toBeTrue();
        expect($filter->matches(0, ['quantity' => 100], 1))->toBeTrue();
        expect($filter->matches(0, ['quantity' => 50], 1))->toBeFalse();
    });

    it('matches filter with boolean', function (): void {
        $filter = new FilterSegment('@.active == true');

        expect($filter->matches(0, ['active' => true], 1))->toBeTrue();
        expect($filter->matches(0, ['active' => false], 1))->toBeFalse();
    });

    it('does not match when property missing', function (): void {
        $filter = new FilterSegment('@.price > 10');

        expect($filter->matches(0, ['name' => 'Book'], 1))->toBeFalse();
    });

    it('does not match when value is not array', function (): void {
        $filter = new FilterSegment('@.price > 10');

        expect($filter->matches(0, 'string value', 1))->toBeFalse();
        expect($filter->matches(0, 123, 1))->toBeFalse();
    });

    it('matches existence check', function (): void {
        $filter = new FilterSegment('@.email');

        expect($filter->matches(0, ['email' => 'test@example.com', 'name' => 'Alice'], 1))->toBeTrue();
        expect($filter->matches(0, ['name' => 'Bob'], 1))->toBeFalse();
    });

    it('handles invalid filter expression', function (): void {
        $filter = new FilterSegment('invalid expression without @ sign');

        // Should return false for invalid expressions (line 72)
        expect($filter->matches(0, ['price' => 10], 1))->toBeFalse();
    });

    it('matches filter with false boolean value', function (): void {
        $filter = new FilterSegment('@.disabled == false');

        // Tests line 141: 'false' boolean parsing
        expect($filter->matches(0, ['disabled' => false], 1))->toBeTrue();
        expect($filter->matches(0, ['disabled' => true], 1))->toBeFalse();
    });

    it('handles filter with unparseable value', function (): void {
        $filter = new FilterSegment('@.tag == someUnquotedString');

        // Tests line 149: default return in parseValue for non-standard values
        expect($filter->matches(0, ['tag' => 'someUnquotedString'], 1))->toBeTrue();
    });

    it('matches filter with strict equality operator', function (): void {
        $filter = new FilterSegment('@.value === 10');

        // Tests line 163: strict equality operator
        expect($filter->matches(0, ['value' => 10], 1))->toBeTrue();
        expect($filter->matches(0, ['value' => '10'], 1))->toBeFalse(); // String '10' !== int 10
    });

    it('matches filter with strict inequality operator', function (): void {
        $filter = new FilterSegment('@.value !== 10');

        // Tests line 165: strict inequality operator
        expect($filter->matches(0, ['value' => '10'], 1))->toBeTrue(); // String '10' !== int 10
        expect($filter->matches(0, ['value' => 10], 1))->toBeFalse();
    });

    it('handles filter with null value comparison', function (): void {
        $filter = new FilterSegment('@.value == null');

        expect($filter->matches(0, ['value' => null], 1))->toBeTrue();
        expect($filter->matches(0, ['value' => 'not null'], 1))->toBeFalse();
    });

    it('handles filter with <= operator', function (): void {
        $filter = new FilterSegment('@.score <= 50');

        expect($filter->matches(0, ['score' => 30], 1))->toBeTrue();
        expect($filter->matches(0, ['score' => 50], 1))->toBeTrue();
        expect($filter->matches(0, ['score' => 60], 1))->toBeFalse();
    });

    it('isRecursive returns false for filter segments', function (): void {
        $filter = new FilterSegment('@.price > 10');

        // Filter segments are not recursive (line 31)
        expect($filter->isRecursive())->toBeFalse();
    });

    it('getExpression returns the filter expression', function (): void {
        $expression = '@.age >= 18';
        $filter = new FilterSegment($expression);

        // Tests line 36: getExpression()
        expect($filter->getExpression())->toBe($expression);
    });
});
