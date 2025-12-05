<?php

use JsonStream\Reader\StreamReader;

describe('JSONPath Filtering Behavior', function (): void {
    it('filters array elements by wildcard', function (): void {
        $json = '{"users": [{"name": "Alice"}, {"name": "Bob"}, {"name": "Charlie"}]}';

        $reader = StreamReader::fromString($json)
            ->withPath('$.users[*].name');

        $names = [];
        foreach ($reader->readItems() as $item) {
            $names[] = $item;
        }

        // Should only get the names, not the full objects
        expect($names)->toBe(['Alice', 'Bob', 'Charlie']);
    });

    it('filters with array index', function (): void {
        $json = '{"users": [{"name": "Alice"}, {"name": "Bob"}, {"name": "Charlie"}]}';

        $reader = StreamReader::fromString($json)
            ->withPath('$.users[0]');

        $result = $reader->readAll();

        // Should only get the first user
        expect($result)->toBe(['name' => 'Alice']);
    });

    it('filters with property path', function (): void {
        $json = '{"store": {"name": "MyStore", "location": "NYC"}}';

        $reader = StreamReader::fromString($json)
            ->withPath('$.store.name');

        $result = $reader->readAll();

        // Should only get the name property
        expect($result)->toBe('MyStore');
    });

    it('filters with filter expression', function (): void {
        $json = '{"products": [
            {"name": "Book", "price": 10},
            {"name": "Laptop", "price": 1000},
            {"name": "Pen", "price": 2}
        ]}';

        $reader = StreamReader::fromString($json)
            ->withPath('$.products[?(@.price < 100)]');

        $products = [];
        foreach ($reader->readItems() as $product) {
            $products[] = $product;
        }

        // Should only get products with price < 100
        expect(count($products))->toBe(2);
        expect($products[0]['name'])->toBe('Book');
        expect($products[1]['name'])->toBe('Pen');
    });

    it('filters with recursive descent', function (): void {
        $json = '{
            "store": {
                "book": [
                    {"title": "Book 1", "author": {"name": "Alice"}},
                    {"title": "Book 2", "author": {"name": "Bob"}}
                ],
                "owner": {"name": "Charlie"}
            }
        }';

        $reader = StreamReader::fromString($json)
            ->withPath('$..name');

        $names = [];
        foreach ($reader->readItems() as $name) {
            $names[] = $name;
        }

        // Should get all name properties at any depth
        expect($names)->toBe(['Alice', 'Bob', 'Charlie']);
    });

    it('filters nested property access', function (): void {
        $json = '{"user": {"profile": {"email": "test@example.com"}}}';

        $reader = StreamReader::fromString($json)
            ->withPath('$.user.profile.email');

        $result = $reader->readAll();

        expect($result)->toBe('test@example.com');
    });

    it('filters with array slice', function (): void {
        $json = '{"items": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]}';

        $reader = StreamReader::fromString($json)
            ->withPath('$.items[2:5]');

        $items = [];
        foreach ($reader->readItems() as $item) {
            $items[] = $item;
        }

        // Should get items at index 2, 3, 4
        expect($items)->toBe([3, 4, 5]);
    });

    it('returns empty when no matches found', function (): void {
        $json = '{"users": [{"name": "Alice", "age": 25}]}';

        $reader = StreamReader::fromString($json)
            ->withPath('$.users[?(@.age > 30)]');

        $items = [];
        foreach ($reader->readItems() as $item) {
            $items[] = $item;
        }

        expect($items)->toBe([]);
    });

    it('works with complex nested structures', function (): void {
        $json = '{
            "store": {
                "books": [
                    {
                        "title": "Book 1",
                        "authors": [
                            {"name": "Alice", "country": "US"},
                            {"name": "Bob", "country": "UK"}
                        ]
                    },
                    {
                        "title": "Book 2",
                        "authors": [
                            {"name": "Charlie", "country": "US"}
                        ]
                    }
                ]
            }
        }';

        $reader = StreamReader::fromString($json)
            ->withPath('$.store.books[*].authors[*].name');

        $names = [];
        foreach ($reader->readItems() as $name) {
            $names[] = $name;
        }

        expect($names)->toBe(['Alice', 'Bob', 'Charlie']);
    });
});
