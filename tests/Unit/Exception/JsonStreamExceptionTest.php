<?php

declare(strict_types=1);

use JsonStream\Exception\JsonStreamException;

describe('JsonStreamException', function (): void {
    describe('context management', function (): void {
        it('has empty context by default', function (): void {
            $exception = new JsonStreamException('Test error');

            expect($exception->getContext())->toBe('');
        });

        it('can set and retrieve context', function (): void {
            $exception = new JsonStreamException('Test error');
            $exception->setContext('parsing array at depth 5');

            expect($exception->getContext())->toBe('parsing array at depth 5');
        });

        it('allows updating context multiple times', function (): void {
            $exception = new JsonStreamException('Test error');

            $exception->setContext('first context');
            expect($exception->getContext())->toBe('first context');

            $exception->setContext('second context');
            expect($exception->getContext())->toBe('second context');
        });

        it('can set empty context', function (): void {
            $exception = new JsonStreamException('Test error');
            $exception->setContext('some context');
            $exception->setContext('');

            expect($exception->getContext())->toBe('');
        });
    });

    describe('inheritance', function (): void {
        it('extends Exception', function (): void {
            $exception = new JsonStreamException('Test error');

            expect($exception)->toBeInstanceOf(Exception::class);
        });

        it('preserves exception message', function (): void {
            $exception = new JsonStreamException('Custom error message');

            expect($exception->getMessage())->toBe('Custom error message');
        });

        it('supports exception code', function (): void {
            $exception = new JsonStreamException('Test error', 123);

            expect($exception->getCode())->toBe(123);
        });

        it('supports previous exception', function (): void {
            $previous = new Exception('Previous error');
            $exception = new JsonStreamException('Test error', 0, $previous);

            expect($exception->getPrevious())->toBe($previous);
        });
    });
});
