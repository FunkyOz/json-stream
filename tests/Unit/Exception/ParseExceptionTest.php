<?php

declare(strict_types=1);

use JsonStream\Exception\JsonStreamException;
use JsonStream\Exception\ParseException;

describe('ParseException', function (): void {
    describe('position management', function (): void {
        it('has zero line and column by default', function (): void {
            $exception = new ParseException('Parse error');

            expect($exception->getJsonLine())->toBe(0);
            expect($exception->getJsonColumn())->toBe(0);
        });

        it('can set and retrieve position', function (): void {
            $exception = new ParseException('Parse error');
            $exception->setPosition(10, 25);

            expect($exception->getJsonLine())->toBe(10);
            expect($exception->getJsonColumn())->toBe(25);
        });

        it('allows updating position multiple times', function (): void {
            $exception = new ParseException('Parse error');

            $exception->setPosition(5, 12);
            expect($exception->getJsonLine())->toBe(5);
            expect($exception->getJsonColumn())->toBe(12);

            $exception->setPosition(20, 8);
            expect($exception->getJsonLine())->toBe(20);
            expect($exception->getJsonColumn())->toBe(8);
        });

        it('can set position to zero', function (): void {
            $exception = new ParseException('Parse error');
            $exception->setPosition(10, 20);
            $exception->setPosition(0, 0);

            expect($exception->getJsonLine())->toBe(0);
            expect($exception->getJsonColumn())->toBe(0);
        });
    });

    describe('string representation', function (): void {
        it('includes position in string when line is greater than zero', function (): void {
            $exception = new ParseException('Unexpected token');
            $exception->setPosition(5, 12);

            $string = (string) $exception;

            expect($string)->toContain('Unexpected token');
            expect($string)->toContain('at line 5, column 12');
        });

        it('includes position in string when column is greater than zero', function (): void {
            $exception = new ParseException('Unexpected token');
            $exception->setPosition(0, 5);

            $string = (string) $exception;

            expect($string)->toContain('at line 0, column 5');
        });

        it('includes position when both line and column are greater than zero', function (): void {
            $exception = new ParseException('Invalid JSON');
            $exception->setPosition(10, 25);

            $string = (string) $exception;

            expect($string)->toContain('Invalid JSON');
            expect($string)->toMatch('/at line 10, column 25/');
        });

        it('does not include position when both are zero', function (): void {
            $exception = new ParseException('Parse error');
            $exception->setPosition(0, 0);

            $string = (string) $exception;

            expect($string)->toContain('Parse error');
            expect($string)->not->toContain('at line');
        });

        it('does not include position by default', function (): void {
            $exception = new ParseException('Parse error');

            $string = (string) $exception;

            expect($string)->toContain('Parse error');
            expect($string)->not->toContain('at line');
        });

        it('formats position correctly in string', function (): void {
            $exception = new ParseException('Syntax error');
            $exception->setPosition(100, 200);

            $string = (string) $exception;

            expect($string)->toContain('Syntax error');
            expect($string)->toContain(' at line 100, column 200');
        });
    });

    describe('inheritance', function (): void {
        it('extends JsonStreamException', function (): void {
            $exception = new ParseException('Parse error');

            expect($exception)->toBeInstanceOf(JsonStreamException::class);
        });

        it('extends Exception', function (): void {
            $exception = new ParseException('Parse error');

            expect($exception)->toBeInstanceOf(Exception::class);
        });

        it('preserves exception message', function (): void {
            $exception = new ParseException('Invalid JSON syntax');

            expect($exception->getMessage())->toBe('Invalid JSON syntax');
        });

        it('supports exception code', function (): void {
            $exception = new ParseException('Parse error', 400);

            expect($exception->getCode())->toBe(400);
        });

        it('inherits context methods from JsonStreamException', function (): void {
            $exception = new ParseException('Parse error');
            $exception->setContext('parsing object key');

            expect($exception->getContext())->toBe('parsing object key');
        });
    });
});
