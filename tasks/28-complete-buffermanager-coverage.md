---
title: Complete BufferManager Coverage
status: done
priority: High
description: Add tests to cover missing BufferManager edge cases
---

## Objectives
- Achieve 100% coverage for BufferManager class
- Test uncovered edge cases in readChunk and error handling
- Verify buffer refill logic in all scenarios

## Deliverables
1. Additional tests for BufferManager covering lines 151, 237, and 266
2. Tests for readChunk with size=0 and negative sizes
3. Tests for edge cases in buffer refill logic
4. Verification of all code paths

## Technical Details

### Current Coverage Gap
- **BufferManager**: 96.3% coverage
- **Missing lines**: 151, 237, 266

### Uncovered Code Analysis

**Line 151** - readChunk with size <= 0:
```php
public function readChunk(int $size): string
{
    if ($size <= 0) {
        return '';  // Line 151 - not covered
    }
    // ...
}
```

**Line 237** - Error path in refillBuffer:
```php
private function refillBuffer(): bool
{
    // ...
    if ($this->stream === null) {
        throw new IOException('Stream is not initialized');  // Line 237 - not covered
    }
    // ...
}
```

**Line 266** - Constructor validation error:
```php
private function validateStreamResource($stream): void
{
    // ...
    if (feof($stream)) {
        throw new IOException('Stream is at EOF');  // Line 266 - not covered (or similar error path)
    }
}
```

### Test Scenarios Needed

1. **readChunk with size 0**
   - Call readChunk(0), expect empty string
   - Verify no buffer consumption

2. **readChunk with negative size**
   - Call readChunk(-1), expect empty string
   - Verify no buffer consumption

3. **refillBuffer with closed/null stream**
   - Close stream, attempt read
   - Should throw IOException

4. **Stream at EOF during construction**
   - Open file, seek to end
   - Pass EOF stream to BufferManager
   - Should throw IOException or handle gracefully

5. **Edge cases in buffer boundaries**
   - Test readChunk spanning multiple buffer refills
   - Test exact buffer size reads

## Dependencies
- Task 27 (Exception tests) - Need exception classes fully tested first

## Estimated Complexity
**Low** - Simple unit tests for edge cases. Most logic is already tested, just need to cover error paths and boundary conditions.

## Implementation Notes

### Example Tests

```php
test('BufferManager readChunk returns empty string for zero size', function () {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, 'test data');
    rewind($stream);

    $buffer = new BufferManager($stream);
    $result = $buffer->readChunk(0);

    expect($result)->toBe('');
});

test('BufferManager readChunk returns empty string for negative size', function () {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, 'test data');
    rewind($stream);

    $buffer = new BufferManager($stream);
    $result = $buffer->readChunk(-5);

    expect($result)->toBe('');
});

test('BufferManager throws IOException when stream is closed during refill', function () {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, str_repeat('a', 10000)); // Large enough to require refill
    rewind($stream);

    $buffer = new BufferManager($stream, 100); // Small buffer
    $buffer->readChunk(50); // Read some data

    fclose($stream); // Close the stream

    // Next read should trigger refill and throw
    $buffer->readChunk(100);
})->throws(IOException::class);
```

### Files to Update
- `tests/Unit/Internal/BufferManagerTest.php` - Add new test cases

## Acceptance Criteria
- [x] Line 151 is covered (readChunk size <= 0)
- [x] Line 237 is covered (fseek failure in reset)
- [x] Line 267 is covered (fread failure in refillBuffer)
- [x] Line 137 is covered (peek beyond EOF after refill)
- [x] All new tests pass
- [x] Coverage report shows 100% for BufferManager
- [x] No regressions in existing tests
- [x] Code follows project conventions

## Success Metrics
After completion, coverage should show:
- BufferManager: 96.3% -> 100%
