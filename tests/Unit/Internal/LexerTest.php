<?php

declare(strict_types=1);

use JsonStream\Exception\ParseException;
use JsonStream\Internal\BufferManager;
use JsonStream\Internal\Lexer;
use JsonStream\Internal\TokenType;

function createLexer(string $json): Lexer
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $json);
    rewind($stream);
    $buffer = new BufferManager($stream);

    return new Lexer($buffer);
}

describe('Lexer', function (): void {
    describe('structural tokens', function (): void {
        it('tokenizes left brace', function (): void {
            $lexer = createLexer('{');
            $token = $lexer->nextToken();

            expect($token->type)->toBe(TokenType::LEFT_BRACE);
        });

        it('tokenizes right brace', function (): void {
            $lexer = createLexer('}');
            $token = $lexer->nextToken();

            expect($token->type)->toBe(TokenType::RIGHT_BRACE);
        });

        it('tokenizes left bracket', function (): void {
            $lexer = createLexer('[');
            $token = $lexer->nextToken();

            expect($token->type)->toBe(TokenType::LEFT_BRACKET);
        });

        it('tokenizes right bracket', function (): void {
            $lexer = createLexer(']');
            $token = $lexer->nextToken();

            expect($token->type)->toBe(TokenType::RIGHT_BRACKET);
        });

        it('tokenizes colon', function (): void {
            $lexer = createLexer(':');
            $token = $lexer->nextToken();

            expect($token->type)->toBe(TokenType::COLON);
        });

        it('tokenizes comma', function (): void {
            $lexer = createLexer(',');
            $token = $lexer->nextToken();

            expect($token->type)->toBe(TokenType::COMMA);
        });

        it('tokenizes multiple structural tokens', function (): void {
            $lexer = createLexer('{}[]:,');

            expect($lexer->nextToken()->type)->toBe(TokenType::LEFT_BRACE);
            expect($lexer->nextToken()->type)->toBe(TokenType::RIGHT_BRACE);
            expect($lexer->nextToken()->type)->toBe(TokenType::LEFT_BRACKET);
            expect($lexer->nextToken()->type)->toBe(TokenType::RIGHT_BRACKET);
            expect($lexer->nextToken()->type)->toBe(TokenType::COLON);
            expect($lexer->nextToken()->type)->toBe(TokenType::COMMA);
        });
    });

    describe('string tokens', function (): void {
        it('tokenizes simple string', function (): void {
            $lexer = createLexer('"hello"');
            $token = $lexer->nextToken();

            expect($token->type)->toBe(TokenType::STRING);
            expect($token->value)->toBe('hello');
        });

        it('tokenizes empty string', function (): void {
            $lexer = createLexer('""');
            $token = $lexer->nextToken();

            expect($token->type)->toBe(TokenType::STRING);
            expect($token->value)->toBe('');
        });

        it('tokenizes string with escaped quotes', function (): void {
            $lexer = createLexer('"hello \"world\""');
            $token = $lexer->nextToken();

            expect($token->value)->toBe('hello "world"');
        });

        it('tokenizes string with escaped backslash', function (): void {
            $lexer = createLexer('"path\\\\to\\\\file"');
            $token = $lexer->nextToken();

            expect($token->value)->toBe('path\\to\\file');
        });

        it('tokenizes string with common escape sequences', function (): void {
            $lexer = createLexer('"\\n\\r\\t\\b\\f"');
            $token = $lexer->nextToken();

            expect($token->value)->toBe("\n\r\t\x08\f");
        });

        it('tokenizes string with forward slash escape', function (): void {
            $lexer = createLexer('"\\/"');
            $token = $lexer->nextToken();

            expect($token->value)->toBe('/');
        });

        it('tokenizes string with unicode escape', function (): void {
            $lexer = createLexer('"\\u0048\\u0065\\u006C\\u006C\\u006F"'); // "Hello"
            $token = $lexer->nextToken();

            expect($token->value)->toBe('Hello');
        });

        it('tokenizes string with emoji unicode', function (): void {
            $lexer = createLexer('"\\uD83D\\uDE00"'); // 😀 (surrogate pair)
            $token = $lexer->nextToken();

            expect($token->value)->toBe('😀');
        });

        it('throws on unterminated string', function (): void {
            expect(fn () => createLexer('"hello')->nextToken())
                ->toThrow(ParseException::class, 'Unterminated string');
        });

        it('throws on invalid escape sequence', function (): void {
            expect(fn () => createLexer('"\\x"')->nextToken())
                ->toThrow(ParseException::class, 'Invalid escape sequence');
        });

        it('throws on control characters', function (): void {
            expect(fn () => createLexer("\"\x01\"")->nextToken())
                ->toThrow(ParseException::class, 'Invalid control character');
        });

        it('throws on invalid unicode escape', function (): void {
            expect(fn () => createLexer('"\\uXXXX"')->nextToken())
                ->toThrow(ParseException::class, 'Invalid Unicode escape');
        });
    });

    describe('number tokens', function (): void {
        it('tokenizes positive integer', function (): void {
            $lexer = createLexer('123');
            $token = $lexer->nextToken();

            expect($token->type)->toBe(TokenType::NUMBER);
            expect($token->value)->toBe(123);
        });

        it('tokenizes negative integer', function (): void {
            $lexer = createLexer('-456');
            $token = $lexer->nextToken();

            expect($token->value)->toBe(-456);
        });

        it('tokenizes zero', function (): void {
            $lexer = createLexer('0');
            $token = $lexer->nextToken();

            expect($token->value)->toBe(0);
        });

        it('tokenizes floating point number', function (): void {
            $lexer = createLexer('123.456');
            $token = $lexer->nextToken();

            expect($token->type)->toBe(TokenType::NUMBER);
            expect($token->value)->toBe(123.456);
        });

        it('tokenizes negative float', function (): void {
            $lexer = createLexer('-78.9');
            $token = $lexer->nextToken();

            expect($token->value)->toBe(-78.9);
        });

        it('tokenizes scientific notation with positive exponent', function (): void {
            $lexer = createLexer('1.5e10');
            $token = $lexer->nextToken();

            expect($token->value)->toBe(1.5e10);
        });

        it('tokenizes scientific notation with negative exponent', function (): void {
            $lexer = createLexer('2.5e-3');
            $token = $lexer->nextToken();

            expect($token->value)->toBe(0.0025);
        });

        it('tokenizes scientific notation with capital E', function (): void {
            $lexer = createLexer('1E5');
            $token = $lexer->nextToken();

            expect($token->value)->toBe(100000.0);
        });

        it('tokenizes multiple numbers', function (): void {
            $lexer = createLexer('1 2.5 -3 4e2');

            expect($lexer->nextToken()->value)->toBe(1);
            expect($lexer->nextToken()->value)->toBe(2.5);
            expect($lexer->nextToken()->value)->toBe(-3);
            expect($lexer->nextToken()->value)->toBe(400.0);
        });

        it('throws on leading zeros', function (): void {
            expect(fn () => createLexer('0123')->nextToken())
                ->toThrow(ParseException::class, 'Leading zeros not allowed');
        });

        it('throws on invalid number after minus', function (): void {
            expect(fn () => createLexer('- ')->nextToken())
                ->toThrow(ParseException::class, 'Expected digit after minus');
        });

        it('throws on decimal without digits', function (): void {
            expect(fn () => createLexer('123.')->nextToken())
                ->toThrow(ParseException::class, 'Expected digit after decimal point');
        });

        it('throws on exponent without digits', function (): void {
            expect(fn () => createLexer('1e')->nextToken())
                ->toThrow(ParseException::class, 'Expected digit in exponent');
        });
    });

    describe('keyword tokens', function (): void {
        it('tokenizes true', function (): void {
            $lexer = createLexer('true');
            $token = $lexer->nextToken();

            expect($token->type)->toBe(TokenType::TRUE);
            expect($token->value)->toBe(true);
        });

        it('tokenizes false', function (): void {
            $lexer = createLexer('false');
            $token = $lexer->nextToken();

            expect($token->type)->toBe(TokenType::FALSE);
            expect($token->value)->toBe(false);
        });

        it('tokenizes null', function (): void {
            $lexer = createLexer('null');
            $token = $lexer->nextToken();

            expect($token->type)->toBe(TokenType::NULL);
            expect($token->value)->toBeNull();
        });

        it('throws on invalid keyword', function (): void {
            expect(fn () => createLexer('tru')->nextToken())
                ->toThrow(ParseException::class, 'Invalid keyword');
        });

        it('throws on partial false keyword', function (): void {
            expect(fn () => createLexer('fals')->nextToken())
                ->toThrow(ParseException::class, 'Invalid keyword');
        });

        it('throws on partial null keyword', function (): void {
            expect(fn () => createLexer('nul')->nextToken())
                ->toThrow(ParseException::class, 'Invalid keyword');
        });
    });

    describe('whitespace handling', function (): void {
        it('skips spaces', function (): void {
            $lexer = createLexer('   123');
            $token = $lexer->nextToken();

            expect($token->value)->toBe(123);
        });

        it('skips tabs', function (): void {
            $lexer = createLexer("\t\t123");
            $token = $lexer->nextToken();

            expect($token->value)->toBe(123);
        });

        it('skips newlines', function (): void {
            $lexer = createLexer("\n\n123");
            $token = $lexer->nextToken();

            expect($token->value)->toBe(123);
        });

        it('skips carriage returns', function (): void {
            $lexer = createLexer("\r\r123");
            $token = $lexer->nextToken();

            expect($token->value)->toBe(123);
        });

        it('skips mixed whitespace', function (): void {
            $lexer = createLexer(" \t\n\r 123");
            $token = $lexer->nextToken();

            expect($token->value)->toBe(123);
        });
    });

    describe('peek functionality', function (): void {
        it('peeks without consuming', function (): void {
            $lexer = createLexer('123 456');

            $peek1 = $lexer->peekToken();
            $peek2 = $lexer->peekToken();

            expect($peek1->value)->toBe(123);
            expect($peek2->value)->toBe(123); // Same token

            $next = $lexer->nextToken();
            expect($next->value)->toBe(123); // Consumes peeked token

            expect($lexer->nextToken()->value)->toBe(456);
        });

        it('peek returns same object', function (): void {
            $lexer = createLexer('true');

            $peek1 = $lexer->peekToken();
            $peek2 = $lexer->peekToken();

            expect($peek1)->toBe($peek2); // Exact same object
        });
    });

    describe('EOF token', function (): void {
        it('returns EOF at end of input', function (): void {
            $lexer = createLexer('123');

            $lexer->nextToken(); // consume 123

            $token = $lexer->nextToken();
            expect($token->type)->toBe(TokenType::EOF);
        });

        it('returns EOF on empty input', function (): void {
            $lexer = createLexer('');

            $token = $lexer->nextToken();
            expect($token->type)->toBe(TokenType::EOF);
        });

        it('returns EOF on whitespace-only input', function (): void {
            $lexer = createLexer("   \n\t  "); // Use double quotes for escape sequences

            $token = $lexer->nextToken();
            expect($token->type)->toBe(TokenType::EOF);
        });
    });

    describe('position tracking', function (): void {
        it('tracks token position', function (): void {
            $lexer = createLexer('123');
            $token = $lexer->nextToken();

            // Positions are 1-based for display (error messages)
            expect($token->line)->toBe(1);
            expect($token->column)->toBe(1);
        });

        it('tracks position across multiple lines', function (): void {
            $lexer = createLexer("{\n  \"key\": 123\n}");

            $lexer->nextToken(); // {
            $keyToken = $lexer->nextToken(); // "key"

            expect($keyToken->line)->toBe(2); // Second line (1-based)
        });
    });

    describe('error messages', function (): void {
        it('includes position in error', function (): void {
            try {
                createLexer('invalid')->nextToken();
                expect(false)->toBeTrue(); // Should not reach
            } catch (ParseException $e) {
                expect($e->getMessage())->toContain('Unexpected character');
                expect($e->getJsonLine())->toBe(1);
                expect($e->getJsonColumn())->toBe(1);
            }
        });
    });

    describe('complex JSON', function (): void {
        it('tokenizes simple object', function (): void {
            $lexer = createLexer('{"name":"test"}');

            expect($lexer->nextToken()->type)->toBe(TokenType::LEFT_BRACE);
            expect($lexer->nextToken()->value)->toBe('name');
            expect($lexer->nextToken()->type)->toBe(TokenType::COLON);
            expect($lexer->nextToken()->value)->toBe('test');
            expect($lexer->nextToken()->type)->toBe(TokenType::RIGHT_BRACE);
        });

        it('tokenizes simple array', function (): void {
            $lexer = createLexer('[1,2,3]');

            expect($lexer->nextToken()->type)->toBe(TokenType::LEFT_BRACKET);
            expect($lexer->nextToken()->value)->toBe(1);
            expect($lexer->nextToken()->type)->toBe(TokenType::COMMA);
            expect($lexer->nextToken()->value)->toBe(2);
            expect($lexer->nextToken()->type)->toBe(TokenType::COMMA);
            expect($lexer->nextToken()->value)->toBe(3);
            expect($lexer->nextToken()->type)->toBe(TokenType::RIGHT_BRACKET);
        });
    });
});
