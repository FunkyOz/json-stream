<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use JsonStream\Exception\IOException;
use JsonStream\Exception\JsonStreamException;
use JsonStream\Exception\ParseException;
use JsonStream\Exception\PathException;
use JsonStream\Reader\StreamReader;

/**
 * Example 6: Error Handling
 *
 * This example demonstrates proper error handling for various
 * failure scenarios when working with JsonStream.
 */
echo "=== Example 6: Error Handling ===\n\n";

// Example 1: File not found
echo "--- Example 1: Handling File Not Found ---\n";
try {
    $reader = StreamReader::fromFile('nonexistent-file.json');
    echo "This should not be reached\n";
} catch (IOException $e) {
    echo "✓ Caught IOException: {$e->getMessage()}\n\n";
}

// Example 2: Invalid JSON syntax
echo "--- Example 2: Handling Parse Errors ---\n";
$invalidJson = '{"name": "John", "age": 30,}';  // Trailing comma is invalid
try {
    $reader = StreamReader::fromString($invalidJson);
    $data = $reader->readAll();
    echo "This should not be reached\n";
} catch (ParseException $e) {
    echo "✓ Caught ParseException: {$e->getMessage()}\n";
    echo "  Error at line: {$e->getJsonLine()}, column: {$e->getJsonColumn()}\n\n";
}

// Example 3: Invalid JSONPath expression
echo "--- Example 3: Handling Invalid JSONPath ---\n";
try {
    $reader = StreamReader::fromString('{"users": []}', [
        'jsonPath' => '$.invalid[*[*]',  // Invalid syntax
    ]);
    echo "This should not be reached\n";
} catch (PathException $e) {
    echo "✓ Caught PathException: {$e->getMessage()}\n\n";
}

// Example 4: Proper cleanup with try-finally
echo "--- Example 4: Proper Resource Cleanup ---\n";
$tempFile = __DIR__.'/data/temp-test.json';

$reader = null;
try {
    $reader = StreamReader::fromFile($tempFile);

    foreach ($reader->readArray() as $item) {
        echo "  Processing item: {$item['id']}\n";

        if ($item['id'] === 2) {
            throw new \Exception('Simulated processing error');
        }
    }

    echo "  All items processed successfully\n";

} catch (\Exception $e) {
    echo "✗ Processing error: {$e->getMessage()}\n";

} finally {
    // Always close the reader, even if an error occurred
    if ($reader !== null) {
        $reader->close();
        echo "✓ Reader closed properly\n";
    }
}

// Example 5: Generic exception handling
echo "\n--- Example 5: Generic JsonStream Exception Handling ---\n";
try {
    // This could throw any JsonStream exception
    $reader = StreamReader::fromFile('test.json');
    $data = $reader->readAll();
    $reader->close();

} catch (JsonStreamException $e) {
    // Catches IOException, ParseException, PathException, etc.
    echo '✓ Caught JsonStreamException: '.get_class($e)."\n";
    echo "  Message: {$e->getMessage()}\n";

} catch (\Throwable $e) {
    // Catch any other unexpected errors
    echo "Unexpected error: {$e->getMessage()}\n";
}

echo "\n✓ Example completed successfully!\n";
