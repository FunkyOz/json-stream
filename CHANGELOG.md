# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-03-09

### Added

- **Nested Wildcard Streaming**: Patterns with multiple wildcards like `$.users[*].posts[*]`, `$.matrix[*][*]`, and `$.a[*].b[*].c[*]` now use true streaming instead of falling back to PathFilter which buffered the entire JSON structure in memory
- **PathEvaluator Enhancements**: Added `getAllRemainingSegments()`, `hasNestedWildcardsRemaining()`, and `walkValueWithWildcards()` methods for recursive wildcard expansion during streaming
- **Numeric String Bracket Notation**: `$["1"]` and `$['1']` now match array index `1` as well as object property `"1"`, per JSONPath RFC 9535 §2.3.1

### Security

- **UTF-16 Lone Surrogate Validation**: Lone high/low UTF-16 surrogates in JSON strings now throw `ParseException` instead of producing invalid UTF-8 output
- **Integer Overflow Handling**: Numbers exceeding `PHP_INT_MAX` are now correctly parsed as floats instead of silently overflowing
- **ObjectIterator Cache Limits**: Added bounded FIFO cache (default 1000 entries) to prevent unbounded memory growth from very large JSON objects
- **PathFilter Depth Tracking**: Enforces configurable depth limit during path traversal to prevent stack overflow from deeply nested data
- **ReDoS Prevention**: Filter expressions are limited to 1000 characters to prevent regular expression denial-of-service attacks

### Fixed

- **Stream Position in Fluent Methods**: `withPath()`, `withBufferSize()`, and `withMaxDepth()` now correctly reset seekable streams after partial reads; throws `IOException` for non-seekable streams
- **IOException File Path Consistency**: `StreamReader::fromFile()` now consistently sets the file path on all `IOException` instances via `setFilePath()`
- **Negative Index Validation**: `ArraySliceSegment` now throws `PathException` for negative indices which are unsupported in streaming mode

### Changed

- **Merged BufferManager into Lexer**: Eliminated `BufferManager` as a standalone class by absorbing all buffer I/O state and methods into `Lexer`. This removes all inter-object method call overhead from the tokenization hot path. `StreamReader::getBuffer()` replaced with `StreamReader::getLexer()`
- **PathExpression**: `canUseSimpleStreaming()` now returns `true` for patterns with multiple wildcards, enabling streaming for nested wildcard patterns
- **PropertySegment**: `matches()` now supports numeric string properties matching integer array indices (e.g., `$["0"]` matches array index `0`)
- **Parser**: Enhanced `streamFromArray()` to detect nested wildcards and use recursive extraction via `walkValueWithWildcards()`
- **PathExpression Analysis Caching**: `hasRecursive()` and `canUseSimpleStreaming()` results are now cached at construction time for better performance
- **Optimized `isAssociativeArray()`**: Replaced custom method with inline `array_is_list()` check in `PathFilter`
- **PHPStan Ignore Comments**: Added descriptive explanations to all `@phpstan-ignore-next-line` annotations
- **Removed Unused Config Constants**: Removed `MODE_RELAXED`, `ENCODE_NUMERIC_CHECK`, `ENCODE_PRETTY_PRINT`, `ENCODE_UNESCAPED_SLASHES`, and `ENCODE_UNESCAPED_UNICODE` constants that were reserved for unimplemented features

### Performance

- **Tokenization Hot Path**: Merged `BufferManager` into `Lexer` to eliminate ~2-4 million per-byte cross-object method calls for a 1MB file. Whitespace skipping now uses direct `$this->buffer[$this->bufferPosition]` access instead of `peek()`/`readByte()` method calls
- **Memory**: Nested wildcard patterns like `$.users[*].posts[*]` now use O(single outer element) memory instead of O(entire JSON)
- **Streaming**: Arbitrary depth nesting (4+ wildcard levels) is supported without buffering

## [1.1.0] - 2025-12-17

### Added

- **Complex Pattern Streaming**: Extended JSONPath streaming capability to handle sophisticated patterns without buffering entire arrays in memory
  - Property extraction after wildcards: `$.users[*].name` streams individual property values
  - Array index with property access: `$.items[0].name` extracts nested properties with streaming
  - Filter expressions with property extraction: `$.items[?price > 100].name` leverages streaming when possible
- **PathEvaluator Enhancements**: Added `shouldExtractFromValue()` and `walkValue()` methods for partial path matching and nested value extraction
- **Sequential Result Indexing**: All streamed results maintain correct key ordering across operations

### Changed

- **PathExpression**: Updated `canUseSimpleStreaming()` to recognize complex streamable patterns
- **Parser**: Enhanced parsing to support complex pattern detection and evaluation

### Performance

- **Memory**: Complex JSONPath patterns now use constant memory instead of buffering entire arrays
- **Scalability**: Can process very large JSON arrays with complex patterns efficiently
- **Compatibility**: Maintains performance parity with buffered approach while reducing memory footprint

## [1.0.0] - 2025-12-05

Initial release of JsonStream PHP - a high-performance streaming JSON parser for PHP 8.1+.

### Added

- **StreamReader** with support for reading from files, strings, and streams
- **Iterator Types**:
  - ArrayIterator for streaming array iteration
  - ObjectIterator for streaming object iteration
  - ItemIterator for flexible iteration over arrays or objects
- **JSONPath Support** for filtering and extracting data with expressions like `$.users[*]`, `$.data.items[0:10]`
- **Pagination** with `skip()` and `limit()` operations
- **Error Handling** with custom exceptions (IOException, ParseException, PathException)
- **Performance Benchmarks** suite

### Features

- **Constant Memory Usage**: Process multi-GB JSON files with ~100KB memory footprint
- **Streaming Evaluation**: Start processing data before full file is loaded
- **Configurable Buffers**: Tune performance with buffer size options (8KB - 1MB)
- **Deep Nesting Support**: Handle deeply nested structures with configurable max depth
- **Zero Dependencies**: Pure PHP implementation, no extensions required

### Performance

- ~1.5-2x slower than `json_decode()` for small files
- Constant ~100KB memory vs unbounded memory for `json_decode()`
- Successfully processes multi-GB files that would fail with native functions
