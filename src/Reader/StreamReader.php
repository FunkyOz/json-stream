<?php

declare(strict_types=1);

namespace JsonStream\Reader;

use JsonStream\Config;
use JsonStream\Exception\IOException;
use JsonStream\Internal\BufferManager;
use JsonStream\Internal\JsonPath\PathEvaluator;
use JsonStream\Internal\JsonPath\PathExpression;
use JsonStream\Internal\JsonPath\PathFilter;
use JsonStream\Internal\JsonPath\PathParser;
use JsonStream\Internal\Lexer;
use JsonStream\Internal\Parser;

/**
 * Main entry point for reading and parsing JSON streams
 *
 * Provides factory methods to create readers from various sources
 * and methods to parse JSON data into PHP values or iterators.
 */
class StreamReader
{
    private BufferManager $buffer;

    private Lexer $lexer;

    private Parser $parser;

    private int $bufferSize;

    private int $maxDepth;

    private ?string $jsonPath;

    private ?PathEvaluator $pathEvaluator = null;

    /** @var resource|null */
    private $stream = null;

    private bool $ownsStream = false;

    private int $itemsProcessed = 0;

    /**
     * @param  resource  $stream
     */
    private function __construct(
        mixed $stream,
        int $bufferSize = Config::DEFAULT_BUFFER_SIZE,
        int $maxDepth = Config::DEFAULT_MAX_DEPTH,
        ?string $jsonPath = null,
        bool $ownsStream = false
    ) {
        $this->stream = $stream;
        $this->bufferSize = $bufferSize;
        $this->maxDepth = $maxDepth;
        $this->jsonPath = $jsonPath;
        $this->ownsStream = $ownsStream;

        $this->buffer = new BufferManager($stream, $bufferSize);
        $this->lexer = new Lexer($this->buffer);

        // Parse JSONPath expression if provided
        if ($jsonPath !== null) {
            $parser = new PathParser();
            $expression = $parser->parse($jsonPath);
            $this->pathEvaluator = new PathEvaluator($expression);
        }

        // Create parser with PathEvaluator for streaming JSONPath evaluation
        $this->parser = new Parser($this->lexer, $maxDepth, $this->pathEvaluator);
    }

    /**
     * Create reader from a file path
     *
     * @param  array{bufferSize?: int, maxDepth?: int, jsonPath?: string}  $options
     *
     * @throws IOException If file cannot be opened
     */
    public static function fromFile(string $filePath, array $options = []): self
    {
        if (! file_exists($filePath)) {
            throw new IOException("File not found: {$filePath}");
        }

        if (! is_readable($filePath)) {
            throw new IOException("File is not readable: {$filePath}");
        }

        $stream = @fopen($filePath, 'r');
        if ($stream === false) {
            throw new IOException("Failed to open file: {$filePath}");
        }

        return new self(
            $stream,
            $options['bufferSize'] ?? Config::DEFAULT_BUFFER_SIZE,
            $options['maxDepth'] ?? Config::DEFAULT_MAX_DEPTH,
            $options['jsonPath'] ?? null,
            true // We own this stream
        );
    }

    /**
     * Create reader from a stream resource
     *
     * @param  resource  $stream
     * @param  array{bufferSize?: int, maxDepth?: int, jsonPath?: string}  $options
     *
     * @throws IOException If stream is invalid
     */
    public static function fromStream(mixed $stream, array $options = []): self
    {
        if (! is_resource($stream)) {
            throw new IOException('Invalid stream resource');
        }

        return new self(
            $stream,
            $options['bufferSize'] ?? Config::DEFAULT_BUFFER_SIZE,
            $options['maxDepth'] ?? Config::DEFAULT_MAX_DEPTH,
            $options['jsonPath'] ?? null,
            false // We don't own this stream
        );
    }

    /**
     * Create reader from a JSON string
     *
     * @param  array{bufferSize?: int, maxDepth?: int, jsonPath?: string}  $options
     */
    public static function fromString(string $jsonString, array $options = []): self
    {
        $stream = fopen('php://memory', 'r+');
        assert($stream !== false);

        fwrite($stream, $jsonString);
        rewind($stream);

        return new self(
            $stream,
            $options['bufferSize'] ?? Config::DEFAULT_BUFFER_SIZE,
            $options['maxDepth'] ?? Config::DEFAULT_MAX_DEPTH,
            $options['jsonPath'] ?? null,
            true // We own this stream
        );
    }

    /**
     * Set JSONPath filter expression (fluent interface)
     */
    public function withPath(string $path): self
    {
        // Transfer ownership if we own the stream
        $newOwnsStream = $this->ownsStream;
        if ($this->ownsStream) {
            $this->ownsStream = false; // Transfer ownership to new instance
        }

        assert($this->stream !== null);

        return new self(
            $this->stream,
            $this->bufferSize,
            $this->maxDepth,
            $path,
            $newOwnsStream
        );
    }

    /**
     * Set buffer size (fluent interface)
     */
    public function withBufferSize(int $size): self
    {
        // Transfer ownership if we own the stream
        $newOwnsStream = $this->ownsStream;
        if ($this->ownsStream) {
            $this->ownsStream = false; // Transfer ownership to new instance
        }

        assert($this->stream !== null);

        return new self(
            $this->stream,
            $size,
            $this->maxDepth,
            $this->jsonPath,
            $newOwnsStream
        );
    }

    /**
     * Set maximum nesting depth (fluent interface)
     */
    public function withMaxDepth(int $depth): self
    {
        // Transfer ownership if we own the stream
        $newOwnsStream = $this->ownsStream;
        if ($this->ownsStream) {
            $this->ownsStream = false; // Transfer ownership to new instance
        }

        assert($this->stream !== null);

        return new self(
            $this->stream,
            $this->bufferSize,
            $depth,
            $this->jsonPath,
            $newOwnsStream
        );
    }

    /**
     * Read JSON array and return iterator
     */
    public function readArray(): ArrayIterator
    {
        return new ArrayIterator($this);
    }

    /**
     * Read JSON object and return iterator
     */
    public function readObject(): ObjectIterator
    {
        return new ObjectIterator($this);
    }

    /**
     * Read any JSON structure and return iterator
     *
     * If a JSONPath filter is set via withPath(), only matching items will be yielded.
     */
    public function readItems(): ItemIterator
    {
        return new ItemIterator($this);
    }

    /**
     * Read and parse entire JSON into memory
     *
     * If a JSONPath filter is set, returns only the first matching value.
     */
    public function readAll(): mixed
    {
        $value = $this->parser->parseValue();
        $this->itemsProcessed++;

        // Apply path filtering if set
        if ($this->pathEvaluator !== null) {
            $filter = new PathFilter($this->pathEvaluator);
            $matches = $filter->extract($value);

            return empty($matches) ? null : $matches[0];
        }

        return $value;
    }

    /**
     * Read and parse entire JSON, returning all matching values
     *
     * Internal method used by iterators when path filtering is enabled.
     *
     * @return array<mixed> All matching values
     *
     * @internal
     */
    public function readAllMatches(): array
    {
        $value = $this->parser->parseValue();
        $this->itemsProcessed++;

        // Apply path filtering if set
        if ($this->pathEvaluator !== null) {
            $filter = new PathFilter($this->pathEvaluator);

            return $filter->extract($value);
        }

        // No filtering - wrap in array for consistency
        return [$value];
    }

    /**
     * Get statistics about parsing
     *
     * @return array{bytesRead: int, itemsProcessed: int, bufferSize: int, maxDepth: int}
     */
    public function getStats(): array
    {
        return [
            'bytesRead' => $this->buffer->getTotalBytesRead(),
            'itemsProcessed' => $this->itemsProcessed,
            'bufferSize' => $this->bufferSize,
            'maxDepth' => $this->maxDepth,
        ];
    }

    /**
     * Close the stream resource
     */
    public function close(): void
    {
        if ($this->stream !== null && $this->ownsStream && is_resource($this->stream)) {
            fclose($this->stream);
            $this->stream = null;
        }
    }

    /**
     * Get internal parser (for iterators)
     *
     * @internal
     */
    public function getParser(): Parser
    {
        return $this->parser;
    }

    /**
     * Increment items processed counter (for iterators)
     *
     * @internal
     */
    public function incrementItemsProcessed(): void
    {
        $this->itemsProcessed++;
    }

    /**
     * Get buffer manager (for iterators)
     *
     * @internal
     */
    public function getBuffer(): BufferManager
    {
        return $this->buffer;
    }

    /**
     * Get path evaluator (for iterators)
     *
     * @internal
     */
    public function getPathEvaluator(): ?PathEvaluator
    {
        return $this->pathEvaluator;
    }

    /**
     * Check if JSONPath filtering is enabled
     *
     * @internal
     */
    public function hasPathFilter(): bool
    {
        return $this->pathEvaluator !== null;
    }

    /**
     * Get the PathExpression for pattern analysis
     *
     * @internal Used by iterators for streaming optimization
     */
    public function getPathExpression(): ?PathExpression
    {
        return $this->pathEvaluator?->getExpression();
    }

    /**
     * Automatic cleanup
     */
    public function __destruct()
    {
        $this->close();
    }
}
