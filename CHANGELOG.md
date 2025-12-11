# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
