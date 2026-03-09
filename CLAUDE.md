# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

`funkyoz/json-stream` — A streaming JSON parser for PHP 8.1+ with zero dependencies. Parses large JSON files with constant memory using generators, supports JSONPath filtering, and provides iterator-based pagination.

## Commands

All commands run inside the Docker container. Start with `docker compose up -d --build`, then prefix commands with `docker compose exec php`.

```bash
# Build and start container
docker compose up -d --build

# Run all checks (lint, types, refactor, unit, integration)
docker compose exec php composer tests

# Individual checks
docker compose exec php composer tests:unit             # Unit tests (Pest)
docker compose exec php composer tests:integration      # Integration tests (Pest)
docker compose exec php composer tests:lint             # Code style check (Laravel Pint, PSR-12)
docker compose exec php composer tests:types            # PHPStan static analysis (max level)
docker compose exec php composer tests:type-coverage    # Verify 100% type coverage
docker compose exec php composer tests:refactor         # Rector dry-run
docker compose exec php composer tests:coverage         # Coverage report (100% required)

# Run a single test file
docker compose exec php ./vendor/bin/pest tests/Unit/Reader/StreamReaderTest.php

# Run a single test by name
docker compose exec php ./vendor/bin/pest --filter="test name here"

# Fix code style
docker compose exec php composer lint

# Apply refactoring
docker compose exec php composer refactor

# Install dependencies
docker compose exec php composer install
```

## Architecture

**Entry point:** `JsonStream::read($input)` returns a `StreamReader` with fluent API.

**Streaming pipeline:** `BufferManager` → `Lexer` (tokenizer) → `Parser` (generator-based) → `Iterator` (public API)

**Namespace layout:**
- `JsonStream\Reader\` — Public API: `StreamReader`, `ArrayIterator`, `ObjectIterator`, `ItemIterator`
- `JsonStream\Internal\` — Implementation: `Parser`, `Lexer`, `BufferManager`, `Token`, `TokenType`
- `JsonStream\Internal\JsonPath\` — JSONPath support: `PathParser`, `PathEvaluator`, segment classes
- `JsonStream\Exception\` — `JsonStreamException` base, `ParseException`, `IOException`, `PathException`

**Key patterns:**
- Facade (`JsonStream`) → Factory methods (`StreamReader::fromFile/fromStream/fromString`) → Fluent builder (`withPath`, `withBufferSize`, `withMaxDepth`) → Iterators
- Generator-based parsing for constant memory usage
- JSONPath expressions parsed into segment chains (`RootSegment`, `PropertySegment`, `ArrayIndexSegment`, `ArraySliceSegment`, `WildcardSegment`, `FilterSegment`)
- Iterators support `skip()` and `limit()` for pagination

## Code Standards

- `declare(strict_types=1)` on all files
- 100% type coverage and 100% code coverage enforced
- PHPStan at max level
- PSR-12 style via Laravel Pint
- Internal classes marked `@internal`
- Config constants in `Config.php` (buffer sizes, depth limits)

## CI

GitHub Actions runs on PHP 8.1–8.5 across Ubuntu, macOS, Windows. Jobs: lint, refactor, types, tests.
