<?php

declare(strict_types=1);

use JsonStream\Reader\StreamReader;

function createLargeArrayFile(int $count): string
{
    $file = tempnam(sys_get_temp_dir(), 'json_stream_large_');

    $data = [];
    for ($i = 0; $i < $count; $i++) {
        $data[] = [
            'id' => $i,
            'name' => "User $i",
            'email' => "user{$i}@example.com",
            'active' => true,
            'score' => $i * 1.5,
            'metadata' => [
                'created' => '2024-01-01T00:00:00Z',
                'updated' => '2024-12-01T00:00:00Z',
            ],
        ];
    }

    file_put_contents($file, json_encode($data));

    return $file;
}

/**
 * Integration tests for large array handling
 *
 * These tests verify that the parser correctly handles arrays
 * with thousands of elements, especially across buffer boundaries.
 *
 * Bug fix: Parser was failing when reading arrays > 1000 elements
 * due to incorrect position calculation in BufferManager::peek()
 * after buffer refill.
 */
describe('Large Array Support', function (): void {
    afterEach(function (): void {
        // Cleanup test files
        if (isset($this->testFile) && file_exists($this->testFile)) {
            unlink($this->testFile);
        }
    });

    it('handles arrays with 1,000 elements', function (): void {
        $count = 1000;
        $this->testFile = createLargeArrayFile($count);

        $reader = StreamReader::fromFile($this->testFile);
        $itemCount = 0;

        foreach ($reader->readArray() as $item) {
            expect($item)->toBeArray();
            expect($item['id'])->toBe($itemCount);
            $itemCount++;
        }

        $reader->close();

        expect($itemCount)->toBe($count);
    });

    it('handles arrays with 5,000 elements', function (): void {
        $count = 5000;
        $this->testFile = createLargeArrayFile($count);

        $reader = StreamReader::fromFile($this->testFile);
        $itemCount = 0;

        foreach ($reader->readArray() as $item) {
            expect($item)->toBeArray();
            // Spot check every 1000th item
            if ($itemCount % 1000 === 0) {
                expect($item['id'])->toBe($itemCount);
                expect($item['name'])->toBe("User $itemCount");
            }
            $itemCount++;
        }

        $reader->close();

        expect($itemCount)->toBe($count);
    });

    it('handles arrays with 10,000 elements', function (): void {
        $count = 10000;
        $this->testFile = createLargeArrayFile($count);

        $reader = StreamReader::fromFile($this->testFile);
        $itemCount = 0;

        foreach ($reader->readArray() as $item) {
            expect($item)->toBeArray();
            // Spot check every 2000th item
            if ($itemCount % 2000 === 0) {
                expect($item['id'])->toBe($itemCount);
                expect($item['email'])->toBe("user{$itemCount}@example.com");
            }
            $itemCount++;
        }

        $reader->close();

        expect($itemCount)->toBe($count);
    });

    it('handles round-trip with 1,000 elements', function (): void {
        $count = 1000;
        $this->testFile = tempnam(sys_get_temp_dir(), 'json_stream_roundtrip_');

        // Write
        $testData = [];
        for ($i = 0; $i < $count; $i++) {
            $testData[] = [
                'id' => $i,
                'value' => "test_$i",
                'nested' => ['key' => $i * 2],
            ];
        }

        file_put_contents($this->testFile, json_encode($testData));

        // Read and verify
        $reader = StreamReader::fromFile($this->testFile);
        $index = 0;

        foreach ($reader->readArray() as $item) {
            expect($item)->toBe($testData[$index]);
            $index++;
        }

        $reader->close();

        expect($index)->toBe($count);
    });

    it('works with different buffer sizes', function (): void {
        $count = 500;
        $this->testFile = createLargeArrayFile($count);

        // Test with various buffer sizes
        $bufferSizes = [1024, 4096, 8192, 16384, 32768];

        foreach ($bufferSizes as $bufferSize) {
            $reader = StreamReader::fromFile($this->testFile, ['bufferSize' => $bufferSize]);
            $itemCount = 0;

            foreach ($reader->readArray() as $item) {
                expect($item)->toBeArray();
                $itemCount++;
            }

            $reader->close();

            expect($itemCount)->toBe($count, "Failed with buffer size $bufferSize");
        }
    });

    it('maintains constant memory usage', function (): void {
        $count = 10000;
        $this->testFile = createLargeArrayFile($count);

        $memoryBefore = memory_get_usage();

        $reader = StreamReader::fromFile($this->testFile);
        $itemCount = 0;

        foreach ($reader->readArray() as $item) {
            // Don't accumulate items in memory
            $itemCount++;

            // Check memory every 1000 items
            if ($itemCount % 1000 === 0) {
                $currentMemory = memory_get_usage();
                $memoryIncrease = $currentMemory - $memoryBefore;

                // Memory increase should be minimal (< 1MB)
                // This proves streaming works correctly
                expect($memoryIncrease)->toBeLessThan(1024 * 1024);
            }
        }

        $reader->close();

        expect($itemCount)->toBe($count);
    });

    it('handles arrays at exact buffer boundary', function (): void {
        // Create JSON that ends exactly at 8192 bytes (default buffer size)
        $this->testFile = tempnam(sys_get_temp_dir(), 'json_stream_boundary_');

        // Each object is approximately 78 bytes when compact
        // Calculate to get close to 8192 bytes
        $targetSize = 8192;
        $itemSize = 78;
        $itemsNeeded = (int) ($targetSize / $itemSize);

        $data = [];
        for ($i = 0; $i < $itemsNeeded; $i++) {
            $data[] = [
                'id' => $i,
                'name' => 'U',
                'email' => 'u@e.com',
                'active' => true,
                'score' => 1.5,
            ];
        }

        file_put_contents($this->testFile, json_encode($data));

        // Verify file size is near buffer boundary (around 8KB)
        $fileSize = filesize($this->testFile);
        expect($fileSize)->toBeGreaterThan(6000);
        expect($fileSize)->toBeLessThan(10000);

        // Read and verify
        $reader = StreamReader::fromFile($this->testFile);
        $count = 0;

        foreach ($reader->readArray() as $item) {
            expect($item)->toBeArray();
            expect($item['id'])->toBe($count);
            $count++;
        }

        $reader->close();

        expect($count)->toBe($itemsNeeded);
    });

    it('validates JSON integrity with native decoder', function (): void {
        $count = 1000;
        $this->testFile = createLargeArrayFile($count);

        // Read with StreamReader
        $reader = StreamReader::fromFile($this->testFile);
        $streamItems = [];

        foreach ($reader->readArray() as $item) {
            $streamItems[] = $item;
        }

        $reader->close();

        // Read with native json_decode
        $nativeItems = json_decode(file_get_contents($this->testFile), true);

        // Both should produce identical results
        expect($streamItems)->toBe($nativeItems);
        expect(count($streamItems))->toBe($count);
    });
});
