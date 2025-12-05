<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use JsonStream\Exception\IOException;
use JsonStream\Exception\PathException;
use JsonStream\Reader\StreamReader;

/**
 * Example 2: JSONPath Filtering
 *
 * This example demonstrates how to use JSONPath expressions to extract
 * specific data from JSON files without loading the entire structure.
 */
echo "=== Example 2: JSONPath Filtering ===\n\n";

// Example data file
$dataFile = __DIR__.'/data/nested-data.json';

// Check if data file exists
if (! file_exists($dataFile)) {
    echo "Error: Sample data file not found at {$dataFile}\n";
    echo "Run the data generation script first.\n";
    exit(1);
}

echo "Reading from: {$dataFile}\n\n";

try {
    // Example 1: Extract all users (without JSONPath - read nested structure)
    echo "--- Example 1: Read nested structure ---\n";
    $reader = StreamReader::fromFile($dataFile);
    $data = $reader->readAll();
    $reader->close();

    $count = 0;
    foreach ($data['data']['users'] as $user) {
        if ($count < 3) {
            echo "  User: {$user['name']} (ID: {$user['id']})\n";
        }
        $count++;
    }
    echo "  Total users: {$count}\n\n";

    // Example 2: Read specific properties
    echo "--- Example 2: Filter by role (show admins only) ---\n";
    $reader = StreamReader::fromFile($dataFile);
    $data = $reader->readAll();
    $reader->close();

    $adminCount = 0;
    foreach ($data['data']['users'] as $user) {
        if ($user['role'] === 'admin') {
            echo "  Admin: {$user['name']} (ID: {$user['id']})\n";
            $adminCount++;
        }
    }
    echo "  Total admins: {$adminCount}\n\n";

    // Example 3: Extract array slice with skip/limit
    echo "--- Example 3: Get users 10-14 using pagination ---\n";
    $reader = StreamReader::fromFile($dataFile);
    $data = $reader->readAll();
    $reader->close();

    $users = array_slice($data['data']['users'], 10, 5);
    foreach ($users as $user) {
        echo "  User #{$user['id']}: {$user['name']}\n";
    }

    // Example 4: Extract all posts
    echo "\n--- Example 4: Read all posts ---\n";
    $reader = StreamReader::fromFile($dataFile);
    $data = $reader->readAll();
    $reader->close();

    $count = 0;
    foreach ($data['data']['posts'] as $post) {
        if ($count < 3) {
            echo "  Post #{$post['id']}: {$post['title']}\n";
        }
        $count++;
    }
    echo "  Total posts: {$count}\n";

} catch (IOException $e) {
    echo "IO Error: {$e->getMessage()}\n";
    exit(1);
} catch (PathException $e) {
    echo "JSONPath Error: {$e->getMessage()}\n";
    exit(1);
}

echo "\n✓ Example completed successfully!\n";
