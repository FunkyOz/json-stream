<?php

declare(strict_types=1);

namespace JsonStream;

use InvalidArgumentException;
use JsonStream\Reader\StreamReader;

final class JsonStream
{
    /**
     * @param  resource|string  $input
     * @param  array{bufferSize?: int, maxDepth?: int, jsonPath?: string}  $options
     *
     * @throws Exception\IOException
     */
    public static function read(mixed $input, array $options = []): StreamReader
    {
        if (is_resource($input)) {
            return StreamReader::fromStream($input, $options);
        } elseif (is_string($input)) {
            if (file_exists($input)) {
                return StreamReader::fromFile($input, $options);
            }

            return StreamReader::fromString($input, $options);
        } else {
            throw new InvalidArgumentException('Input must be a valid resource, filepath or JSON string');
        }
    }
}
