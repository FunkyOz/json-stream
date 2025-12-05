<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use JsonStream\Exception\IOException;
use JsonStream\Reader\StreamReader;

/**
 * Example 1: Reading Large JSON Files
 *
 * This example demonstrates how to read large JSON files efficiently
 * without loading the entire file into memory.
 */
echo "=== Example 1: Reading Large JSON Files ===\n\n";

// Example data file
$dataFile = __DIR__.'/data/users.json';

// Check if data file exists
if (! file_exists($dataFile)) {
    echo "Error: Sample data file not found at {$dataFile}\n";
    echo "Run the data generation script first.\n";
    exit(1);
}

echo "Reading users from: {$dataFile}\n";
echo 'File size: '.number_format(filesize($dataFile))." bytes\n\n";

try {
    // Create a reader from the file
    $reader = StreamReader::fromFile($dataFile);

    echo 'Memory before reading: '.number_format(memory_get_usage())." bytes\n";

    $count = 0;
    $start = microtime(true);

    // Iterate through each user in the array
    foreach ($reader->readArray() as $user) {
        $count++;

        // Process each user (print first 5 as example)
        if ($count <= 5) {
            echo sprintf(
                "User #%d: %s <%s>\n",
                $user['id'],
                $user['name'],
                $user['email']
            );
        }

        // Show progress every 1000 users
        if ($count % 1000 === 0) {
            echo "Processed {$count} users...\n";
        }
    }

    $elapsed = microtime(true) - $start;
    $reader->close();

    echo "\nMemory after reading: ".number_format(memory_get_usage())." bytes\n";
    echo 'Memory peak: '.number_format(memory_get_peak_usage())." bytes\n";
    echo "\nTotal users processed: {$count}\n";
    echo 'Time elapsed: '.number_format($elapsed, 3)." seconds\n";
    echo 'Throughput: '.number_format($count / $elapsed, 0)." users/second\n";

} catch (IOException $e) {
    echo "IO Error: {$e->getMessage()}\n";
    exit(1);
}

echo "\n✓ Example completed successfully!\n";
