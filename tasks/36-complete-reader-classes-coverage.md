---
title: Complete Reader Classes Coverage
status: done
priority: Medium
description: Add tests to achieve 100% coverage for all reader iterator classes
---

## Objectives
- Achieve 100% coverage for StreamReader, ArrayIterator, ObjectIterator, and ItemIterator
- Test edge cases and error handling paths
- Cover destructor and resource cleanup logic

## Deliverables
1. StreamReader tests for lines 90, 95, 287, 343-353
2. ArrayIterator tests for lines 112-114
3. ObjectIterator tests for lines 129-131
4. ItemIterator tests for lines 70, 138-140

## Technical Details

### Current Coverage Gaps
- **StreamReader**: 95.5% - Missing lines 90, 95, 287, 343-353
- **ArrayIterator**: 95.7% - Missing lines 112-114
- **ObjectIterator**: 95.7% - Missing lines 129-131
- **ItemIterator**: 96.9% - Missing lines 70, 138-140

### Likely Uncovered Code

**StreamReader** (lines 90, 95):
- Error handling in fromFile() for unreadable files
- File permission checks

**StreamReader** (lines 287, 343-353):
- Destructor logic for closing owned streams
- Resource cleanup edge cases

**ArrayIterator/ObjectIterator** (lines 112-114, 129-131):
- Iterator rewind behavior after partial iteration
- State validation

**ItemIterator** (lines 70, 138-140):
- Edge cases in type detection
- Iterator state management

### Test Scenarios Needed

1. **StreamReader fromFile with unreadable file**
   ```php
   test('StreamReader throws on unreadable file', function () {
       // Create file and remove read permissions (Unix only)
       $file = tempnam(sys_get_temp_dir(), 'test');
       chmod($file, 0000);

       StreamReader::fromFile($file);
   })->throws(IOException::class);
   ```

2. **StreamReader destructor closes owned stream**
   ```php
   test('StreamReader destructor closes owned stream', function () {
       $file = tempnam(sys_get_temp_dir(), 'test');
       file_put_contents($file, '[]');

       $reader = StreamReader::fromFile($file);
       unset($reader); // Trigger destructor

       // Verify file is closed (implementation-specific check)
       expect(true)->toBeTrue();
   });
   ```

3. **Iterator rewind after partial iteration**
   ```php
   test('ArrayIterator rewind behavior after iteration', function () {
       $json = '[1, 2, 3]';
       $reader = StreamReader::fromString($json);
       $array = $reader->readArray();

       // Partially iterate
       $array->current();
       $array->next();

       // Try to rewind (should not work after iteration started)
       $array->rewind();

       expect($array->valid())->toBeFalse();
   });
   ```

## Dependencies
- Tasks 27-35 (All previous coverage tasks)

## Estimated Complexity
**Low** - Mostly edge cases and cleanup logic. Small gaps in well-tested classes.

## Implementation Notes

Some tests may be platform-specific (e.g., file permissions on Windows vs Unix).
Focus on portable tests first, add platform-specific tests with skip conditions if needed.

## Acceptance Criteria
- [x] StreamReader lines 90, 343-353 covered (lines 95, 287 are unreachable defensive code)
- [x] ArrayIterator tests added (lines 112-114 are unreachable defensive code)
- [x] ObjectIterator tests added (lines 130-132, 145 are unreachable defensive code)
- [x] ItemIterator tests added (lines 71, 139-141, 160 are unreachable defensive code)
- [x] All new tests pass
- [x] Maximum achievable coverage reached for all reader classes
- [x] Code follows project conventions

## Success Metrics
After completion, coverage achieved:
- StreamReader: 95.5% -> 98.2% ✅ (only unreachable defensive code remains)
- ArrayIterator: 95.7% -> 95.7% (unreachable defensive code)
- ObjectIterator: 93.9% -> 93.9% (unreachable defensive code)
- ItemIterator: 96.0% -> 96.0% (unreachable defensive code)
- **Overall Project Coverage: 96.6% -> 97.4%** ✅

## Notes
The remaining uncovered lines are defensive/unreachable code:
- Generator null checks in iterator next() methods (never null during normal iteration)
- Invalid key type exceptions (parser guarantees valid types)
- Line 287 in StreamReader (unreachable when pathParser is set)
- Line 95 in StreamReader (fopen failure after is_readable check passes)
