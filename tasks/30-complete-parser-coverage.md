---
title: Complete Parser Coverage
status: todo
priority: High
description: Add tests to cover missing Parser paths including JSONPath filtering logic
---

## Objectives
- Achieve 100% coverage for Parser class
- Test all JSONPath filtering code paths
- Cover error handling and edge cases

## Deliverables
1. Tests for parseStream with JSONPath filtering (lines 73-75, 83-85)
2. Tests for streamFromPath navigation (line 133)
3. Tests for parseArray/parseObject edge cases (lines 150-151, 182, 216-224, 241-242)
4. Tests for skipValue edge cases (lines 262)
5. Tests for depth tracking (lines 394-395, 454-457, 472, 488-491, 498, 517)

## Technical Details

### Current Coverage Gap
- **Parser**: 86.1% coverage
- **Missing lines**: 73-75, 83-85, 133, 150-151, 182, 216-224, 241-242, 262, 394-395, 454-457, 472, 488-491, 498, 517

### Key Uncovered Areas

1. **JSONPath Filtering** (lines 73-75, 83-85)
   - Path matches root "$"
   - Early return for root match
   - Path-based streaming

2. **Navigation and Streaming** (line 133)
   - streamFromPath logic
   - Path segment navigation

3. **Parse Errors** (various lines)
   - Unexpected EOF in arrays/objects
   - Missing structural tokens
   - Depth limit violations

### Test Scenarios Needed

1. Test parseStream with path="$" (root only)
2. Test parseStream with path matching root
3. Test parseArray with unexpected EOF
4. Test parseObject with unexpected EOF
5. Test depth tracking at exact limits
6. Test depth violations in nested structures
7. Test skipValue on deeply nested structures

## Dependencies
- Task 27 (Exception tests)

## Estimated Complexity
**Medium** - Requires understanding of streaming and JSONPath logic. Some tests involve complex setup.

## Implementation Notes

```php
test('Parser handles root path filter', function () {
    $json = '{"name":"test"}';
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $json);
    rewind($stream);

    $buffer = new BufferManager($stream);
    $lexer = new Lexer($buffer);
    $pathEvaluator = new PathEvaluator(PathParser::parse('$'));
    $parser = new Parser($lexer, $pathEvaluator);

    $results = iterator_to_array($parser->parseStream());
    expect($results)->toHaveCount(1);
});
```

## Acceptance Criteria
- [ ] All listed line ranges are covered
- [ ] JSONPath root filtering tested
- [ ] Depth limit edge cases tested
- [ ] Error paths tested
- [ ] Coverage shows 100% for Parser
- [ ] All tests pass
- [ ] Code follows project conventions

## Success Metrics
- Parser: 86.1% -> 100%
