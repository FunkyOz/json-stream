---
title: Fix Stream Position Not Reset in Fluent Interface Methods
status: todo
priority: Critical
description: Fix stream position handling when using fluent interface methods to prevent data loss
---

## Objectives
- Ensure fluent methods don't cause data loss when stream has been partially consumed
- Reset stream position when creating new StreamReader instances via fluent methods
- Prevent parse errors caused by missing already-consumed bytes

## Deliverables
1. Modified `withPath()`, `withBufferSize()`, and `withMaxDepth()` methods in `StreamReader` to handle stream position correctly
2. Add validation to detect if stream has been partially consumed
3. Reset stream position when possible (for seekable streams)
4. Throw exception or document limitation for non-seekable streams
5. Unit tests covering edge cases of fluent method chaining after partial reads

## Technical Details

**Location:** `src/Reader/StreamReader.php:155-216`

**Current Issue:**
```php
public function withPath(string $path): self
{
    // Creates new StreamReader with existing $this->stream
    // But buffer may have already consumed data
    return new self(
        $this->stream,
        $this->bufferSize,
        $this->maxDepth,
        $path,
        $newOwnsStream
    );
}
```

**Proposed Solution:**
```php
public function withPath(string $path): self
{
    // Check if stream is seekable
    $metadata = stream_get_meta_data($this->stream);
    if ($metadata['seekable'] && $this->buffer->getPosition() > 0) {
        // Reset stream to beginning
        rewind($this->stream);
        $this->buffer->reset();
    } elseif ($this->buffer->getPosition() > 0) {
        throw new IOException(
            'Cannot use fluent methods after reading from non-seekable stream'
        );
    }

    return new self(
        $this->stream,
        $this->bufferSize,
        $this->maxDepth,
        $path,
        $newOwnsStream
    );
}
```

## Dependencies
- None (critical path item)

## Estimated Complexity
**Medium** - Requires careful handling of stream state and seekability detection

## Implementation Notes
- Need to track buffer position to detect partial consumption
- BufferManager may need a `getPosition()` method if not already present
- Consider whether to add a `reset()` method to BufferManager
- Must test with both seekable (file) and non-seekable (network) streams
- All three fluent methods need the same fix: `withPath()`, `withBufferSize()`, `withMaxDepth()`

## Acceptance Criteria
- [ ] Stream position is reset when calling fluent methods on seekable streams
- [ ] Exception is thrown when calling fluent methods after partial read on non-seekable streams
- [ ] Documentation clearly states limitations for non-seekable streams
- [ ] Tests verify correct behavior for both seekable and non-seekable streams
- [ ] Tests verify data integrity when chaining fluent methods
- [ ] All existing tests pass
- [ ] Code follows PSR-12 standards
- [ ] PHPStan analysis passes
