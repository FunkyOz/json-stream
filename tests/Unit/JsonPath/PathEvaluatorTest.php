<?php

use JsonStream\Internal\JsonPath\PathEvaluator;
use JsonStream\Internal\JsonPath\PathParser;

describe('PathEvaluator', function (): void {
    it('matches root path at depth 0', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$');
        $evaluator = new PathEvaluator($expression);

        expect($evaluator->matches())->toBeTrue();
    });

    it('matches simple property path', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('users', []);
        expect($evaluator->matches())->toBeTrue();
    });

    it('does not match wrong property', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('posts', []);
        expect($evaluator->matches())->toBeFalse();
    });

    it('matches nested property path', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.store.book');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('store', []);
        expect($evaluator->matches())->toBeFalse();

        $evaluator->enterLevel('book', []);
        expect($evaluator->matches())->toBeTrue();
    });

    it('matches array index', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[0]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('users', []);
        $evaluator->enterLevel(0, ['name' => 'Alice']);
        expect($evaluator->matches())->toBeTrue();
    });

    it('does not match wrong array index', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[0]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('users', []);
        $evaluator->enterLevel(1, ['name' => 'Bob']);
        expect($evaluator->matches())->toBeFalse();
    });

    it('matches wildcard', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[*]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('users', []);
        $evaluator->enterLevel(0, ['name' => 'Alice']);
        expect($evaluator->matches())->toBeTrue();

        $evaluator->exitLevel();
        $evaluator->enterLevel(1, ['name' => 'Bob']);
        expect($evaluator->matches())->toBeTrue();
    });

    it('matches array slice', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[0:3]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('users', []);

        $evaluator->enterLevel(0, ['name' => 'Alice']);
        expect($evaluator->matches())->toBeTrue();
        $evaluator->exitLevel();

        $evaluator->enterLevel(2, ['name' => 'Charlie']);
        expect($evaluator->matches())->toBeTrue();
        $evaluator->exitLevel();

        $evaluator->enterLevel(3, ['name' => 'Dave']);
        expect($evaluator->matches())->toBeFalse();
    });

    it('gets current path string', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.store.book');
        $evaluator = new PathEvaluator($expression);

        expect($evaluator->getCurrentPath())->toBe('$');

        $evaluator->enterLevel('store', []);
        expect($evaluator->getCurrentPath())->toBe('$.store');

        $evaluator->enterLevel('book', []);
        expect($evaluator->getCurrentPath())->toBe('$.store.book');
    });

    it('can reset state', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('users', []);
        expect($evaluator->getDepth())->toBe(1);

        $evaluator->reset();
        expect($evaluator->getDepth())->toBe(0);
    });

    // Tests for matchesStructure() method
    it('matchesStructure returns true for root-only path at depth 0', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$');
        $evaluator = new PathEvaluator($expression);

        expect($evaluator->matchesStructure())->toBeTrue();
    });

    it('matchesStructure returns false for root-only path at depth > 0', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('users', []);
        expect($evaluator->matchesStructure())->toBeFalse();
    });

    it('matchesStructure works with simple property path', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('users', []);
        expect($evaluator->matchesStructure())->toBeTrue();
    });

    it('matchesStructure works with filter segment on array index', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[?(@.price > 10)]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('items', []);
        $evaluator->enterLevel(0, ['price' => 15]);
        expect($evaluator->matchesStructure())->toBeTrue();
    });

    it('matchesStructure returns false for filter segment on non-integer key', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[?(@.price > 10)]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('items', []);
        $evaluator->enterLevel('foo', ['price' => 15]);
        expect($evaluator->matchesStructure())->toBeFalse();
    });

    it('matchesStructure returns true for recursive descent segments', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$..name');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('store', []);
        $evaluator->enterLevel('items', []);
        // Recursive segments should match at any depth
        expect($evaluator->matchesStructure())->toBeTrue();
    });

    it('matchesStructure handles non-matching segment', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users.name');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('posts', []); // Wrong property
        expect($evaluator->matchesStructure())->toBeFalse();
    });

    it('matchesStructure handles path deeper than segments', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('users', []);
        $evaluator->enterLevel('name', 'Alice'); // Deeper than expression
        expect($evaluator->matchesStructure())->toBeFalse();
    });

    // Tests for needsValueForMatch() method
    it('needsValueForMatch returns false at depth 0', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[?(@.price > 10)]');
        $evaluator = new PathEvaluator($expression);

        expect($evaluator->needsValueForMatch())->toBeFalse();
    });

    it('needsValueForMatch returns false for root-only path', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$');
        $evaluator = new PathEvaluator($expression);

        expect($evaluator->needsValueForMatch())->toBeFalse();
    });

    it('needsValueForMatch returns true for filter segments', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[?(@.price > 10)]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('items', []);
        $evaluator->enterLevel(0, ['price' => 15]);
        expect($evaluator->needsValueForMatch())->toBeTrue();
    });

    it('needsValueForMatch returns false for non-filter segments', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[0]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('items', []);
        $evaluator->enterLevel(0, ['price' => 15]);
        expect($evaluator->needsValueForMatch())->toBeFalse();
    });

    it('needsValueForMatch returns false when depth exceeds segment count', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('items', []);
        $evaluator->enterLevel(0, ['name' => 'Item 1']);
        $evaluator->enterLevel('name', 'Item 1'); // Beyond expression depth
        expect($evaluator->needsValueForMatch())->toBeFalse();
    });

    // Tests for getCurrentPath() with integer keys
    it('getCurrentPath handles integer keys correctly', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[0]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('items', []);
        expect($evaluator->getCurrentPath())->toBe('$.items');

        $evaluator->enterLevel(0, ['name' => 'Item 1']);
        expect($evaluator->getCurrentPath())->toBe('$.items[0]');
    });

    it('getCurrentPath handles mixed property and array keys', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.store.items[0].name');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('store', []);
        $evaluator->enterLevel('items', []);
        $evaluator->enterLevel(0, ['name' => 'Item 1']);
        expect($evaluator->getCurrentPath())->toBe('$.store.items[0]');
    });

    // Tests for getCurrentValue() method
    it('getCurrentValue returns null when value stack is empty', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$');
        $evaluator = new PathEvaluator($expression);

        expect($evaluator->getCurrentValue())->toBeNull();
    });

    it('getCurrentValue returns current value at top of stack', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users');
        $evaluator = new PathEvaluator($expression);

        $testValue = ['name' => 'Alice'];
        $evaluator->enterLevel('users', $testValue);
        expect($evaluator->getCurrentValue())->toBe($testValue);
    });

    it('getCurrentValue returns most recent value after multiple levels', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.store.items');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('store', ['items' => []]);
        $testValue = ['name' => 'Item 1'];
        $evaluator->enterLevel('items', $testValue);
        expect($evaluator->getCurrentValue())->toBe($testValue);
    });

    // Test for getExpression() method
    it('getExpression returns the PathExpression', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users');
        $evaluator = new PathEvaluator($expression);

        expect($evaluator->getExpression())->toBe($expression);
    });

    // Tests for recursive descent matching
    it('matches() handles recursive descent at matching position', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$..name');
        $evaluator = new PathEvaluator($expression);

        // Navigate to a 'name' property at different depths
        $evaluator->enterLevel('store', []);
        $evaluator->enterLevel('items', []);
        $evaluator->enterLevel(0, ['name' => 'Item 1']);
        $evaluator->enterLevel('name', 'Item 1');

        expect($evaluator->matches())->toBeTrue();
    });

    it('matches() handles recursive descent by skipping levels', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$..name');
        $evaluator = new PathEvaluator($expression);

        // Navigate deep and find 'name' at the end
        $evaluator->enterLevel('store', []);
        $evaluator->enterLevel('name', 'Store Name');

        expect($evaluator->matches())->toBeTrue();
    });

    it('matchesStructure with no stack but segments remaining returns false', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users.name.first');
        $evaluator = new PathEvaluator($expression);

        // Only navigate to 'users', not deep enough
        $evaluator->enterLevel('users', []);

        // matchesStructure should return false since we need to go deeper
        expect($evaluator->matchesStructure())->toBeFalse();
    });

    // Tests for canTerminateEarly() and hasReachedTerminationPoint()
    it('canTerminateEarly returns false for expressions without early termination', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[*]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('items', []);
        $evaluator->enterLevel(0, ['name' => 'Item 1']);

        expect($evaluator->canTerminateEarly())->toBeFalse();
    });

    it('canTerminateEarly returns false before reaching termination point', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[5]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('items', []);
        $evaluator->enterLevel(3, ['name' => 'Item 4']); // Index 3, not yet at 5

        expect($evaluator->canTerminateEarly())->toBeFalse();
    });

    it('canTerminateEarly returns true after reaching termination point', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[5]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('items', []);
        $evaluator->enterLevel(6, ['name' => 'Item 7']); // Index 6, termination index is 5+1=6

        expect($evaluator->canTerminateEarly())->toBeTrue();
    });

    it('canTerminateEarly returns true after exceeding termination point', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[5]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('items', []);
        $evaluator->enterLevel(10, ['name' => 'Item 11']); // Index 10, exceeded termination point

        expect($evaluator->canTerminateEarly())->toBeTrue();
    });

    it('canTerminateEarly returns false at depth 0', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[5]');
        $evaluator = new PathEvaluator($expression);

        // No navigation, depth is 0
        expect($evaluator->canTerminateEarly())->toBeFalse();
    });

    it('canTerminateEarly returns false for non-integer key', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[5]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('items', []);
        $evaluator->enterLevel('foo', ['name' => 'Item']); // String key, not integer

        expect($evaluator->canTerminateEarly())->toBeFalse();
    });

    it('canTerminateEarly handles slice with bounded end', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[0:5]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('items', []);
        $evaluator->enterLevel(10, ['name' => 'Item 11']); // Beyond slice end

        expect($evaluator->canTerminateEarly())->toBeTrue();
    });

    // Tests for getAllRemainingSegments()
    it('getAllRemainingSegments returns all segments after current depth including wildcards', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[*].posts[*]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('users', null);
        $evaluator->enterLevel(0, null);

        $remaining = $evaluator->getAllRemainingSegments();

        // At depth 2 (users, 0), segments are [Root, Property(users), Wildcard, Property(posts), Wildcard]
        // After depth 2 (segment index 2 = Wildcard), remaining should be [Property(posts), Wildcard]
        expect(count($remaining))->toBe(2);
    });

    it('getAllRemainingSegments returns empty when at end of path', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[*]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('items', null);
        $evaluator->enterLevel(0, null);

        $remaining = $evaluator->getAllRemainingSegments();

        expect($remaining)->toBe([]);
    });

    // Tests for hasNestedWildcardsRemaining()
    it('hasNestedWildcardsRemaining returns true for nested wildcard patterns', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[*].posts[*]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('users', null);
        $evaluator->enterLevel(0, null);

        expect($evaluator->hasNestedWildcardsRemaining())->toBeTrue();
    });

    it('hasNestedWildcardsRemaining returns false for single wildcard patterns', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[*].name');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('users', null);
        $evaluator->enterLevel(0, null);

        expect($evaluator->hasNestedWildcardsRemaining())->toBeFalse();
    });

    it('hasNestedWildcardsRemaining returns false at end of path', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[*]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('items', null);
        $evaluator->enterLevel(0, null);

        expect($evaluator->hasNestedWildcardsRemaining())->toBeFalse();
    });

    // Tests for walkValueWithWildcards()
    it('walkValueWithWildcards expands single wildcard', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[*].posts[*]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('users', null);
        $evaluator->enterLevel(0, null);

        $value = ['posts' => [1, 2, 3]];
        $remaining = $evaluator->getAllRemainingSegments();
        $results = $evaluator->walkValueWithWildcards($value, $remaining);

        expect($results)->toBe([1, 2, 3]);
    });

    it('walkValueWithWildcards handles missing property', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[*].posts[*]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('users', null);
        $evaluator->enterLevel(0, null);

        $value = ['name' => 'Alice']; // No 'posts' key
        $remaining = $evaluator->getAllRemainingSegments();
        $results = $evaluator->walkValueWithWildcards($value, $remaining);

        expect($results)->toBe([]);
    });

    it('walkValueWithWildcards handles non-array value at wildcard', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[*].posts[*]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('users', null);
        $evaluator->enterLevel(0, null);

        $value = ['posts' => 'not an array'];
        $remaining = $evaluator->getAllRemainingSegments();
        $results = $evaluator->walkValueWithWildcards($value, $remaining);

        expect($results)->toBe([]);
    });

    it('walkValueWithWildcards handles non-array at property segment', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[*].posts[*]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('users', null);
        $evaluator->enterLevel(0, null);

        $remaining = $evaluator->getAllRemainingSegments();
        $results = $evaluator->walkValueWithWildcards('string value', $remaining);

        expect($results)->toBe([]);
    });

    it('walkValueWithWildcards handles deeply nested wildcards', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.a[*].b[*].c[*]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('a', null);
        $evaluator->enterLevel(0, null);

        $value = ['b' => [
            ['c' => [1, 2]],
            ['c' => [3]],
        ]];
        $remaining = $evaluator->getAllRemainingSegments();
        $results = $evaluator->walkValueWithWildcards($value, $remaining);

        expect($results)->toBe([1, 2, 3]);
    });

    it('walkValueWithWildcards handles empty segments', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[*]');
        $evaluator = new PathEvaluator($expression);

        $results = $evaluator->walkValueWithWildcards('hello', []);

        expect($results)->toBe(['hello']);
    });

    it('walkValueWithWildcards handles array index segments', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[*].posts[0]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('users', null);
        $evaluator->enterLevel(0, null);

        $value = ['posts' => ['first', 'second', 'third']];
        $remaining = $evaluator->getAllRemainingSegments();
        $results = $evaluator->walkValueWithWildcards($value, $remaining);

        expect($results)->toBe(['first']);
    });

    it('walkValueWithWildcards handles negative array index', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.data[*].items[-1]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('data', null);
        $evaluator->enterLevel(0, null);

        $value = ['items' => ['a', 'b', 'c']];
        $remaining = $evaluator->getAllRemainingSegments();
        $results = $evaluator->walkValueWithWildcards($value, $remaining);

        expect($results)->toBe(['c']);
    });

    it('walkValueWithWildcards returns empty for out-of-bounds index', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.data[*].items[99]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('data', null);
        $evaluator->enterLevel(0, null);

        $value = ['items' => ['a', 'b']];
        $remaining = $evaluator->getAllRemainingSegments();
        $results = $evaluator->walkValueWithWildcards($value, $remaining);

        expect($results)->toBe([]);
    });

    it('walkValueWithWildcards returns empty for non-list array at index segment', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.data[*].items[0]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('data', null);
        $evaluator->enterLevel(0, null);

        $value = ['items' => ['key' => 'value']]; // Associative, not a list
        $remaining = $evaluator->getAllRemainingSegments();
        $results = $evaluator->walkValueWithWildcards($value, $remaining);

        expect($results)->toBe([]);
    });

    // Tests for shouldExtractFromValue with nested wildcards
    it('shouldExtractFromValue returns true for nested wildcard patterns', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[*].posts[*]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('users', null);
        $evaluator->enterLevel(0, null);

        expect($evaluator->shouldExtractFromValue())->toBeTrue();
    });

    it('shouldExtractFromValue returns true for $.matrix[*][*] at wildcard position', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.matrix[*][*]');
        $evaluator = new PathEvaluator($expression);

        $evaluator->enterLevel('matrix', null);
        $evaluator->enterLevel(0, null);

        expect($evaluator->shouldExtractFromValue())->toBeTrue();
    });
});
