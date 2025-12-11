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
});
