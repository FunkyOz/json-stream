---
title: Streaming Parser Core
status: done
priority: Critical
description: Implement the core streaming parser that builds PHP values from tokens without constructing a full AST. This parser powers all reader iterators.
---

## Objectives
- Create Parser class that converts tokens to PHP values
- Support streaming (yield values as they're parsed)
- Handle nested structures with depth limits
- Provide hooks for iterator implementations
- Memory efficient (no full tree construction)

## Deliverables
1. `src/Internal/Parser.php` - Core parser
   - Constructor accepting Lexer and options
   - `parseValue(): mixed` - Parse any JSON value
   - `parseArray(): \Generator` - Yield array elements
   - `parseObject(): \Generator` - Yield object properties
   - `skipValue(): void` - Skip a value without parsing
   - Depth tracking and limit enforcement
   - Position tracking for errors

## Technical Considerations
- Use generators for streaming
- Maintain parse stack for depth tracking
- Validate JSON structure (matching braces, commas, etc.)
- Handle both associative arrays and objects
- Throw ParseException with accurate positions
- Support partial parsing (for iterators)

## Parser Algorithm
- Recursive descent parser
- Single-pass (no backtracking)
- Streaming-friendly (progressive output)

## Dependencies
- Task 02: Config Constants (for depth limits)
- Task 03: Exception Classes (for ParseException)
- Task 05: Lexer/Tokenizer

## Estimated Complexity
**High** - Complex state management

## Acceptance Criteria
- [x] Parser class implemented
- [x] Handles all JSON types correctly
- [x] Depth limits enforced
- [x] Proper error messages with positions
- [x] Generator-based for streaming
- [x] Unit tests with 100% coverage
- [x] Handles deeply nested structures
- [x] Edge case tests (empty arrays/objects, mixed nesting)
