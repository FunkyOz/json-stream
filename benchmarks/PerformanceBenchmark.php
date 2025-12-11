<?php

declare(strict_types=1);

namespace Benchmarks;

use JsonStream\Exception\IOException;
use JsonStream\Reader\StreamReader;

/**
 * Performance benchmark suite for JsonStream
 *
 * Compares JsonStream performance against native json_decode
 * and measures memory usage at different file sizes and buffer configurations.
 */
class PerformanceBenchmark
{
    private string $currentMemoryLimit;

    private const BUFFER_SIZES = [
        '8KB' => 8 * 1024,
        '16KB' => 16 * 1024,
        '32KB' => 32 * 1024,
        '64KB' => 64 * 1024,
    ];

    public function __construct()
    {
        // CAVEAT: Convert memory_usage to MB
        $currentMemory = memory_get_usage() / (1024 ** 2);
        $currentMemoryLimit = ini_get('memory_limit');
        if (false !== $currentMemoryLimit) {
            $this->currentMemoryLimit = $currentMemoryLimit;
        } else {
            $this->currentMemoryLimit = '128M';
        }
        if ($currentMemory < 256) {
            ini_set('memory_limit', '256M');
        }
    }

    private static function printTitle(string $title): void
    {
        echo str_repeat('=', 80)."\n";
        echo $title."\n";
        echo str_repeat('=', 80)."\n\n";
    }

    private static function printBenchmarkTitle(string $title): void
    {
        echo $title."\n";
        echo str_repeat('-', 80)."\n";
    }

    private static function printResultTable(array $results): void
    {
        $header = [''];
        $data = [];
        foreach ($results as $key => $result) {
            $row = [$key];
            foreach ($result as $column => $value) {
                $header[] = $column;
                $row[] = $value;
            }
            $data[] = $row;
        }
        array_unshift($data, array_unique($header));
        $maxTitleLen = 0;
        $maxTimeLen = 0;
        $maxMemoryLen = 0;
        foreach ($data as [$title, $time, $memory]) {
            $maxTitleLen = max(strlen($title), $maxTitleLen);
            $maxTimeLen = max(strlen($time), $maxTimeLen);
            $maxMemoryLen = max(strlen($memory), $maxMemoryLen);
        }

        $totalRows = count($data);
        foreach ($data as $i => [$title, $time, $memory]) {
            $titlePad = str_repeat(' ', $maxTitleLen - strlen($title));
            $timePad = str_repeat(' ', $maxTimeLen - strlen($time));
            $memoryPad = str_repeat(' ', $maxMemoryLen - strlen($memory));
            $output = sprintf('│ %s │ %s │ %s │', $title.$titlePad, $timePad.$time, $memoryPad.$memory);
            $divider = str_repeat('─', strlen($output) - 10);
            if ($i === 0) {
                echo '╭'.$divider."╮\n";
            }
            echo $output."\n";
            if ($i === 0) {
                echo '│'.$divider."│\n";
            } elseif ($i === $totalRows - 1) {
                echo '╰'.$divider."╯\n";
            }
        }
    }

    public function run(): void
    {
        self::printTitle('JsonStream Performance Benchmark Suite');

        self::printBenchmarkTitle('READING BENCHMARKS');
        $this->runReadingBenchmarks();

        self::printBenchmarkTitle('BUFFER SIZE COMPARISON');
        $this->runBufferSizeBenchmarks();

        self::printBenchmarkTitle('MEMORY USAGE BENCHMARKS');
        $this->runMemoryBenchmarks();

        self::printBenchmarkTitle('JSONPATH BENCHMARKS');
        $this->runJsonPathBenchmarks();

        self::printTitle('Benchmark complete!');
    }

    private function runReadingBenchmarks(): void
    {
        $testFile = self::generateTestFile('1MB', 1000);

        echo "\n1. Reading 1MB JSON array (1,000 objects)\n";
        echo "   File: {$testFile}\n\n";

        $resultClassic = $this->benchmarkJsonDecode($testFile);

        $resultJsonStream = $this->benchmarkStreamReader($testFile);

        self::printResultTable([
            'Classic (json_decode)' => [
                'Time' => self::formatTime($resultClassic['time']),
                'Memory' => self::formatBytes($resultClassic['memory']),
            ],
            'JsonStream' => [
                'Time' => self::formatTime($resultJsonStream['time']),
                'Memory' => self::formatBytes($resultJsonStream['memory']),
            ],
        ]);

        @unlink($testFile);

        echo "\n\n";
    }

    private function runBufferSizeBenchmarks(): void
    {
        $testFile = self::generateTestFile('1MB', 1000);

        echo "\n1. Reading with different buffer sizes (1MB file)\n\n";

        foreach (self::BUFFER_SIZES as $name => $size) {
            $resultJsonStream = $this->benchmarkStreamReader($testFile, $size);

            self::printResultTable([
                'JsonStream' => [
                    'Time' => self::formatTime($resultJsonStream['time']),
                    'Memory' => self::formatBytes($resultJsonStream['memory']),
                ],
            ]);
        }

        @unlink($testFile);

        echo "\n\n";
    }

    private function runMemoryBenchmarks(): void
    {
        echo "\n1. Constant memory usage verification\n\n";

        foreach (['500KB' => 500, '1MB' => 1000, '2MB' => 2000] as $label => $count) {
            $testFile = self::generateTestFile($label, $count);
            $fileSize = filesize($testFile);

            $startTime = microtime(true);
            $memBefore = memory_get_usage(true);
            $reader = StreamReader::fromFile($testFile);

            $items = [];
            foreach ($reader->readArray() as $item) {
                $items[] = $item;
            }

            $memAfter = memory_get_usage(true);
            $memUsed = $memAfter - $memBefore;
            $timeUsed = microtime(true) - $startTime;

            printf("   File size: %s\n", self::formatBytes($fileSize));

            // TODO: Make another printer
            self::printResultTable([
                'JsonStream' => [
                    'Time' => self::formatTime($timeUsed),
                    'Memory' => self::formatBytes($memUsed),
                ],
            ]);

            echo "\n";

            @unlink($testFile);
        }

        echo "\n\n";
    }

    private function runJsonPathBenchmarks(): void
    {
        $testFile = __DIR__.'/data/data-689.json';

        if (! file_exists($testFile)) {
            echo "\n   Note: data-689.json not found, using generated test file\n";
            $testFile = self::generateNestedTestFile('1MB', 1000);
        }

        $fileSize = filesize($testFile);
        echo "\n1. JSONPath filtering vs manual filtering\n";
        echo '   File size: '.(self::formatBytes($fileSize))."\n\n";

        $resultClassic = $this->benchmarkManualFiltering($testFile);

        $resultJsonStream = $this->benchmarkJsonPath($testFile);

        self::printResultTable([
            'Classic (json_decode)' => [
                'Time' => self::formatTime($resultClassic['time']),
                'Memory' => self::formatBytes($resultClassic['memory']),
            ],
            'JsonStream' => [
                'Time' => self::formatTime($resultJsonStream['time']),
                'Memory' => self::formatBytes($resultJsonStream['memory']),
            ],
        ]);

        echo "\n\n";
    }

    /**
     * Benchmark native json_decode
     *
     * @return array{time: float, memory: int}
     */
    private function benchmarkJsonDecode(string $file): array
    {
        gc_collect_cycles();
        $timeStart = microtime(true);
        $memStart = memory_get_usage(true);

        $json = file_get_contents($file);
        $data = json_decode($json, true);

        $memEnd = memory_get_usage(true);
        $timeEnd = microtime(true);

        return [
            'data' => $data,
            'time' => $timeEnd - $timeStart,
            'memory' => $memEnd - $memStart,
        ];
    }

    /**
     * Benchmark StreamReader
     *
     * @return array{time: float, memory: int}
     *
     * @throws IOException
     */
    private function benchmarkStreamReader(string $file, int $bufferSize = 8192): array
    {
        gc_collect_cycles();
        $memStart = memory_get_usage(true);
        $timeStart = microtime(true);

        $reader = StreamReader::fromFile($file, ['bufferSize' => $bufferSize]);
        $data = [];
        foreach ($reader->readArray() as $item) {
            $data[] = $item;
        }

        $timeEnd = microtime(true);
        $memEnd = memory_get_usage(true);

        return [
            'data' => $data,
            'time' => $timeEnd - $timeStart,
            'memory' => $memEnd - $memStart,
        ];
    }

    /**
     * Benchmark manual filtering (load all then filter)
     *
     * @return array{time: float, memory: int}
     */
    private function benchmarkManualFiltering(string $file): array
    {
        gc_collect_cycles();
        $memStart = memory_get_usage(true);
        $timeStart = microtime(true);

        $json = file_get_contents($file);
        $data = json_decode($json, true);

        // Simulate filtering - assuming data structure with 'Ads' array
        $count = 0;
        $vids = [];
        if (isset($data['Ads']) && is_array($data['Ads'])) {
            foreach ($data['Ads'] as $item) {
                $count++;
                // Access a property like in example.php
                if (isset($item['Vid'])) {
                    $vids[] = $item['Vid'];
                }
            }
        }

        $timeEnd = microtime(true);
        $memEnd = memory_get_usage(true);

        return [
            'data' => $vids,
            'time' => $timeEnd - $timeStart,
            'memory' => $memEnd - $memStart,
        ];
    }

    /**
     * Benchmark JSONPath streaming (similar to example.php)
     *
     * @return array{time: float, memory: int}
     *
     * @throws IOException
     */
    private function benchmarkJsonPath(string $file): array
    {
        gc_collect_cycles();
        $memStart = memory_get_usage(true);
        $timeStart = microtime(true);

        $reader = StreamReader::fromFile($file)
            ->withPath('$.Ads[*]');

        $data = [];
        foreach ($reader->readItems() as $key => $value) {
            $data[] = $value;
        }

        $timeEnd = microtime(true);
        $memEnd = memory_get_usage(true);

        return [
            'data' => $data,
            'time' => $timeEnd - $timeStart,
            'memory' => $memEnd - $memStart,
        ];
    }

    private static function generateTestFile(string $label, int $objectCount = 1000): string
    {
        $file = sys_get_temp_dir()."/jsonstream_bench_{$label}.json";

        if (file_exists($file)) {
            return $file;
        }

        echo '   Generating test file: '.$file."...\n";

        $handle = fopen($file, 'w');
        fwrite($handle, '[');

        for ($i = 0; $i < $objectCount; $i++) {
            if ($i > 0) {
                fwrite($handle, ',');
            }

            $object = [
                'id' => $i,
                'name' => 'User '.$i,
                'email' => "user{$i}@example.com",
                'age' => rand(18, 80),
                'active' => (bool) rand(0, 1),
                'balance' => rand(0, 100000) / 100,
                'tags' => ['tag1', 'tag2', 'tag3'],
                'metadata' => [
                    'created' => '2025-01-01T00:00:00Z',
                    'updated' => '2025-12-01T00:00:00Z',
                    'version' => 1,
                ],
            ];

            fwrite($handle, json_encode($object));

            if ($i % 100 === 0) {
                echo "   Generated {$i}/{$objectCount} objects...\r";
            }
        }

        fwrite($handle, ']');
        fclose($handle);

        echo "   Generated {$objectCount}/{$objectCount} objects... Done!\n";

        return $file;
    }

    /**
     * Generate test file with nested structure for JSONPath testing
     */
    private static function generateNestedTestFile(string $label, int $objectCount = 1000): string
    {
        $file = sys_get_temp_dir()."/jsonstream_bench_nested_{$label}.json";

        if (file_exists($file)) {
            return $file;
        }

        echo "   Generating nested test file: {$file}...\n";

        $handle = fopen($file, 'w');
        fwrite($handle, '{"Ads":[');

        for ($i = 0; $i < $objectCount; $i++) {
            if ($i > 0) {
                fwrite($handle, ',');
            }

            $ad = [
                'Vid' => "video_{$i}",
                'id' => $i,
                'title' => 'Ad Title '.$i,
                'duration' => rand(15, 120),
                'impressions' => rand(1000, 1000000),
                'clicks' => rand(10, 10000),
                'metadata' => [
                    'created' => '2025-01-01T00:00:00Z',
                    'updated' => '2025-12-01T00:00:00Z',
                    'version' => 1,
                ],
            ];

            fwrite($handle, json_encode($ad));

            if ($i % 100 === 0) {
                echo "   Generated {$i}/{$objectCount} objects...\r";
            }
        }

        fwrite($handle, ']}');
        fclose($handle);

        echo "   Generated {$objectCount}/{$objectCount} objects... Done!\n";

        return $file;
    }

    /**
     * Format bytes into human-readable format
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return sprintf('%6.2f %s', $bytes, $units[$i]);
    }

    private static function formatTime(float $time): string
    {
        $units = ['s', 'ms', 'mms'];
        $i = 0;
        while ($time < 1 && $i < count($units) - 1) {
            $time *= 1000;
            $i++;
        }

        return sprintf('%6.2f %s', $time, $units[$i]);
    }

    public function __destruct()
    {
        ini_set('memory_limit', $this->currentMemoryLimit);
    }
}
