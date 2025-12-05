<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use JsonStream\Exception\IOException;
use JsonStream\Reader\StreamReader;

/**
 * Example 5: Working with Nested Structures
 *
 * This example demonstrates how to read complex nested
 * JSON structures with multiple levels of nesting.
 */
echo "=== Example 5: Working with Nested Structures ===\n\n";

$outputFile = __DIR__.'/data/nested-data.json';

echo "--- Reading Nested Structure ---\n";

try {
    $reader = StreamReader::fromFile($outputFile);
    $data = $reader->readAll();
    $reader->close();

    if (isset($data['data']['users']) && is_array($data['data']['users'])) {
        echo "Users in nested structure:\n";
        foreach (array_slice($data['data']['users'], 0, 5) as $user) {
            echo "  - {$user['name']} (ID: {$user['id']}, Email: {$user['email']})\n";
        }
        $remaining = count($data['data']['users']) - 5;
        if ($remaining > 0) {
            echo "  ... and {$remaining} more users\n";
        }
    }

    if (isset($data['data']['posts']) && is_array($data['data']['posts'])) {
        echo "\nPosts in nested structure:\n";
        foreach (array_slice($data['data']['posts'], 0, 3) as $post) {
            echo "  - {$post['title']} (by author {$post['author_id']})\n";
        }
        $remaining = count($data['data']['posts']) - 3;
        if ($remaining > 0) {
            echo "  ... and {$remaining} more posts\n";
        }
    }

} catch (IOException $e) {
    echo "IO Error: {$e->getMessage()}\n";
    exit(1);
}

echo "\n✓ Example completed successfully!\n";
