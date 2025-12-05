---
title: Fix Large Object Array Parsing Bug
status: done
priority: Critical
description: Fix parser failure when reading arrays with >1000 complex objects
---

## Objectives
- Fix "Expected comma or closing brace" exception when parsing large arrays
- Ensure StreamReader can handle arrays with 10K+ complex objects
- Maintain consistent behavior across buffer boundaries
- Preserve existing test compatibility

## Deliverables
1. Bug fix in parser/lexer state management at buffer boundaries
2. New integration tests for large arrays (1K, 5K, 10K, 50K elements)
3. Stress tests verifying round-trip with complex nested objects
4. Documentation of the root cause and fix in code comments

## Technical Details

### Bug Description
When reading JSON arrays containing more than ~1000 complex objects using StreamReader, the parser throws "Expected comma or closing brace" ParseException. The issue appears to be related to buffer refill logic during object parsing.

### Reproduction Code
```php
// Write large array (this works)
$writer = StreamWriter::toFile('test.json');
$array = $writer->beginArray();
for ($i = 0; $i < 10000; $i++) {
    $array->value([
        'id' => $i,
        'name' => "User $i",
        'email' => "user{$i}@example.com",
        'active' => true,
        'score' => $i * 1.5
    ]);
}
$array->endArray();
$writer->close();

// Read large array (this fails around element 1000-5000)
$reader = StreamReader::fromFile('test.json');
foreach ($reader->readArray() as $item) {
    // ParseException thrown: "Expected comma or closing brace"
}
```

### Evidence
- Generated JSON files are valid (verified with native `json_decode`)
- Writing large arrays works correctly
- Existing tests pass because they use < 1000 elements
- Error occurs consistently around same element count
- Native PHP `json_decode` can read the generated files without issue

### Suspected Root Cause
Based on the symptoms, likely causes:
1. **Buffer boundary handling**: Parser state may not be properly preserved when BufferManager refills at element boundaries
2. **Token stream continuity**: Lexer may be losing context when tokenizing across buffer chunks
3. **State machine edge case**: Parser state transitions may have an edge case with specific token sequences in large arrays

### Files to Investigate
- `src/Parser/StreamParser.php`: Main parser state machine
- `src/Lexer/Lexer.php`: Token generation and buffer interaction
- `src/Buffer/BufferManager.php`: Buffer refill logic
- `src/Reader/StreamReader.php`: Array reading coordination

### Investigation Strategy
1. Add detailed logging to track parser state at buffer boundaries
2. Create minimal reproduction with exact element count where failure occurs
3. Compare buffer state before/after refill at failure point
4. Review parser state preservation in `parseArray()` and `parseObject()` methods
5. Check if issue is specific to objects or also affects primitive arrays
6. Verify token lookahead/pushback logic at chunk boundaries

## Dependencies
- Tasks 04-06 (Buffer Manager, Lexer, Parser) - components where bug exists
- Tasks 07-10 (Reader implementation) - affected by the bug

## Estimated Complexity
**High** - This is a deep bug in core parsing logic involving state management across buffer boundaries. Requires thorough investigation, careful debugging, and extensive testing to ensure the fix doesn't introduce regressions. The bug has existed undetected, suggesting it's subtle and may involve multiple interacting components.

## Implementation Notes

### Testing Strategy
1. **Create failing test first**: Write integration test that reproduces the bug
2. **Binary search**: Narrow down exact element count where failure occurs
3. **Logging**: Add comprehensive state logging at critical points
4. **Incremental validation**: Test with various array sizes (100, 500, 1K, 5K, 10K, 50K)
5. **Buffer size variations**: Test with different buffer sizes to isolate boundary issues
6. **Object complexity**: Test with varying object complexity (simple vs deeply nested)

### Potential Fix Areas
- Ensure parser state (depth, context stack) is maintained across buffer refills
- Verify lexer position tracking is accurate at chunk boundaries
- Check that BufferManager's `refill()` preserves necessary state
- Review any assumptions about token availability in single buffer

### Regression Prevention
- Add stress tests to integration suite
- Include tests with various buffer sizes
- Test edge cases: 999, 1000, 1001 elements
- Verify performance doesn't degrade with fix

### Performance Considerations
- Fix should not impact memory footprint (constant memory is key feature)
- May need to adjust buffer strategy if performance is affected
- Benchmark before/after fix with large datasets

## Acceptance Criteria
- [x] Parser successfully reads arrays with 10,000+ complex objects
- [x] Parser successfully reads arrays with 50,000+ complex objects
- [x] Round-trip write/read works for large arrays
- [x] All existing unit tests continue to pass (no regressions)
- [x] New integration tests added for large array scenarios
- [x] Tests pass with various buffer sizes (8KB, 16KB, 32KB, 64KB)
- [x] Memory usage remains constant regardless of array size
- [x] Performance benchmarks can run successfully
- [x] Root cause documented in code comments
- [x] Fix verified with native `json_decode` comparison

## Related Work
- **Blocks**: Task 19 (Performance Benchmarks) - cannot run until this is fixed
- **Found during**: Task 15 (Performance Optimization)
- **Critical for**: Production readiness and real-world usage with large datasets
