---
title: Buffer Manager
status: done
priority: Critical
description: Implement an internal buffer manager for efficient I/O operations. This component handles reading from streams in chunks and managing the internal buffer state.
---

## Objectives
- Create BufferManager class for stream reading
- Implement configurable buffer sizes
- Handle EOF detection
- Provide byte-by-byte and chunk reading
- Optimize for minimal memory allocations
- Support seekable and non-seekable streams

## Deliverables
1. `src/Internal/BufferManager.php` with:
   - Constructor accepting stream resource and buffer size
   - `readByte(): string|null` - Read single byte
   - `readChunk(int $size): string` - Read multiple bytes
   - `peek(int $offset = 0): string|null` - Look ahead without consuming
   - `isEof(): bool` - Check if at end of stream
   - `getPosition(): int` - Current byte position
   - `reset(): void` - Reset buffer (for seekable streams)
   - Proper resource cleanup in destructor

## Technical Considerations
- Use `fread()` for chunk reading
- Maintain internal buffer and position pointers
- Handle buffer refills automatically
- Track line and column for error reporting
- Memory efficiency - reuse buffer space

## Dependencies
- Task 02: Config Constants (for buffer size limits)
- Task 03: Exception Classes (for IOException)

## Estimated Complexity
**Medium** - Requires careful state management

## Acceptance Criteria
- [x] BufferManager class implemented
- [x] All read methods work correctly
- [x] Handles EOF properly
- [x] Works with both seekable and non-seekable streams
- [x] Efficient memory usage (reuses buffer)
- [x] Position tracking accurate
- [x] Unit tests with 100% coverage
- [x] Memory leak tests pass
