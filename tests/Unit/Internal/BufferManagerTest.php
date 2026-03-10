<?php

declare(strict_types=1);

use JsonStream\Config;
use JsonStream\Exception\IOException;
use JsonStream\Internal\Lexer;
use JsonStream\Internal\TokenType;

/**
 * Custom stream wrapper that simulates fread failure
 */
class FailingStreamWrapper
{
    public $context;

    private int $position = 0;

    private string $data = '"test"';

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

    private string $data = '"test"';

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

/**
 * Tests for Lexer's buffer I/O functionality (formerly BufferManager)
 *
 * These tests verify the stream reading, buffering, position tracking,
 * and error handling that was previously in BufferManager and is now
 * integrated into the Lexer.
 */
describe('Lexer Buffer I/O', function (): void {
    it('handles EOF correctly', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, '1');
        rewind($stream);

        $lexer = new Lexer($stream);

        expect($lexer->isEof())->toBeFalse();
        $lexer->nextToken(); // consume 1
        $lexer->nextToken(); // EOF token
        expect($lexer->isEof())->toBeTrue();
    });

    it('tracks position correctly for regular characters', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, '"abc"');
        rewind($stream);

        $lexer = new Lexer($stream);

        expect($lexer->getLine())->toBe(0);
        expect($lexer->getColumn())->toBe(0);

        $lexer->nextToken(); // consume "abc"
        // After reading "abc" (5 bytes: " a b c ")
        expect($lexer->getLine())->toBe(0);
        expect($lexer->getColumn())->toBe(5);
    });

    it('tracks position correctly with newlines', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "1\n2");
        rewind($stream);

        $lexer = new Lexer($stream);

        $lexer->nextToken(); // 1
        expect($lexer->getLine())->toBe(0);

        // After consuming whitespace (newline) and reading 2
        $lexer->nextToken(); // 2
        expect($lexer->getLine())->toBe(1);
    });

    it('refills buffer automatically for large input', function (): void {
        $stream = fopen('php://memory', 'r+');
        // Create a JSON array with many elements that exceeds buffer size
        $elements = implode(',', range(1, 2000));
        fwrite($stream, "[$elements]");
        rewind($stream);

        $lexer = new Lexer($stream, 1024); // Small buffer

        // Read opening bracket
        $token = $lexer->nextToken();
        expect($token->type)->toBe(TokenType::LEFT_BRACKET);

        // Read all elements
        $count = 0;
        while (true) {
            $token = $lexer->nextToken();
            if ($token->type === TokenType::RIGHT_BRACKET) {
                break;
            }
            if ($token->type === TokenType::NUMBER) {
                $count++;
            }
        }

        expect($count)->toBe(2000);
    });

    it('tracks total bytes read', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, '"hello"');
        rewind($stream);

        $lexer = new Lexer($stream);

        expect($lexer->getTotalBytesRead())->toBe(0);

        $lexer->nextToken(); // consume "hello"
        expect($lexer->getTotalBytesRead())->toBe(7); // 7 bytes: "hello"
    });

    it('handles seekable streams with reset', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, '[1,2]');
        rewind($stream);

        $lexer = new Lexer($stream);

        $lexer->nextToken(); // [
        $lexer->nextToken(); // 1

        $lexer->reset();

        $token = $lexer->nextToken(); // Back to [
        expect($token->type)->toBe(TokenType::LEFT_BRACKET);
        expect($lexer->getLine())->toBe(0);
    });

    it('handles non-seekable streams with reset as no-op', function (): void {
        $stream = fopen('php://stdin', 'r');
        $lexer = new Lexer($stream);

        // Should not throw, just no-op
        $lexer->reset();

        expect(true)->toBeTrue();
    });

    it('throws IOException when fseek fails during reset', function (): void {
        stream_wrapper_register('failseek', NonSeekableStreamWrapper::class);

        $stream = fopen('failseek://test', 'r');

        try {
            $lexer = new Lexer($stream, 1024);
            $lexer->nextToken(); // Read some data

            expect(fn () => $lexer->reset())
                ->toThrow(IOException::class, 'Failed to seek stream');
        } finally {
            fclose($stream);
            stream_wrapper_unregister('failseek');
        }
    });

    it('throws on invalid stream resource', function (): void {
        expect(fn () => new Lexer('not a resource'))
            ->toThrow(IOException::class, 'Invalid stream resource');
    });

    it('throws on non-readable stream', function (): void {
        $stream = fopen('php://output', 'w');

        expect(fn () => new Lexer($stream))
            ->toThrow(IOException::class, 'not readable');
    });

    it('validates buffer size limits', function (): void {
        $stream = fopen('php://memory', 'r+');

        expect(fn () => new Lexer($stream, 100)) // Too small
            ->toThrow(IOException::class, 'Buffer size must be');

        expect(fn () => new Lexer($stream, 2000000)) // Too large
            ->toThrow(IOException::class, 'Buffer size must be');
    });

    it('accepts valid buffer sizes', function (): void {
        $stream = fopen('php://memory', 'r+');

        $lexer1 = new Lexer($stream, Config::MIN_BUFFER_SIZE);
        expect($lexer1)->toBeInstanceOf(Lexer::class);

        $lexer2 = new Lexer($stream, Config::DEFAULT_BUFFER_SIZE);
        expect($lexer2)->toBeInstanceOf(Lexer::class);

        $lexer3 = new Lexer($stream, Config::MAX_BUFFER_SIZE);
        expect($lexer3)->toBeInstanceOf(Lexer::class);
    });

    it('handles empty stream', function (): void {
        $stream = fopen('php://memory', 'r+');

        $lexer = new Lexer($stream);

        $token = $lexer->nextToken();
        expect($token->type)->toBe(TokenType::EOF);
        expect($lexer->isEof())->toBeTrue();
    });

    it('handles single token stream', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, '1');
        rewind($stream);

        $lexer = new Lexer($stream);

        $token = $lexer->nextToken();
        expect($token->type)->toBe(TokenType::NUMBER);
        expect($token->value)->toBe(1);

        $token = $lexer->nextToken();
        expect($token->type)->toBe(TokenType::EOF);
    });

    it('handles unicode characters in strings', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, '"Hello 世界"');
        rewind($stream);

        $lexer = new Lexer($stream);

        $token = $lexer->nextToken();
        expect($token->type)->toBe(TokenType::STRING);
        expect($token->value)->toBe('Hello 世界');
    });

    it('throws IOException on fread failure', function (): void {
        stream_wrapper_register('failread', FailingStreamWrapper::class);

        $stream = fopen('failread://test', 'r');

        try {
            $lexer = new Lexer($stream, 1024);

            // First token succeeds
            $lexer->nextToken();

            // This triggers refill which fails
            $lexer->nextToken();

            expect(false)->toBeTrue('Expected IOException to be thrown');
        } catch (IOException $e) {
            expect($e->getMessage())->toContain('Failed to read from stream');
        } finally {
            fclose($stream);
            stream_wrapper_unregister('failread');
        }
    });
});
