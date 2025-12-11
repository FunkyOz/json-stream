<?php

use JsonStream\Internal\JsonPath\PathEvaluator;
use JsonStream\Internal\JsonPath\PathFilter;
use JsonStream\Internal\JsonPath\PathParser;

describe('PathFilter', function (): void {
    it('extracts root value when path matches root', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$');
        $evaluator = new PathEvaluator($expression);
        $filter = new PathFilter($evaluator);

        $data = ['name' => 'Alice'];
        $results = $filter->extract($data);

        expect($results)->toHaveCount(1);
        expect($results[0])->toBe($data);
    });

    it('extracts matching properties from object', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.name');
        $evaluator = new PathEvaluator($expression);
        $filter = new PathFilter($evaluator);

        $data = ['name' => 'Alice', 'age' => 30];
        $results = $filter->extract($data);

        expect($results)->toHaveCount(1);
        expect($results[0])->toBe('Alice');
    });

    it('extracts matching array elements', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[0]');
        $evaluator = new PathEvaluator($expression);
        $filter = new PathFilter($evaluator);

        $data = ['users' => [['name' => 'Alice'], ['name' => 'Bob']]];
        $results = $filter->extract($data);

        expect($results)->toHaveCount(1);
        expect($results[0])->toBe(['name' => 'Alice']);
    });

    it('extracts all matching elements with wildcard', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[*]');
        $evaluator = new PathEvaluator($expression);
        $filter = new PathFilter($evaluator);

        $data = ['users' => [['name' => 'Alice'], ['name' => 'Bob']]];
        $results = $filter->extract($data);

        expect($results)->toHaveCount(2);
        expect($results[0])->toBe(['name' => 'Alice']);
        expect($results[1])->toBe(['name' => 'Bob']);
    });

    it('handles empty array correctly', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.items[*]');
        $evaluator = new PathEvaluator($expression);
        $filter = new PathFilter($evaluator);

        $data = ['items' => []]; // Empty array triggers line 89
        $results = $filter->extract($data);

        expect($results)->toBeArray();
    });

    it('handles nested empty arrays', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.store.items[*]');
        $evaluator = new PathEvaluator($expression);
        $filter = new PathFilter($evaluator);

        $data = [
            'store' => [
                'items' => [], // Empty array
                'name' => 'My Store',
            ],
        ];
        $results = $filter->extract($data);

        expect($results)->toBeArray();
    });

    it('extracts nested properties', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.store.book.title');
        $evaluator = new PathEvaluator($expression);
        $filter = new PathFilter($evaluator);

        $data = [
            'store' => [
                'book' => [
                    'title' => 'Test Book',
                    'author' => 'Test Author',
                ],
            ],
        ];
        $results = $filter->extract($data);

        expect($results)->toHaveCount(1);
        expect($results[0])->toBe('Test Book');
    });

    it('returns empty results when no matches', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.nonexistent');
        $evaluator = new PathEvaluator($expression);
        $filter = new PathFilter($evaluator);

        $data = ['name' => 'Alice'];
        $results = $filter->extract($data);

        expect($results)->toBeEmpty();
    });

    it('handles recursive descent', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$..name');
        $evaluator = new PathEvaluator($expression);
        $filter = new PathFilter($evaluator);

        $data = [
            'name' => 'Root',
            'child' => [
                'name' => 'Child',
            ],
        ];
        $results = $filter->extract($data);

        expect($results)->toContain('Root');
        expect($results)->toContain('Child');
    });

    it('handles mixed associative and indexed arrays', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.data');
        $evaluator = new PathEvaluator($expression);
        $filter = new PathFilter($evaluator);

        $data = [
            'data' => [
                'name' => 'Test', // Associative part
                ['item1'], // Indexed part
            ],
        ];
        $results = $filter->extract($data);

        expect($results)->toHaveCount(1);
    });

    it('handles scalar values at leaf nodes', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.value');
        $evaluator = new PathEvaluator($expression);
        $filter = new PathFilter($evaluator);

        $data = ['value' => 'scalar string'];
        $results = $filter->extract($data);

        expect($results)->toHaveCount(1);
        expect($results[0])->toBe('scalar string');
    });

    it('extracts from pure indexed array', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$[1]');
        $evaluator = new PathEvaluator($expression);
        $filter = new PathFilter($evaluator);

        $data = ['first', 'second', 'third'];
        $results = $filter->extract($data);

        expect($results)->toHaveCount(1);
        expect($results[0])->toBe('second');
    });

    it('handles empty root array', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$[*]');
        $evaluator = new PathEvaluator($expression);
        $filter = new PathFilter($evaluator);

        $data = []; // Empty root array
        $results = $filter->extract($data);

        expect($results)->toBeArray();
    });
});
