<?php

declare(strict_types=1);

use JsonStream\Exception\ParseException;
use JsonStream\JsonStream;

describe('JsonStream', function (): void {
    it('create a StreamReader using resource', function (): void {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, '{"test": "value"}');
        rewind($stream);
        JsonStream::read($stream)->readAll();
        expect(true)->toBeTrue();
        fclose($stream);
    });

    it('create a StreamReader using filepath', function (): void {
        $tempFilepath = tempnam(sys_get_temp_dir(), 'json_test_');
        file_put_contents($tempFilepath, '{"test": "value"}');
        JsonStream::read($tempFilepath)->readAll();
        expect(true)->toBeTrue();
        unlink($tempFilepath);
    });

    it('create a StreamReader using json string', function (): void {
        JsonStream::read('{"test": "value"}')->readAll();
        expect(true)->toBeTrue();
    });

    it('throw an invalid argument exception', function (mixed $input): void {
        expect(fn () => JsonStream::read($input)->readAll())
            ->toThrow(InvalidArgumentException::class, 'Input must be a valid resource, filepath or JSON string');
    })->with([
        [null],
        [1],
        [true],
        [new stdClass],
    ]);

    it('throw an parse exception', function (mixed $input): void {
        expect(fn () => JsonStream::read($input)->readAll())->toThrow(ParseException::class);
    })->with([
        [''], // Empty string
        ['invalid'], // Invalid json string
        ['{"test":"invalid}'], // Invalid json string, but similar to json
    ]);
});
