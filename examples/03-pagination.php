<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use JsonStream\Exception\IOException;
use JsonStream\Reader\StreamReader;

/**
 * Example 3: Pagination with Skip/Limit
 *
 * This example demonstrates how to implement pagination efficiently
 * using the skip() and limit() methods.
 */
echo "=== Example 3: Pagination with Skip/Limit ===\n\n";

// Example data file
$dataFile = __DIR__.'/data/users.json';

// Check if data file exists
if (! file_exists($dataFile)) {
    echo "Error: Sample data file not found at {$dataFile}\n";
    echo "Run the data generation script first.\n";
    exit(1);
}

try {
    // Pagination settings
    $pageSize = 10;
    $totalPages = 5;

    echo "Demonstrating pagination with page size: {$pageSize}\n\n";

    for ($page = 1; $page <= $totalPages; $page++) {
        $offset = ($page - 1) * $pageSize;

        echo "--- Page {$page} (offset: {$offset}, limit: {$pageSize}) ---\n";

        $reader = StreamReader::fromFile($dataFile);
        $users = $reader->readArray()->skip($offset)->limit($pageSize);

        $count = 0;
        foreach ($users as $user) {
            $count++;
            echo sprintf(
                "  %d. User #%d: %s\n",
                $offset + $count,
                $user['id'],
                $user['name']
            );
        }

        if ($count === 0) {
            echo "  (No more results)\n";
            break;
        }

        $reader->close();
        echo "\n";
    }

    // Example: Get specific page with JSONPath
    echo "--- Using JSONPath for pagination ($.users[20:30]) ---\n";
    $reader = StreamReader::fromFile($dataFile, [
        'jsonPath' => '$[20:30]',
    ]);

    $count = 0;
    foreach ($reader->readArray() as $user) {
        $count++;
        echo sprintf("  %d. User #%d: %s\n", 20 + $count, $user['id'], $user['name']);
    }
    $reader->close();

} catch (IOException $e) {
    echo "IO Error: {$e->getMessage()}\n";
    exit(1);
}

echo "\n✓ Example completed successfully!\n";
