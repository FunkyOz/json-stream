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
});
