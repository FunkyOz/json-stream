# JsonStream PHP

A high-performance PHP library for streaming JSON parsing, designed to handle arbitrarily large JSON files with constant memory usage.

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE.md)
[![Tests](https://img.shields.io/badge/tests-passing-brightgreen)](tests/)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen)](tests/)

## Why JsonStream?

Traditional JSON parsing with `json_decode()` loads the entire file into memory, which fails on large files. JsonStream uses a streaming approach that processes JSON incrementally, maintaining **constant memory usage** regardless of file size.

### Key Benefits

- **Memory Efficient**: Process multi-GB JSON files with ~100KB memory usage
- **Fast**: Iterator-based API for immediate data access without upfront parsing
- **JSONPath Support**: Filter and extract specific data without loading everything
- **Type Safe**: Full PHP 8.1+ type declarations with 100% type coverage
- **Zero Dependencies**: Pure PHP implementation, no extensions required

## Performance Comparison

| Operation | JsonStream | json_decode() |
|-----------|------------|---------------|
| 1MB file | ~100KB RAM | ~3MB RAM |
| 100MB file | ~100KB RAM | **FAILS** (out of memory) |
| 1GB file | ~100KB RAM | **FAILS** (out of memory) |
| Speed | ~1.5-2x slower | Baseline |

**When to use JsonStream:**
- Files larger than available memory
- Processing large arrays/objects incrementally
- Streaming data from APIs or network
- Need to start processing before full download

**When to use json_decode():**
- Small files (< 10MB) that fit in memory
- Need random access to data structure
- Maximum speed is critical

## Requirements

- PHP 8.1 or higher
- No external dependencies or extensions

## Installation

Install via Composer:

```bash
composer require funkyoz/json-stream
```

## Quick Start

### Reading Large Arrays

Process large JSON arrays without loading everything into memory:

```php
use JsonStream\JsonStream;

// Read a large array incrementally
$reader = JsonStream::read('large-data.json');

foreach ($reader->readArray() as $item) {
    // Process each item immediately
    echo "ID: {$item['id']}, Name: {$item['name']}\n";

    // Memory usage stays constant!
}

$reader->close();
```

## Usage Examples

### 1. JSONPath Filtering

Extract specific data using JSONPath expressions:

```php
use JsonStream\JsonStream;

// Only read items matching the JSONPath expression
$reader = JsonStream::read('data.json', [
    'jsonPath' => '$.users[*]'  // Only extract users array items
]);

foreach ($reader->readArray() as $user) {
    echo "User: {$user['name']}\n";
}

$reader->close();
```

**Supported JSONPath expressions:**
- Simple paths: `$.users[*]`, `$.data.items[*]`
- Array indices: `$.users[0]`, `$.users[5]`
- Array slices: `$.users[0:10]`, `$.users[10:]`
- Wildcards: `$.users[*].name`

### 2. Pagination with Skip/Limit

Process specific ranges of data efficiently:

```php
use JsonStream\JsonStream;

$reader = JsonStream::read('data.json');

// Skip first 100 items, read next 50
foreach ($reader->readArray()->skip(100)->limit(50) as $item) {
    echo "Item: {$item['id']}\n";
}

$reader->close();
```

### 3. Nested Object Iteration

Read nested JSON objects:

```php
use JsonStream\JsonStream;

$reader = JsonStream::read('config.json');

// Iterate over object key-value pairs
foreach ($reader->readObject() as $key => $value) {
    echo "Config '{$key}': {$value}\n";
}

$reader->close();
```

### 4. Reading from String

Parse JSON from a string:

```php
use JsonStream\JsonStream;

$json = '{"users": [{"id": 1}, {"id": 2}]}';
$reader = JsonStream::read($json);

foreach ($reader->readArray('$.users[*]') as $user) {
    echo "User ID: {$user['id']}\n";
}

$reader->close();
```

### 5. Error Handling

Handle parsing errors gracefully:

```php
use JsonStream\JsonStream;
use JsonStream\Exception\ParseException;
use JsonStream\Exception\IOException;

try {
    $reader = JsonStream::read('data.json');

    foreach ($reader->readArray() as $item) {
        // Process item
    }

    $reader->close();

} catch (IOException $e) {
    // File not found or not readable
    echo "IO Error: {$e->getMessage()}\n";

} catch (ParseException $e) {
    // Invalid JSON syntax
    echo "Parse Error: {$e->getMessage()}\n";
    echo "At position: {$e->getPosition()}\n";
}
```

## Configuration Options

### Reader Options

```php
$reader = JsonStream::read('data.json', [
    'bufferSize' => 32768,        // Buffer size in bytes (default: 8192)
    'maxDepth' => 512,            // Maximum nesting depth (default: 512)
    'jsonPath' => '$.data[*]'     // JSONPath filter (default: null)
]);
```

### Buffer Size Recommendations

- Small files (< 1MB): 8KB (default)
- Medium files (1-100MB): 16-32KB
- Large files (> 100MB): 32-64KB
- Very large files (> 1GB): 64KB-1MB

## API Reference

### JsonStream

**Main Method:**
- `JsonStream::read(resource|string $input, array $options = []): StreamReader`
  - Accepts file path, JSON string, or stream resource
  - Automatically detects input type and creates appropriate reader

**Reading Methods (via returned StreamReader):**
- `readArray(): ArrayIterator` - Iterate over array items
- `readObject(): ObjectIterator` - Iterate over object properties
- `readItems(): ItemIterator` - Iterate over items (array or object)
- `readAll(): mixed` - Read entire structure into memory (use with caution)
- `readAllMatches(): array` - Read all items matching JSONPath filter

**Iterator Methods:**
- `skip(int $count): self` - Skip N items
- `limit(int $count): self` - Limit to N items
- `count(): int` - Count total items

**Utility Methods:**
- `close(): void` - Close the reader and free resources

## Testing

Run the complete test suite:

```bash
composer tests
```

This runs:
- Code style checks (Laravel Pint)
- Type coverage checks (100% required)
- Static analysis (PHPStan)
- Unit tests (Pest)
- Integration tests

### Individual Test Commands

```bash
composer tests:unit           # Unit tests only
composer tests:integration    # Integration tests only
composer tests:coverage       # Coverage report (100% required)
composer tests:types          # Static analysis
composer tests:benchmark      # Performance benchmarks
```

## Performance Benchmarks

Run performance benchmarks:

```bash
composer tests:benchmark
```

This tests:
- Memory usage vs file size
- Speed comparison with native JSON functions
- Buffer size impact
- JSONPath filtering performance

## Project Quality

This project maintains strict quality standards:

- **100% Type Coverage**: All code has complete type declarations
- **100% Code Coverage**: All code is covered by tests
- **Zero Style Violations**: Code passes Laravel Pint formatting
- **Zero Static Analysis Errors**: Code passes PHPStan checks at max level

## Known Limitations

- JSONPath support is limited to common patterns (see JSONPath Filtering section)
- Complex filter expressions `$[?(@.price < 10)]` are not yet supported
- Streaming is ~1.5-2x slower than native `json_decode()` for small files

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Write tests for new features
4. Ensure all quality checks pass: `composer tests`
5. Submit a pull request

## Bug Reports

Found a bug? Please [open an issue](https://github.com/funkyoz/json-stream/issues) with:

- PHP version
- Code example reproducing the issue
- Expected vs actual behavior
- Sample JSON data if applicable

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.

## Author

Lorenzo Dessimoni (lorenzo.dessimoni@gmail.com)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and release notes.

## Further Reading

- [Development Guide](DEVELOPMENT.md) - For contributors
- [Examples](examples/) - More usage examples
