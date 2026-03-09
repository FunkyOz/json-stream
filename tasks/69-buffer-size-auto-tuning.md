---
title: Buffer Size Auto-Tuning
status: todo
priority: Low
description: Automatically adjust buffer size based on stream characteristics for optimal throughput
---

## Objectives
- Automatically select optimal buffer size based on stream type and data patterns
- Reduce unnecessary buffer refills for large files
- Avoid over-allocation for small files

## Deliverables
1. Auto-tuning logic in BufferManager
2. Benchmarks showing improvement for various file sizes
3. Override still available via explicit bufferSize option

## Technical Details

**Location:** `src/Internal/BufferManager.php`

**Current Issue:**
Buffer size defaults to 8KB (`Config::DEFAULT_BUFFER_SIZE`). This is:
- Too small for large files (30MB+) causing many refill operations
- Potentially too large for tiny files (< 1KB) wasting memory

Benchmark data shows minimal difference between 8KB-64KB buffers for 1MB files, but for 30MB files the refill overhead accumulates.

**Proposed Solution:**
```php
final class BufferManager
{
    public function __construct(
        private readonly mixed $stream,
        private int $bufferSize = Config::DEFAULT_BUFFER_SIZE
    ) {
        // Auto-tune if using default
        if ($bufferSize === Config::DEFAULT_BUFFER_SIZE) {
            $this->bufferSize = $this->autoTuneBufferSize();
        }
    }

    private function autoTuneBufferSize(): int
    {
        $meta = stream_get_meta_data($this->stream);

        // For seekable streams, check file size
        if ($meta['seekable']) {
            $stat = fstat($this->stream);
            if ($stat !== false && $stat['size'] > 0) {
                $fileSize = $stat['size'];

                if ($fileSize < 4096) {
                    return Config::MIN_BUFFER_SIZE; // 1KB for tiny files
                }
                if ($fileSize > 1_000_000) {
                    return 65536; // 64KB for large files
                }
            }
        }

        return Config::DEFAULT_BUFFER_SIZE; // 8KB default
    }
}
```

## Dependencies
- None

## Estimated Complexity
**Low** - Simple heuristic based on stream metadata

## Implementation Notes
- `fstat()` is available for file streams and gives file size
- Network streams won't have size info, so default applies
- The auto-tune runs once at construction, no ongoing overhead
- User-specified bufferSize always overrides auto-tuning
- Benchmark showed 8KB-64KB difference is < 2% for 1MB files, so impact is mainly for very large files

## Acceptance Criteria
- [ ] Buffer size auto-tunes based on file size for seekable streams
- [ ] Explicit bufferSize option still overrides auto-tuning
- [ ] Large files (> 1MB) use larger buffer automatically
- [ ] Small files (< 4KB) use smaller buffer
- [ ] All existing tests pass
- [ ] PHPStan analysis passes
