---
title: StreamReader Base Implementation
status: done
priority: High
description: Implement the StreamReader class, which is the main entry point for reading and parsing JSON streams.
---

## Objectives
- Implement all factory methods (fromFile, fromStream, fromString)
- Implement configuration methods (fluent interface)
- Implement parsing method stubs (will delegate to iterators)
- Implement utility methods (getStats, close)
- Proper resource management

## Deliverables
1. `src/Reader/StreamReader.php` with:

   **Factory Methods:**
   - `static fromFile(string $filePath, array $options = []): StreamReader`
   - `static fromStream(resource $stream, array $options = []): StreamReader`
   - `static fromString(string $jsonString, array $options = []): StreamReader`

   **Configuration Methods (Fluent):**
   - `withPath(string $path): StreamReader`
   - `withBufferSize(int $size): StreamReader`
   - `withMaxDepth(int $depth): StreamReader`

   **Parsing Methods:**
   - `readArray(): ArrayIterator`
   - `readObject(): ObjectIterator`
   - `readItems(): ItemIterator`
   - `readAll(): mixed`

   **Utility Methods:**
   - `getStats(): array`
   - `close(): void`
   - `__destruct()` - automatic cleanup

## API Reference
See API_SIGNATURE.md lines 163-433

## Technical Considerations
- Store options in instance variables
- Create BufferManager, Lexer, Parser internally
- Handle file/stream opening and validation
- Resource cleanup (close file handles)
- Stats tracking (bytes read, items processed)

## Dependencies
- Task 03: Exception Classes
- Task 04: Buffer Manager
- Task 05: Lexer
- Task 06: Parser
- Task 08, 09, 10: Iterator classes (for full functionality)

## Estimated Complexity
**Medium** - Integration of multiple components

## Acceptance Criteria
- [x] All factory methods implemented
- [x] All configuration methods work (fluent interface)
- [x] Parsing methods return appropriate iterators
- [x] readAll() loads complete JSON into memory
- [x] getStats() returns accurate information
- [x] Proper resource cleanup
- [x] Unit tests for all methods
- [x] Integration tests with real files
