<?php

declare(strict_types=1);

use JsonStream\Exception\IOException;
use JsonStream\Exception\JsonStreamException;

describe('IOException', function (): void {
    describe('file path management', function (): void {
        it('has null file path by default', function (): void {
            $exception = new IOException('IO error');

            expect($exception->getFilePath())->toBeNull();
        });

        it('can set and retrieve file path', function (): void {
            $exception = new IOException('IO error');
            $exception->setFilePath('/path/to/file.json');

            expect($exception->getFilePath())->toBe('/path/to/file.json');
        });

        it('allows updating file path multiple times', function (): void {
            $exception = new IOException('IO error');

            $exception->setFilePath('/first/path.json');
            expect($exception->getFilePath())->toBe('/first/path.json');

            $exception->setFilePath('/second/path.json');
            expect($exception->getFilePath())->toBe('/second/path.json');
        });
    });

    describe('string representation', function (): void {
        it('includes file path in string when set', function (): void {
            $exception = new IOException('Cannot read file');
            $exception->setFilePath('/path/to/file.json');

            $string = (string) $exception;

            expect($string)->toContain('Cannot read file');
            expect($string)->toContain('(file: /path/to/file.json)');
        });

        it('does not include file path when null', function (): void {
            $exception = new IOException('Cannot read file');

            $string = (string) $exception;

            expect($string)->toContain('Cannot read file');
            expect($string)->not->toContain('(file:');
        });

        it('formats file path correctly in string', function (): void {
            $exception = new IOException('File error');
            $exception->setFilePath('/var/www/data.json');

            $string = (string) $exception;

            expect($string)->toMatch('/\(file: \/var\/www\/data\.json\)/');
        });
    });

    describe('inheritance', function (): void {
        it('extends JsonStreamException', function (): void {
            $exception = new IOException('IO error');

            expect($exception)->toBeInstanceOf(JsonStreamException::class);
        });

        it('extends Exception', function (): void {
            $exception = new IOException('IO error');

            expect($exception)->toBeInstanceOf(Exception::class);
        });

        it('preserves exception message', function (): void {
            $exception = new IOException('File not found');

            expect($exception->getMessage())->toBe('File not found');
        });

        it('supports exception code', function (): void {
            $exception = new IOException('IO error', 500);

            expect($exception->getCode())->toBe(500);
        });

        it('inherits context methods from JsonStreamException', function (): void {
            $exception = new IOException('IO error');
            $exception->setContext('reading configuration file');

            expect($exception->getContext())->toBe('reading configuration file');
        });
    });
});
