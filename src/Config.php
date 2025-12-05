<?php

declare(strict_types=1);

namespace JsonStream;

/**
 * Configuration constants for JsonStream package
 *
 * Provides centralized configuration values for buffer sizes,
 * depth limits, parser modes, and encoding options.
 */
final class Config
{
    /**
     * Minimum buffer size in bytes (1 KB)
     *
     * The smallest allowed buffer size for I/O operations.
     * Smaller buffers reduce memory usage but increase I/O overhead.
     */
    public const MIN_BUFFER_SIZE = 1024;

    /**
     * Default buffer size in bytes (8 KB)
     *
     * Balanced buffer size providing good performance
     * for most use cases with reasonable memory usage.
     */
    public const DEFAULT_BUFFER_SIZE = 8192;

    /**
     * Maximum buffer size in bytes (1 MB)
     *
     * The largest allowed buffer size for I/O operations.
     * Larger buffers reduce I/O overhead but increase memory usage.
     */
    public const MAX_BUFFER_SIZE = 1048576;

    /**
     * Minimum nesting depth (1 level)
     *
     * The minimum allowed depth for JSON structure nesting.
     */
    public const MIN_DEPTH = 1;

    /**
     * Default maximum nesting depth (512 levels)
     *
     * Balanced depth limit that handles most real-world JSON
     * while preventing stack overflow from malicious input.
     */
    public const DEFAULT_MAX_DEPTH = 512;

    /**
     * Maximum allowed nesting depth (4096 levels)
     *
     * The absolute maximum depth for deeply nested structures.
     * Use with caution as very deep structures may impact performance.
     */
    public const MAX_DEPTH = 4096;

    /**
     * Strict parser mode (RFC 8259 compliance)
     *
     * Enforces strict JSON parsing according to RFC 8259:
     * - No trailing commas
     * - No comments
     * - Strict number format
     * - Strict string escaping
     */
    public const MODE_STRICT = 1;

    /**
     * Relaxed parser mode
     *
     * Allows common JSON extensions:
     * - Trailing commas in arrays and objects
     * - Single-line and multi-line comments
     * - Unquoted object keys (when safe)
     * - Single-quoted strings
     *
     * Note: This mode is reserved for future implementation.
     */
    public const MODE_RELAXED = 2;

    /**
     * Encoding option: Numeric check
     *
     * Encodes numeric strings as numbers in JSON output.
     *
     * Reserved for future implementation.
     */
    public const ENCODE_NUMERIC_CHECK = 1;

    /**
     * Encoding option: Pretty print
     *
     * Use whitespace and indentation for readable JSON output.
     *
     * Reserved for future implementation (currently handled via withPrettyPrint()).
     */
    public const ENCODE_PRETTY_PRINT = 2;

    /**
     * Encoding option: Unescaped slashes
     *
     * Don't escape forward slashes (/) in strings.
     *
     * Reserved for future implementation.
     */
    public const ENCODE_UNESCAPED_SLASHES = 4;

    /**
     * Encoding option: Unescaped unicode
     *
     * Output unicode characters directly instead of \uXXXX escapes.
     *
     * Reserved for future implementation.
     */
    public const ENCODE_UNESCAPED_UNICODE = 8;

    private function __construct()
    {
        // Private constructor prevents instantiation
    }
}
