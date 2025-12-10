<?php

declare(strict_types=1);

use JsonStream\Exception\JsonStreamException;
use JsonStream\Exception\PathException;

describe('PathException', function (): void {
    describe('path management', function (): void {
        it('has empty path by default', function (): void {
            $exception = new PathException('Path error');

            expect($exception->getPath())->toBe('');
        });

        it('can set and retrieve path', function (): void {
            $exception = new PathException('Path error');
            $exception->setPath('$.users[*].name');

            expect($exception->getPath())->toBe('$.users[*].name');
        });

        it('allows updating path multiple times', function (): void {
            $exception = new PathException('Path error');

            $exception->setPath('$.users');
            expect($exception->getPath())->toBe('$.users');

            $exception->setPath('$.items[0]');
            expect($exception->getPath())->toBe('$.items[0]');
        });

        it('can set empty path', function (): void {
            $exception = new PathException('Path error');
            $exception->setPath('$.some.path');
            $exception->setPath('');

            expect($exception->getPath())->toBe('');
        });
    });

    describe('string representation', function (): void {
        it('includes path in string when set', function (): void {
            $exception = new PathException('Invalid path expression');
            $exception->setPath('$.users[*].name');

            $string = (string) $exception;

            expect($string)->toContain('Invalid path expression');
            expect($string)->toContain('(path: $.users[*].name)');
        });

        it('does not include path when empty', function (): void {
            $exception = new PathException('Invalid path expression');

            $string = (string) $exception;

            expect($string)->toContain('Invalid path expression');
            expect($string)->not->toContain('(path:');
        });

        it('formats path correctly in string', function (): void {
            $exception = new PathException('Path evaluation failed');
            $exception->setPath('$.store.books[?(@.price < 10)]');

            $string = (string) $exception;

            expect($string)->toContain('Path evaluation failed');
            expect($string)->toMatch('/\(path: \$\.store\.books\[\?\(@\.price < 10\)\]\)/');
        });

        it('includes simple path in string', function (): void {
            $exception = new PathException('Path not found');
            $exception->setPath('$.id');

            $string = (string) $exception;

            expect($string)->toContain('(path: $.id)');
        });

        it('includes complex path in string', function (): void {
            $exception = new PathException('Invalid filter');
            $exception->setPath('$.users[?(@.age > 18)].email');

            $string = (string) $exception;

            expect($string)->toContain('(path: $.users[?(@.age > 18)].email)');
        });
    });

    describe('inheritance', function (): void {
        it('extends JsonStreamException', function (): void {
            $exception = new PathException('Path error');

            expect($exception)->toBeInstanceOf(JsonStreamException::class);
        });

        it('extends Exception', function (): void {
            $exception = new PathException('Path error');

            expect($exception)->toBeInstanceOf(Exception::class);
        });

        it('preserves exception message', function (): void {
            $exception = new PathException('Unsupported path operator');

            expect($exception->getMessage())->toBe('Unsupported path operator');
        });

        it('supports exception code', function (): void {
            $exception = new PathException('Path error', 404);

            expect($exception->getCode())->toBe(404);
        });

        it('inherits context methods from JsonStreamException', function (): void {
            $exception = new PathException('Path error');
            $exception->setContext('evaluating filter expression');

            expect($exception->getContext())->toBe('evaluating filter expression');
        });
    });
});
