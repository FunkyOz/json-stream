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
});
