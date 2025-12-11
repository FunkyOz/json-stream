<?php

declare(strict_types=1);

use JsonStream\Config;
use JsonStream\Exception\IOException;
use JsonStream\Internal\BufferManager;

/**
 * Custom stream wrapper that simulates fread failure
 */
class FailingStreamWrapper
{
    public $context;

    private int $position = 0;

    private string $data = 'test';

    private bool $failNext = false;

    public function stream_open($path, $mode, $options, &$opened_path): bool
    {
        return true;
    }

    public function stream_read($count): string|false
    {
        if ($this->failNext) {
            return false; // Simulate read failure
        }

        // First read succeeds to fill initial buffer
        if ($this->position >= strlen($this->data)) {
            return '';
        }

        $chunk = substr($this->data, $this->position, $count);
        $this->position += strlen($chunk);

        // Mark to fail on next read (refill)
        if ($this->position >= strlen($this->data)) {
            $this->failNext = true;
        }

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->data) && ! $this->failNext;
    }

    public function stream_stat(): array
    {
        return [];
    }

    public function stream_tell(): int
    {
        return $this->position;
    }
}

/**
 * Custom stream wrapper that reports as seekable but fails on seek
 */
class NonSeekableStreamWrapper
{
    public $context;

    private int $position = 0;

    private string $data = 'test data';

    public function stream_open($path, $mode, $options, &$opened_path): bool
    {
        return true;
    }

    public function stream_read($count): string|false
    {
        if ($this->position >= strlen($this->data)) {
            return '';
        }

        $chunk = substr($this->data, $this->position, min($count, strlen($this->data) - $this->position));
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->data);
    }

    public function stream_stat(): array
    {
        // Report as seekable
        return ['seekable' => 1];
    }

    public function stream_tell(): int
    {
        return $this->position;
    }

    public function stream_seek($offset, $whence = SEEK_SET): bool
    {
        // Always fail on seek
        return false;
    }
}

describe('BufferManager', function (): void {
    it('reads bytes sequentially', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'abc');
        rewind($stream);

        $buffer = new BufferManager($stream);

        expect($buffer->readByte())->toBe('a');
        expect($buffer->readByte())->toBe('b');
        expect($buffer->readByte())->toBe('c');
        expect($buffer->readByte())->toBeNull();
    });

    it('handles EOF correctly', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'x');
        rewind($stream);

        $buffer = new BufferManager($stream);

        expect($buffer->isEof())->toBeFalse();
        $buffer->readByte(); // consume 'x'
        expect($buffer->readByte())->toBeNull();
        expect($buffer->isEof())->toBeTrue();
    });

    it('tracks position correctly for regular characters', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'abc');
        rewind($stream);

        $buffer = new BufferManager($stream);

        expect($buffer->getLine())->toBe(0);
        expect($buffer->getColumn())->toBe(0);

        $buffer->readByte(); // 'a'
        expect($buffer->getLine())->toBe(0);
        expect($buffer->getColumn())->toBe(1);

        $buffer->readByte(); // 'b'
        expect($buffer->getLine())->toBe(0);
        expect($buffer->getColumn())->toBe(2);
    });

    it('tracks position correctly with newlines', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "a\nbc");
        rewind($stream);

        $buffer = new BufferManager($stream);

        $buffer->readByte(); // 'a'
        expect($buffer->getLine())->toBe(0);
        expect($buffer->getColumn())->toBe(1);

        $buffer->readByte(); // '\n'
        expect($buffer->getLine())->toBe(1);
        expect($buffer->getColumn())->toBe(0);

        $buffer->readByte(); // 'b'
        expect($buffer->getLine())->toBe(1);
        expect($buffer->getColumn())->toBe(1);
    });

    it('peeks without consuming bytes', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'abc');
        rewind($stream);

        $buffer = new BufferManager($stream);

        expect($buffer->peek())->toBe('a');
        expect($buffer->peek())->toBe('a'); // Same byte
        expect($buffer->readByte())->toBe('a'); // Now consumed

        expect($buffer->peek())->toBe('b');
        expect($buffer->peek(1))->toBe('c');
    });

    it('peeks with offset', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'abcdef');
        rewind($stream);

        $buffer = new BufferManager($stream);

        expect($buffer->peek(0))->toBe('a');
        expect($buffer->peek(1))->toBe('b');
        expect($buffer->peek(2))->toBe('c');
        expect($buffer->peek(5))->toBe('f');
    });

    it('peek returns null beyond EOF after refill', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'abc');
        rewind($stream);

        $buffer = new BufferManager($stream);

        // Read to EOF
        $buffer->readByte(); // 'a'
        $buffer->readByte(); // 'b'
        $buffer->readByte(); // 'c'

        // Peek beyond EOF should return null (triggers refill, then returns null)
        expect($buffer->peek())->toBeNull();
        expect($buffer->peek(10))->toBeNull();
    });

    it('reads chunks efficiently', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'hello world');
        rewind($stream);

        $buffer = new BufferManager($stream);

        expect($buffer->readChunk(5))->toBe('hello');
        expect($buffer->readChunk(6))->toBe(' world');
        expect($buffer->readChunk(10))->toBe(''); // EOF
    });

    it('refills buffer automatically', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, str_repeat('x', 10000)); // Larger than default buffer
        rewind($stream);

        $buffer = new BufferManager($stream, 1024); // Small buffer

        $count = 0;
        while ($buffer->readByte() !== null) {
            $count++;
        }

        expect($count)->toBe(10000);
    });

    it('tracks total bytes read', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'hello');
        rewind($stream);

        $buffer = new BufferManager($stream);

        expect($buffer->getTotalBytesRead())->toBe(0);

        $buffer->readByte();
        expect($buffer->getTotalBytesRead())->toBe(1);

        $buffer->readChunk(3);
        expect($buffer->getTotalBytesRead())->toBe(4);
    });

    it('handles seekable streams with reset', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'abc');
        rewind($stream);

        $buffer = new BufferManager($stream);

        $buffer->readByte(); // 'a'
        $buffer->readByte(); // 'b'

        $buffer->reset();

        expect($buffer->readByte())->toBe('a'); // Back to start
        expect($buffer->getLine())->toBe(0);
        expect($buffer->getColumn())->toBe(1);
    });

    it('handles non-seekable streams with reset as no-op', function (): void {
        $stream = fopen('php://stdin', 'r');
        $buffer = new BufferManager($stream);

        // Should not throw, just no-op
        $buffer->reset();

        expect(true)->toBeTrue(); // If we get here, no exception was thrown
    });

    it('throws IOException when fseek fails during reset', function (): void {
        // Register a custom stream wrapper that reports as seekable but fails on seek
        stream_wrapper_register('failseek', NonSeekableStreamWrapper::class);

        $stream = fopen('failseek://test', 'r');

        try {
            $buffer = new BufferManager($stream);
            $buffer->readByte(); // Read some data

            // This should trigger fseek which will fail
            expect(fn () => $buffer->reset())
                ->toThrow(IOException::class, 'Failed to seek stream');
        } finally {
            fclose($stream);
            stream_wrapper_unregister('failseek');
        }
    });

    it('throws on invalid stream resource', function (): void {
        expect(fn () => new BufferManager('not a resource'))
            ->toThrow(IOException::class, 'Invalid stream resource');
    });

    it('throws on non-readable stream', function (): void {
        $stream = fopen('php://output', 'w'); // Write-only

        expect(fn () => new BufferManager($stream))
            ->toThrow(IOException::class, 'not readable');
    });

    it('validates buffer size limits', function (): void {
        $stream = fopen('php://memory', 'r+');

        expect(fn () => new BufferManager($stream, 100)) // Too small
            ->toThrow(IOException::class, 'Buffer size must be');

        expect(fn () => new BufferManager($stream, 2000000)) // Too large
            ->toThrow(IOException::class, 'Buffer size must be');
    });

    it('accepts valid buffer sizes', function (): void {
        $stream = fopen('php://memory', 'r+');

        $buffer1 = new BufferManager($stream, Config::MIN_BUFFER_SIZE);
        expect($buffer1)->toBeInstanceOf(BufferManager::class);

        $buffer2 = new BufferManager($stream, Config::DEFAULT_BUFFER_SIZE);
        expect($buffer2)->toBeInstanceOf(BufferManager::class);

        $buffer3 = new BufferManager($stream, Config::MAX_BUFFER_SIZE);
        expect($buffer3)->toBeInstanceOf(BufferManager::class);
    });

    it('handles empty stream', function (): void {
        $stream = fopen('php://memory', 'r+');
        // Don't write anything

        $buffer = new BufferManager($stream);

        expect($buffer->readByte())->toBeNull();
        expect($buffer->isEof())->toBeTrue();
    });

    it('handles single byte stream', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'x');
        rewind($stream);

        $buffer = new BufferManager($stream);

        expect($buffer->readByte())->toBe('x');
        expect($buffer->readByte())->toBeNull();
    });

    it('handles unicode characters', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'Hello 世界'); // "Hello World" in Chinese
        rewind($stream);

        $buffer = new BufferManager($stream);

        expect($buffer->readByte())->toBe('H');
        expect($buffer->readByte())->toBe('e');
        expect($buffer->readByte())->toBe('l');
        expect($buffer->readByte())->toBe('l');
        expect($buffer->readByte())->toBe('o');
        expect($buffer->readByte())->toBe(' ');
        // Chinese characters are multi-byte UTF-8
        expect($buffer->readByte())->toBeString();
    });

    it('returns empty string for readChunk with zero size', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'test data');
        rewind($stream);

        $buffer = new BufferManager($stream);
        $result = $buffer->readChunk(0);

        expect($result)->toBe('');
        expect($buffer->getTotalBytesRead())->toBe(0); // Verify no bytes were consumed
    });

    it('returns empty string for readChunk with negative size', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'test data');
        rewind($stream);

        $buffer = new BufferManager($stream);
        $result = $buffer->readChunk(-5);

        expect($result)->toBe('');
        expect($buffer->getTotalBytesRead())->toBe(0); // Verify no bytes were consumed
    });

    it('throws IOException on fread failure', function (): void {
        // Register a custom stream wrapper that fails on read
        stream_wrapper_register('failread', FailingStreamWrapper::class);

        $stream = fopen('failread://test', 'r');

        try {
            $buffer = new BufferManager($stream, 1024);

            // First readChunk will succeed
            $buffer->readChunk(4);

            // This should trigger refill which will fail
            $buffer->readChunk(1);

            // If we get here, the test failed
            expect(false)->toBeTrue('Expected IOException to be thrown');
        } catch (IOException $e) {
            expect($e->getMessage())->toContain('Failed to read from stream');
        } finally {
            fclose($stream);
            stream_wrapper_unregister('failread');
        }
    });
});
