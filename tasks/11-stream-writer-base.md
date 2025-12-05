---
title: StreamWriter Base Implementation
status: done
priority: High
description: Implement the StreamWriter class, which is the main entry point for writing and generating JSON streams.
---

## Objectives
- Implement all factory methods (toFile, toStream, toString)
- Implement configuration methods (fluent interface)
- Implement writing methods (beginArray, beginObject, write)
- Handle buffering and flushing
- Support pretty printing with indentation
- Proper resource management

## Deliverables
1. `src/Writer/StreamWriter.php` with:

   **Factory Methods:**
   - `static toFile(string $filePath, array $options = []): StreamWriter`
   - `static toStream(resource $stream, array $options = []): StreamWriter`
   - `static toString(array $options = []): StreamWriter`

   **Configuration Methods:**
   - `withPrettyPrint(bool $enable = true, string $indent = "  "): StreamWriter`

   **Writing Methods:**
   - `beginArray(): ArrayWriter`
   - `beginObject(): ObjectWriter`
   - `write(mixed $value): StreamWriter` - Write complete value
   - `flush(): StreamWriter` - Flush buffer
   - `getString(): string` - Get generated JSON (toString mode only)

   **Utility Methods:**
   - `getStats(): array` - Writing statistics
   - `close(): void` - Close and cleanup
   - `__destruct()` - Automatic cleanup

## API Reference
See API_SIGNATURE.md lines 798-1037

## Technical Considerations
- Internal write buffer for efficiency
- Track indentation depth for pretty printing
- Track current context (root, array, object)
- Handle comma placement between elements
- Proper JSON encoding of values
- String memory buffer for toString mode

## Dependencies
- Task 02: Config Constants
- Task 03: Exception Classes
- Task 12, 13: Writer classes (for full functionality)

## Estimated Complexity
**Medium** - Integration and buffering logic

## Acceptance Criteria
- [x] All factory methods implemented
- [x] Configuration methods work (fluent interface)
- [x] beginArray/beginObject return writer contexts
- [x] write() encodes values correctly
- [x] Pretty printing works with proper indentation
- [x] Buffer flushing works correctly
- [x] getString() works in toString mode
- [x] Proper resource cleanup
- [x] Unit tests for all methods
- [x] Integration tests with file writing
