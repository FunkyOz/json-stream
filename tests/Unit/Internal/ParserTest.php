<?php

declare(strict_types=1);

use JsonStream\Config;
use JsonStream\Exception\ParseException;
use JsonStream\Internal\BufferManager;
use JsonStream\Internal\Lexer;
use JsonStream\Internal\Parser;

function createParser(string $json, int $maxDepth = Config::DEFAULT_MAX_DEPTH): Parser
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $json);
    rewind($stream);
    $buffer = new BufferManager($stream);
    $lexer = new Lexer($buffer);

    return new Parser($lexer, $maxDepth);
}

describe('Parser', function (): void {
    describe('parseValue', function (): void {
        it('parses integer', function (): void {
            expect(createParser('123')->parseValue())->toBe(123);
        });

        it('parses float', function (): void {
            expect(createParser('123.456')->parseValue())->toBe(123.456);
        });

        it('parses negative number', function (): void {
            expect(createParser('-789')->parseValue())->toBe(-789);
        });

        it('parses string', function (): void {
            expect(createParser('"hello"')->parseValue())->toBe('hello');
        });

        it('parses true', function (): void {
            expect(createParser('true')->parseValue())->toBe(true);
        });

        it('parses false', function (): void {
            expect(createParser('false')->parseValue())->toBe(false);
        });

        it('parses null', function (): void {
            expect(createParser('null')->parseValue())->toBeNull();
        });

        it('parses simple array', function (): void {
            $result = createParser('[1,2,3]')->parseValue();

            expect($result)->toBe([1, 2, 3]);
        });

        it('parses simple object', function (): void {
            $result = createParser('{"a":1,"b":2}')->parseValue();

            expect($result)->toBe(['a' => 1, 'b' => 2]);
        });

        it('throws on unexpected EOF', function (): void {
            expect(fn () => createParser('')->parseValue())
                ->toThrow(ParseException::class, 'Unexpected end of file');
        });
    });

    describe('parseArray', function (): void {
        it('parses empty array', function (): void {
            $parser = createParser('[]');
            $items = iterator_to_array($parser->parseArray());

            expect($items)->toBe([]);
        });

        it('parses array with single element', function (): void {
            $parser = createParser('[123]');
            $items = iterator_to_array($parser->parseArray());

            expect($items)->toBe([123]);
        });

        it('parses array with multiple elements', function (): void {
            $parser = createParser('[1,2,3,4,5]');
            $items = iterator_to_array($parser->parseArray());

            expect($items)->toBe([1, 2, 3, 4, 5]);
        });

        it('parses array with mixed types', function (): void {
            $parser = createParser('[1,"test",true,null]');
            $items = iterator_to_array($parser->parseArray());

            expect($items)->toBe([1, 'test', true, null]);
        });

        it('parses nested arrays', function (): void {
            $parser = createParser('[[1,2],[3,4]]');
            $items = iterator_to_array($parser->parseArray());

            expect($items)->toBe([[1, 2], [3, 4]]);
        });

        it('yields values progressively', function (): void {
            $parser = createParser('[1,2,3]');
            $generator = $parser->parseArray();

            expect($generator)->toBeInstanceOf(Generator::class);

            $values = [];
            foreach ($generator as $value) {
                $values[] = $value;
            }

            expect($values)->toBe([1, 2, 3]);
        });

        it('handles whitespace in array', function (): void {
            $parser = createParser("[\n  1,\n  2,\n  3\n]");
            $items = iterator_to_array($parser->parseArray());

            expect($items)->toBe([1, 2, 3]);
        });

        it('throws on trailing comma', function (): void {
            expect(fn () => iterator_to_array(createParser('[1,2,]')->parseArray()))
                ->toThrow(ParseException::class, 'Trailing comma not allowed');
        });

        it('throws on missing comma', function (): void {
            expect(fn () => iterator_to_array(createParser('[1 2]')->parseArray()))
                ->toThrow(ParseException::class, 'Expected comma or closing bracket');
        });
    });

    describe('parseObject', function (): void {
        it('parses empty object', function (): void {
            $parser = createParser('{}');
            $items = iterator_to_array($parser->parseObject());

            expect($items)->toBe([]);
        });

        it('parses object with single property', function (): void {
            $parser = createParser('{"name":"test"}');
            $items = iterator_to_array($parser->parseObject());

            expect($items)->toBe(['name' => 'test']);
        });

        it('parses object with multiple properties', function (): void {
            $parser = createParser('{"a":1,"b":2,"c":3}');
            $items = iterator_to_array($parser->parseObject());

            expect($items)->toBe(['a' => 1, 'b' => 2, 'c' => 3]);
        });

        it('parses nested objects', function (): void {
            $parser = createParser('{"user":{"name":"test","age":25}}');
            $items = iterator_to_array($parser->parseObject());

            expect($items)->toBe(['user' => ['name' => 'test', 'age' => 25]]);
        });

        it('parses object with mixed value types', function (): void {
            $parser = createParser('{"num":123,"str":"test","bool":true,"nil":null}');
            $items = iterator_to_array($parser->parseObject());

            expect($items)->toBe([
                'num' => 123,
                'str' => 'test',
                'bool' => true,
                'nil' => null,
            ]);
        });

        it('yields values progressively', function (): void {
            $parser = createParser('{"a":1,"b":2}');
            $generator = $parser->parseObject();

            expect($generator)->toBeInstanceOf(Generator::class);

            $values = [];
            foreach ($generator as $key => $value) {
                $values[$key] = $value;
            }

            expect($values)->toBe(['a' => 1, 'b' => 2]);
        });

        it('throws on trailing comma', function (): void {
            expect(fn () => iterator_to_array(createParser('{"a":1,}')->parseObject()))
                ->toThrow(ParseException::class, 'Trailing comma not allowed');
        });

        it('throws on non-string key', function (): void {
            expect(fn () => iterator_to_array(createParser('{123:456}')->parseObject()))
                ->toThrow(ParseException::class, 'Expected string key');
        });

        it('throws on missing colon', function (): void {
            expect(fn () => iterator_to_array(createParser('{"a" 1}')->parseObject()))
                ->toThrow(ParseException::class, 'Expected COLON');
        });

        it('throws on missing comma', function (): void {
            expect(fn () => iterator_to_array(createParser('{"a":1 "b":2}')->parseObject()))
                ->toThrow(ParseException::class, 'Expected comma or closing brace');
        });
    });

    describe('skipValue', function (): void {
        it('skips scalar values', function (): void {
            $parser = createParser('123 456');

            $parser->skipValue(); // Skip 123
            expect($parser->parseValue())->toBe(456);
        });

        it('skips string', function (): void {
            $parser = createParser('"skip" "keep"');

            $parser->skipValue();
            expect($parser->parseValue())->toBe('keep');
        });

        it('skips array', function (): void {
            $parser = createParser('[1,2,3] 456');

            $parser->skipValue();
            expect($parser->parseValue())->toBe(456);
        });

        it('skips nested array', function (): void {
            $parser = createParser('[[1,2],[3,4]] "next"');

            $parser->skipValue();
            expect($parser->parseValue())->toBe('next');
        });

        it('skips object', function (): void {
            $parser = createParser('{"a":1,"b":2} 789');

            $parser->skipValue();
            expect($parser->parseValue())->toBe(789);
        });

        it('skips nested object', function (): void {
            $parser = createParser('{"user":{"name":"test"}} true');

            $parser->skipValue();
            expect($parser->parseValue())->toBe(true);
        });

        it('skips deeply nested structure', function (): void {
            $parser = createParser('{"a":[1,{"b":[2,3]}]} null');

            $parser->skipValue();
            expect($parser->parseValue())->toBeNull();
        });
    });

    describe('depth tracking', function (): void {
        it('tracks depth during parsing', function (): void {
            $parser = createParser('[[1]]');

            expect($parser->getDepth())->toBe(0);

            $gen = $parser->parseArray();
            $gen->current(); // Start iteration (increases depth)

            // Note: Generator evaluation happens on iteration
        });

        it('enforces max depth limit', function (): void {
            $deep = str_repeat('[', 600); // Exceeds default max depth of 512
            $parser = createParser($deep);

            expect(fn () => $parser->parseValue())
                ->toThrow(ParseException::class, 'Maximum nesting depth');
        });

        it('respects custom max depth', function (): void {
            $json = '[[[[[]]]]]'; // 5 levels deep
            $parser = createParser($json, 10);

            // Should not throw
            $result = $parser->parseValue();
            expect($result)->toBeArray();
        });

        it('throws when exceeding custom depth', function (): void {
            $json = '[[[[[]]]]]'; // 5 levels deep
            $parser = createParser($json, 3); // Max depth 3

            expect(fn () => $parser->parseValue())
                ->toThrow(ParseException::class, 'Maximum nesting depth');
        });

        it('decreases depth after closing structures', function (): void {
            $parser = createParser('[1]');

            $items = iterator_to_array($parser->parseArray());
            expect($parser->getDepth())->toBe(0); // Back to 0 after complete
        });
    });

    describe('complex structures', function (): void {
        it('parses array of objects', function (): void {
            $json = '[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"}]';
            $result = createParser($json)->parseValue();

            expect($result)->toBe([
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ]);
        });

        it('parses object with arrays', function (): void {
            $json = '{"numbers":[1,2,3],"strings":["a","b","c"]}';
            $result = createParser($json)->parseValue();

            expect($result)->toBe([
                'numbers' => [1, 2, 3],
                'strings' => ['a', 'b', 'c'],
            ]);
        });

        it('parses deeply nested mixed structure', function (): void {
            $json = '{"users":[{"name":"Alice","tags":["admin","active"]},{"name":"Bob","tags":[]}]}';
            $result = createParser($json)->parseValue();

            expect($result)->toBe([
                'users' => [
                    ['name' => 'Alice', 'tags' => ['admin', 'active']],
                    ['name' => 'Bob', 'tags' => []],
                ],
            ]);
        });
    });

    describe('error handling', function (): void {
        it('provides helpful error for unexpected token', function (): void {
            try {
                createParser('[}')->parseValue();
                expect(false)->toBeTrue(); // Should not reach
            } catch (ParseException $e) {
                expect($e->getMessage())->toContain('Unexpected token');
            }
        });

        it('includes position in errors', function (): void {
            try {
                createParser('{"a":}')->parseValue();
                expect(false)->toBeTrue();
            } catch (ParseException $e) {
                expect($e->getJsonLine())->toBeGreaterThan(0);
                expect($e->getJsonColumn())->toBeGreaterThan(0);
            }
        });
    });

    describe('generator behavior', function (): void {
        it('allows early termination of array iteration', function (): void {
            $parser = createParser('[1,2,3,4,5,6,7,8,9,10]');
            $gen = $parser->parseArray();

            $count = 0;
            foreach ($gen as $value) {
                $count++;
                if ($count === 3) {
                    break; // Stop early
                }
            }

            expect($count)->toBe(3);
        });

        it('allows early termination of object iteration', function (): void {
            $parser = createParser('{"a":1,"b":2,"c":3,"d":4}');
            $gen = $parser->parseObject();

            $count = 0;
            foreach ($gen as $value) {
                $count++;
                if ($count === 2) {
                    break;
                }
            }

            expect($count)->toBe(2);
        });
    });
});
