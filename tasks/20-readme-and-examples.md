---
title: README and Usage Examples
status: done
priority: Medium
description: Create comprehensive README.md with quick start guide, usage examples, and links to API documentation.
---

## Objectives
- Create clear, concise README
- Provide quick start guide
- Include practical usage examples
- Link to API documentation
- Show performance benefits
- Include installation instructions

## README Structure

### 1. Header
- Package name and tagline
- Badges (PHP version, license, tests status)
- Brief description

### 2. Features
- Memory efficient streaming
- Iterator-based API
- JSONPath support
- Bi-directional (read/write)
- Type safety (PHP 8.1+)

### 3. Installation
```bash
composer require dessimoney/json-stream-php
```

### 4. Quick Start
- Simple reading example
- Simple writing example
- Show memory benefits

### 5. Usage Examples
Based on API_SIGNATURE.md Examples section:
- Reading large arrays (Example 1)
- JSONPath filtering (Example 2)
- Pagination with skip/limit (Example 3)
- Writing large arrays (Example 4)
- Nested structures (Example 5)
- Stream processing (Example 6)
- Error handling (Example 7)

### 6. Documentation Links
- Link to API_SIGNATURE.md for full API reference
- Link to examples directory
- Link to performance benchmarks

### 7. Performance
- Table showing JsonStream vs json_decode
- Memory usage comparison
- When to use streaming vs readAll()

### 8. Requirements
- PHP >= 8.1
- No external dependencies

### 9. Testing
```bash
composer tests
```

### 10. License
- MIT License

### 11. Contributing
- Issue reporting
- Pull requests welcome

## Additional Files

### examples/ Directory
Create practical example files:
- `examples/01-read-large-file.php`
- `examples/02-jsonpath-filtering.php`
- `examples/03-pagination.php`
- `examples/04-write-large-file.php`
- `examples/05-nested-structures.php`
- `examples/06-error-handling.php`
- `examples/data/` - Sample JSON files

### CHANGELOG.md
- Version history
- Breaking changes
- New features

## Dependencies
- All implementation tasks complete

## Estimated Complexity
**Low** - Documentation writing

## Acceptance Criteria
- [x] README.md created with all sections
- [x] Quick start examples work
- [x] All usage examples tested and working
- [x] examples/ directory created with working scripts
- [x] Sample data files included
- [x] CHANGELOG.md created
- [x] Links to API docs working
- [x] Badges added (if applicable)
- [x] Clear and professional presentation
