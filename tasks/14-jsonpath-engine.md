---
title: JSONPath Engine
status: done
priority: Medium
description: Implement a JSONPath engine that can filter JSON data during streaming parsing. This allows users to extract specific data without loading the entire JSON into memory.
---

## Objectives
- Implement JSONPath expression parser
- Support common JSONPath operators
- Integrate with streaming parser
- Filter data during parsing (not after)
- Memory efficient evaluation

## Deliverables
1. `src/Internal/JsonPath/PathExpression.php` - Parsed path representation
2. `src/Internal/JsonPath/PathEvaluator.php` - Path evaluation engine
3. Integration with StreamReader

## JSONPath Features to Support
- Root: `$`
- Child operator: `.property` or `['property']`
- Recursive descent: `..property`
- Array index: `[0]`, `[-1]` (last)
- Array slice: `[0:5]`, `[::2]`
- Array wildcard: `[*]`
- Filter expressions: `[?(@.price < 10)]`
- Multiple selectors: `['name', 'id']`

## API Reference
See API_SIGNATURE.md lines 252-266, 1391-1425

## Technical Considerations
- Parse path expression into AST
- Evaluate against current parser state
- Track parser depth and path
- Only yield values matching path
- Throw PathException for invalid expressions
- Performance optimization (early exit)

## Example Usage
```php
// Only extract emails from large user database
$reader = StreamReader::fromFile('users.json')
    ->withPath('$.users[*].email');
foreach ($reader->readItems() as $email) {
    // Only emails are parsed and yielded
}
```

## Dependencies
- Task 03: Exception Classes (PathException)
- Task 06: Streaming Parser
- Task 07: StreamReader Base

## Estimated Complexity
**High** - Complex parsing and evaluation logic

## Acceptance Criteria
- [x] Path expression parser implemented
- [x] Path evaluator integrated with parser
- [x] All basic JSONPath operators supported
- [x] Filter expressions work
- [x] PathException thrown for invalid paths
- [x] Memory efficient (streaming evaluation)
- [x] Unit tests for all path types
- [x] Integration tests with real JSON data
- [ ] Performance tests (filtering vs post-filtering) - Deferred to Task 19
