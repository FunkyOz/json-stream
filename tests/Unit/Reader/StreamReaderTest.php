<?php

declare(strict_types=1);

use JsonStream\Config;
use JsonStream\Exception\IOException;
use JsonStream\Reader\ArrayIterator;
use JsonStream\Reader\ItemIterator;
use JsonStream\Reader\ObjectIterator;
use JsonStream\Reader\StreamReader;

describe('StreamReader', function (): void {
    describe('factory methods', function (): void {
        it('creates from file path', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'json_test_');
            file_put_contents($tempFile, '{"test": "value"}');

            $reader = StreamReader::fromFile($tempFile);

            expect($reader)->toBeInstanceOf(StreamReader::class);

            unlink($tempFile);
        });

        it('throws on non-existent file', function (): void {
            expect(fn () => StreamReader::fromFile('/nonexistent/file.json'))
                ->toThrow(IOException::class, 'File not found');
        });

        it('creates from stream resource', function (): void {
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, '{"test": "value"}');
            rewind($stream);

            $reader = StreamReader::fromStream($stream);

            expect($reader)->toBeInstanceOf(StreamReader::class);

            fclose($stream);
        });

        it('throws on invalid stream resource', function (): void {
            expect(fn () => StreamReader::fromStream('not a resource'))
                ->toThrow(IOException::class, 'Invalid stream resource');
        });

        it('creates from string', function (): void {
            $reader = StreamReader::fromString('{"test": "value"}');

            expect($reader)->toBeInstanceOf(StreamReader::class);
        });

        it('accepts options in fromFile', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'json_test_');
            file_put_contents($tempFile, '{"test": "value"}');

            $reader = StreamReader::fromFile($tempFile, [
                'bufferSize' => 16384,
                'maxDepth' => 256,
            ]);

            $stats = $reader->getStats();
            expect($stats['bufferSize'])->toBe(16384);
            expect($stats['maxDepth'])->toBe(256);

            unlink($tempFile);
        });

        it('accepts options in fromStream', function (): void {
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, '{"test": "value"}');
            rewind($stream);

            $reader = StreamReader::fromStream($stream, [
                'bufferSize' => 4096,
                'maxDepth' => 128,
            ]);

            $stats = $reader->getStats();
            expect($stats['bufferSize'])->toBe(4096);
            expect($stats['maxDepth'])->toBe(128);

            fclose($stream);
        });

        it('accepts options in fromString', function (): void {
            $reader = StreamReader::fromString('{"test": "value"}', [
                'bufferSize' => 2048,
                'maxDepth' => 64,
            ]);

            $stats = $reader->getStats();
            expect($stats['bufferSize'])->toBe(2048);
            expect($stats['maxDepth'])->toBe(64);
        });
    });

    describe('configuration methods', function (): void {
        it('withPath returns new instance with path', function (): void {
            $reader = StreamReader::fromString('{"test": "value"}');
            $newReader = $reader->withPath('$.test');

            expect($newReader)->not->toBe($reader);
            expect($newReader)->toBeInstanceOf(StreamReader::class);
        });

        it('withBufferSize returns new instance', function (): void {
            $reader = StreamReader::fromString('{"test": "value"}');
            $newReader = $reader->withBufferSize(16384);

            expect($newReader)->not->toBe($reader);
            expect($newReader->getStats()['bufferSize'])->toBe(16384);
        });

        it('withMaxDepth returns new instance', function (): void {
            $reader = StreamReader::fromString('{"test": "value"}');
            $newReader = $reader->withMaxDepth(256);

            expect($newReader)->not->toBe($reader);
            expect($newReader->getStats()['maxDepth'])->toBe(256);
        });

        it('supports method chaining', function (): void {
            $reader = StreamReader::fromString('{"test": "value"}')
                ->withBufferSize(8192)
                ->withMaxDepth(128)
                ->withPath('$.test');

            expect($reader)->toBeInstanceOf(StreamReader::class);
            expect($reader->getStats()['bufferSize'])->toBe(8192);
            expect($reader->getStats()['maxDepth'])->toBe(128);
        });
    });

    describe('parsing methods', function (): void {
        it('readArray returns ArrayIterator', function (): void {
            $reader = StreamReader::fromString('[1, 2, 3]');

            $iterator = $reader->readArray();

            expect($iterator)->toBeInstanceOf(ArrayIterator::class);
        });

        it('readObject returns ObjectIterator', function (): void {
            $reader = StreamReader::fromString('{"a": 1, "b": 2}');

            $iterator = $reader->readObject();

            expect($iterator)->toBeInstanceOf(ObjectIterator::class);
        });

        it('readItems returns ItemIterator', function (): void {
            $reader = StreamReader::fromString('[1, 2, 3]');

            $iterator = $reader->readItems();

            expect($iterator)->toBeInstanceOf(ItemIterator::class);
        });

        it('readAll parses entire JSON into memory', function (): void {
            $reader = StreamReader::fromString('{"test": "value", "num": 42}');

            $result = $reader->readAll();

            expect($result)->toBe(['test' => 'value', 'num' => 42]);
        });

        it('readAll handles arrays', function (): void {
            $reader = StreamReader::fromString('[1, 2, 3, 4, 5]');

            $result = $reader->readAll();

            expect($result)->toBe([1, 2, 3, 4, 5]);
        });

        it('readAll handles scalars', function (): void {
            expect(StreamReader::fromString('"hello"')->readAll())->toBe('hello');
            expect(StreamReader::fromString('42')->readAll())->toBe(42);
            expect(StreamReader::fromString('3.14')->readAll())->toBe(3.14);
            expect(StreamReader::fromString('true')->readAll())->toBe(true);
            expect(StreamReader::fromString('false')->readAll())->toBe(false);
            expect(StreamReader::fromString('null')->readAll())->toBeNull();
        });

        it('readAll handles nested structures', function (): void {
            $json = '{"users": [{"id": 1, "name": "Alice"}, {"id": 2, "name": "Bob"}]}';
            $reader = StreamReader::fromString($json);

            $result = $reader->readAll();

            expect($result)->toBe([
                'users' => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                ],
            ]);
        });
    });

    describe('utility methods', function (): void {
        it('getStats returns accurate information', function (): void {
            $reader = StreamReader::fromString('{"test": "value"}');

            $stats = $reader->getStats();

            expect($stats)->toHaveKeys(['bytesRead', 'itemsProcessed', 'bufferSize', 'maxDepth']);
            expect($stats['bufferSize'])->toBe(Config::DEFAULT_BUFFER_SIZE);
            expect($stats['maxDepth'])->toBe(Config::DEFAULT_MAX_DEPTH);
            expect($stats['itemsProcessed'])->toBe(0);
        });

        it('getStats tracks items processed', function (): void {
            $reader = StreamReader::fromString('{"test": "value"}');

            $reader->readAll();
            $stats = $reader->getStats();

            expect($stats['itemsProcessed'])->toBe(1);
        });

        it('close closes owned stream', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'json_test_');
            file_put_contents($tempFile, '{"test": "value"}');

            $reader = StreamReader::fromFile($tempFile);
            $reader->close();

            // Should not throw
            expect(true)->toBeTrue();

            unlink($tempFile);
        });

        it('close does not close unowned stream', function (): void {
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, '{"test": "value"}');
            rewind($stream);

            $reader = StreamReader::fromStream($stream);
            $reader->close();

            // Stream should still be open
            expect(is_resource($stream))->toBeTrue();

            fclose($stream);
        });

        it('destructor closes owned stream', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'json_test_');
            file_put_contents($tempFile, '{"test": "value"}');

            $reader = StreamReader::fromFile($tempFile);
            unset($reader); // Trigger __destruct

            // Should not throw
            expect(true)->toBeTrue();

            unlink($tempFile);
        });
    });

    describe('resource management', function (): void {
        it('handles large files efficiently', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'json_test_');

            // Create a large JSON array
            $fp = fopen($tempFile, 'w');
            fwrite($fp, '[');
            for ($i = 0; $i < 1000; $i++) {
                if ($i > 0) {
                    fwrite($fp, ',');
                }
                fwrite($fp, json_encode(['id' => $i, 'value' => str_repeat('x', 100)]));
            }
            fwrite($fp, ']');
            fclose($fp);

            $reader = StreamReader::fromFile($tempFile);
            $result = $reader->readAll();

            expect(count($result))->toBe(1000);

            unlink($tempFile);
        });

        it('handles empty JSON structures', function (): void {
            expect(StreamReader::fromString('[]')->readAll())->toBe([]);
            expect(StreamReader::fromString('{}')->readAll())->toBe([]);
        });
    });

    describe('error handling', function (): void {
        it('throws IOException for non-readable file', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'json_test_');
            file_put_contents($tempFile, '[]');

            // Remove read permissions (Unix only, will skip on Windows)
            if (DIRECTORY_SEPARATOR === '/') {
                chmod($tempFile, 0000);

                try {
                    StreamReader::fromFile($tempFile);
                } catch (\JsonStream\Exception\IOException $e) {
                    // Restore permissions before cleanup
                    chmod($tempFile, 0644);
                    unlink($tempFile);

                    expect($e->getMessage())->toContain('not readable');

                    return;
                } finally {
                    if (file_exists($tempFile)) {
                        chmod($tempFile, 0644);
                        unlink($tempFile);
                    }
                }

                throw new \Exception('Expected IOException was not thrown');
            } else {
                // On Windows, skip this test
                unlink($tempFile);
                expect(true)->toBeTrue();
            }
        });
    });

    describe('internal methods', function (): void {
        it('provides access to buffer manager', function (): void {
            $reader = StreamReader::fromString('[]');

            // getBuffer is an internal method (line 343)
            expect($reader->getBuffer())->toBeInstanceOf(\JsonStream\Internal\BufferManager::class);
        });

        it('provides access to path evaluator', function (): void {
            $reader = StreamReader::fromString('[]');

            // Without a path, pathEvaluator is null (lines 351-353)
            expect($reader->getPathEvaluator())->toBeNull();
        });

        it('provides access to path evaluator when path is set', function (): void {
            $reader = StreamReader::fromString('[1, 2, 3]')->withPath('$[*]');

            // With a path, pathEvaluator exists
            expect($reader->getPathEvaluator())->not()->toBeNull();
        });

        it('filters data without pathParser', function (): void {
            $reader = StreamReader::fromString('{"value": 123}');

            // This uses readAll which eventually calls filterResults
            // When no path is set, line 287 returns [$value]
            $result = $reader->readAll();

            expect($result)->toBe(['value' => 123]);
        });
    });
});
