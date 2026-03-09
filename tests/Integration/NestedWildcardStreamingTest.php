<?php

declare(strict_types=1);

use JsonStream\JsonStream;

describe('Nested Wildcard Streaming', function (): void {
    it('streams $.users[*].posts[*] pattern', function (): void {
        $json = json_encode([
            'users' => [
                ['posts' => [1, 2, 3]],
                ['posts' => [4, 5]],
                ['posts' => [6]],
            ],
        ]);

        $results = JsonStream::read($json)->withPath('$.users[*].posts[*]')->readItems()->toArray();

        expect($results)->toBe([1, 2, 3, 4, 5, 6]);
    });

    it('streams $.a[*].b[*] pattern', function (): void {
        $json = json_encode([
            'a' => [
                ['b' => [1, 2]],
                ['b' => [3]],
            ],
        ]);

        $results = JsonStream::read($json)->withPath('$.a[*].b[*]')->readItems()->toArray();

        expect($results)->toBe([1, 2, 3]);
    });

    it('streams $.matrix[*][*] pattern for 2D arrays', function (): void {
        $json = json_encode([
            'matrix' => [
                [1, 2, 3],
                [4, 5, 6],
                [7, 8, 9],
            ],
        ]);

        $results = JsonStream::read($json)->withPath('$.matrix[*][*]')->readItems()->toArray();

        expect($results)->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    });

    it('streams 3-level nested wildcards $.data[*].items[*].tags[*]', function (): void {
        $json = json_encode([
            'data' => [
                ['items' => [
                    ['tags' => ['a', 'b']],
                    ['tags' => ['c']],
                ]],
                ['items' => [
                    ['tags' => ['d', 'e', 'f']],
                ]],
            ],
        ]);

        $results = JsonStream::read($json)->withPath('$.data[*].items[*].tags[*]')->readItems()->toArray();

        expect($results)->toBe(['a', 'b', 'c', 'd', 'e', 'f']);
    });

    it('streams nested wildcards with trailing property $.users[*].posts[*].title', function (): void {
        $json = json_encode([
            'users' => [
                ['posts' => [
                    ['title' => 'Post 1', 'body' => 'Body 1'],
                    ['title' => 'Post 2', 'body' => 'Body 2'],
                ]],
                ['posts' => [
                    ['title' => 'Post 3', 'body' => 'Body 3'],
                ]],
            ],
        ]);

        $results = JsonStream::read($json)->withPath('$.users[*].posts[*].title')->readItems()->toArray();

        expect($results)->toBe(['Post 1', 'Post 2', 'Post 3']);
    });

    it('streams nested wildcards with deep trailing path $.users[*].posts[*].author.name', function (): void {
        $json = json_encode([
            'users' => [
                ['posts' => [
                    ['author' => ['name' => 'Alice']],
                    ['author' => ['name' => 'Bob']],
                ]],
                ['posts' => [
                    ['author' => ['name' => 'Charlie']],
                ]],
            ],
        ]);

        $results = JsonStream::read($json)->withPath('$.users[*].posts[*].author.name')->readItems()->toArray();

        expect($results)->toBe(['Alice', 'Bob', 'Charlie']);
    });

    it('handles empty nested arrays', function (): void {
        $json = json_encode([
            'users' => [
                ['posts' => []],
                ['posts' => [1, 2]],
                ['posts' => []],
            ],
        ]);

        $results = JsonStream::read($json)->withPath('$.users[*].posts[*]')->readItems()->toArray();

        expect($results)->toBe([1, 2]);
    });

    it('handles missing nested property gracefully', function (): void {
        $json = json_encode([
            'users' => [
                ['posts' => [1, 2]],
                ['name' => 'Alice'],  // No 'posts' key
                ['posts' => [3]],
            ],
        ]);

        $results = JsonStream::read($json)->withPath('$.users[*].posts[*]')->readItems()->toArray();

        expect($results)->toBe([1, 2, 3]);
    });

    it('handles mixed types in outer array', function (): void {
        $json = json_encode([
            'data' => [
                ['items' => [1, 2]],
                'string_value',
                null,
                42,
                ['items' => [3]],
            ],
        ]);

        $results = JsonStream::read($json)->withPath('$.data[*].items[*]')->readItems()->toArray();

        expect($results)->toBe([1, 2, 3]);
    });

    it('handles nested objects as wildcard results', function (): void {
        $json = json_encode([
            'categories' => [
                ['products' => [
                    ['name' => 'A', 'price' => 10],
                    ['name' => 'B', 'price' => 20],
                ]],
                ['products' => [
                    ['name' => 'C', 'price' => 30],
                ]],
            ],
        ]);

        $results = JsonStream::read($json)->withPath('$.categories[*].products[*]')->readItems()->toArray();

        expect($results)->toBe([
            ['name' => 'A', 'price' => 10],
            ['name' => 'B', 'price' => 20],
            ['name' => 'C', 'price' => 30],
        ]);
    });

    it('handles completely empty outer array', function (): void {
        $json = json_encode(['users' => []]);

        $results = JsonStream::read($json)->withPath('$.users[*].posts[*]')->readItems()->toArray();

        expect($results)->toBe([]);
    });

    it('handles single element nesting', function (): void {
        $json = json_encode([
            'users' => [
                ['posts' => [42]],
            ],
        ]);

        $results = JsonStream::read($json)->withPath('$.users[*].posts[*]')->readItems()->toArray();

        expect($results)->toBe([42]);
    });

    it('handles 4-level deep nesting $.a[*].b[*].c[*].d[*]', function (): void {
        $json = json_encode([
            'a' => [
                ['b' => [
                    ['c' => [
                        ['d' => [1, 2]],
                    ]],
                ]],
                ['b' => [
                    ['c' => [
                        ['d' => [3]],
                        ['d' => [4, 5]],
                    ]],
                ]],
            ],
        ]);

        $results = JsonStream::read($json)->withPath('$.a[*].b[*].c[*].d[*]')->readItems()->toArray();

        expect($results)->toBe([1, 2, 3, 4, 5]);
    });

    it('streams nested wildcards with large data set', function (): void {
        // Build a structure with many nested elements
        $users = [];
        for ($i = 0; $i < 20; $i++) {
            $users[] = ['posts' => range($i * 5, $i * 5 + 4)];
        }
        $json = json_encode(['users' => $users]);

        $results = JsonStream::read($json)
            ->withPath('$.users[*].posts[*]')
            ->readItems()
            ->toArray();

        // Should have 20 * 5 = 100 results
        expect(count($results))->toBe(100);
        expect($results[0])->toBe(0);
        expect($results[99])->toBe(99);
    });

    it('produces same results as PathFilter for nested wildcards', function (): void {
        // Build a moderately complex nested structure
        $data = ['groups' => []];
        for ($g = 0; $g < 5; $g++) {
            $group = ['members' => []];
            for ($m = 0; $m < 3; $m++) {
                $group['members'][] = ['scores' => range($g * 10 + $m * 3, $g * 10 + $m * 3 + 2)];
            }
            $data['groups'][] = $group;
        }

        $json = json_encode($data);

        // Get results via streaming (nested wildcard)
        $streamResults = JsonStream::read($json)
            ->withPath('$.groups[*].members[*].scores[*]')
            ->readItems()
            ->toArray();

        // Get expected results by manual extraction
        $expected = [];
        foreach ($data['groups'] as $group) {
            foreach ($group['members'] as $member) {
                foreach ($member['scores'] as $score) {
                    $expected[] = $score;
                }
            }
        }

        expect($streamResults)->toBe($expected);
    });

    it('uses streaming not PathFilter for nested wildcards', function (): void {
        $json = json_encode([
            'users' => [
                ['posts' => [1, 2]],
                ['posts' => [3]],
            ],
        ]);

        $reader = JsonStream::read($json)->withPath('$.users[*].posts[*]');

        // Verify the pattern is detected as streamable
        expect($reader->getPathExpression()?->canUseSimpleStreaming())->toBeTrue();

        // Verify results are correct
        $results = $reader->readItems()->toArray();
        expect($results)->toBe([1, 2, 3]);
    });
});
