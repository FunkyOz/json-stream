<?php

use JsonStream\Exception\PathException;
use JsonStream\Internal\JsonPath\ArrayIndexSegment;
use JsonStream\Internal\JsonPath\ArraySliceSegment;
use JsonStream\Internal\JsonPath\FilterSegment;
use JsonStream\Internal\JsonPath\PathParser;
use JsonStream\Internal\JsonPath\PropertySegment;
use JsonStream\Internal\JsonPath\RootSegment;
use JsonStream\Internal\JsonPath\WildcardSegment;

describe('PathParser', function (): void {
    it('parses root path', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$');

        expect($expression->getSegmentCount())->toBe(1);
        expect($expression->getSegment(0))->toBeInstanceOf(RootSegment::class);
    });

    it('parses simple property path', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users');

        expect($expression->getSegmentCount())->toBe(2);
        expect($expression->getSegment(0))->toBeInstanceOf(RootSegment::class);
        expect($expression->getSegment(1))->toBeInstanceOf(PropertySegment::class);
        expect($expression->getSegment(1)->getProperty())->toBe('users');
    });

    it('parses nested property path', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.store.book.title');

        expect($expression->getSegmentCount())->toBe(4);
        expect($expression->getSegment(1)->getProperty())->toBe('store');
        expect($expression->getSegment(2)->getProperty())->toBe('book');
        expect($expression->getSegment(3)->getProperty())->toBe('title');
    });

    it('parses array index', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[0]');

        expect($expression->getSegmentCount())->toBe(3);
        expect($expression->getSegment(2))->toBeInstanceOf(ArrayIndexSegment::class);
        expect($expression->getSegment(2)->getIndex())->toBe(0);
    });

    it('parses negative array index', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[-1]');

        expect($expression->getSegmentCount())->toBe(3);
        expect($expression->getSegment(2))->toBeInstanceOf(ArrayIndexSegment::class);
        expect($expression->getSegment(2)->getIndex())->toBe(-1);
    });

    it('parses array slice', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[0:5]');

        expect($expression->getSegmentCount())->toBe(3);
        $segment = $expression->getSegment(2);
        expect($segment)->toBeInstanceOf(ArraySliceSegment::class);
        expect($segment->getStart())->toBe(0);
        expect($segment->getEnd())->toBe(5);
        expect($segment->getStep())->toBe(1);
    });

    it('parses array slice with step', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[::2]');

        expect($expression->getSegmentCount())->toBe(3);
        $segment = $expression->getSegment(2);
        expect($segment)->toBeInstanceOf(ArraySliceSegment::class);
        expect($segment->getStart())->toBeNull();
        expect($segment->getEnd())->toBeNull();
        expect($segment->getStep())->toBe(2);
    });

    it('parses wildcard in bracket notation', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[*]');

        expect($expression->getSegmentCount())->toBe(3);
        expect($expression->getSegment(2))->toBeInstanceOf(WildcardSegment::class);
    });

    it('parses wildcard in dot notation', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users.*');

        expect($expression->getSegmentCount())->toBe(3);
        expect($expression->getSegment(2))->toBeInstanceOf(WildcardSegment::class);
    });

    it('parses recursive descent', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$..email');

        expect($expression->getSegmentCount())->toBe(2);
        $segment = $expression->getSegment(1);
        expect($segment)->toBeInstanceOf(PropertySegment::class);
        expect($segment->isRecursive())->toBeTrue();
    });

    it('parses bracket notation with quoted string', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$["first-name"]');

        expect($expression->getSegmentCount())->toBe(2);
        expect($expression->getSegment(1))->toBeInstanceOf(PropertySegment::class);
        expect($expression->getSegment(1)->getProperty())->toBe('first-name');
    });

    it('parses filter expression', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.users[?(@.age > 18)]');

        expect($expression->getSegmentCount())->toBe(3);
        $segment = $expression->getSegment(2);
        expect($segment)->toBeInstanceOf(FilterSegment::class);
        expect($segment->getExpression())->toBe('@.age > 18');
    });

    it('parses complex path', function (): void {
        $parser = new PathParser();
        $expression = $parser->parse('$.store.book[*].author');

        expect($expression->getSegmentCount())->toBe(5);
        expect($expression->getSegment(1)->getProperty())->toBe('store');
        expect($expression->getSegment(2)->getProperty())->toBe('book');
        expect($expression->getSegment(3))->toBeInstanceOf(WildcardSegment::class);
        expect($expression->getSegment(4)->getProperty())->toBe('author');
    });

    it('throws on empty path', function (): void {
        $parser = new PathParser();
        expect(fn () => $parser->parse(''))->toThrow(PathException::class);
    });

    it('throws on path without root', function (): void {
        $parser = new PathParser();
        expect(fn () => $parser->parse('users'))->toThrow(PathException::class);
    });

    it('throws on unclosed bracket', function (): void {
        $parser = new PathParser();
        expect(fn () => $parser->parse('$.users[0'))->toThrow(PathException::class);
    });

    it('throws on unterminated string', function (): void {
        $parser = new PathParser();
        expect(fn () => $parser->parse('$["users'))->toThrow(PathException::class);
    });
});
