---
title: ArrayIterator Implementation
status: done
priority: High
description: Implement the ArrayIterator class for streaming iteration over JSON arrays.
---

## Objectives
- Implement Iterator interface for foreach compatibility
- Implement Countable interface
- Support skip() and limit() for pagination
- Provide toArray() for loading into memory
- Memory efficient (only current element in memory)

## Deliverables
1. `src/Reader/ArrayIterator.php` implementing:

   **Iterator Interface:**
   - `current(): mixed` - Current array element
   - `key(): int` - Current index (0-based)
   - `next(): void` - Move to next element
   - `rewind(): void` - Reset to first element
   - `valid(): bool` - Check if current position is valid

   **Countable Interface:**
   - `count(): int` - Element count (-1 if unknown)

   **Extended Functionality:**
   - `skip(int $count): ArrayIterator` - Skip N elements
   - `limit(int $count): ArrayIterator` - Limit to N elements
   - `toArray(): array` - Load all into PHP array

## API Reference
See API_SIGNATURE.md lines 435-566

## Technical Considerations
- Use Parser::parseArray() generator internally
- Track current index
- Implement skip by consuming tokens without parsing values
- Implement limit by counting yielded items
- Handle rewind for seekable streams only
- Lazy evaluation (parse on demand)

## Dependencies
- Task 06: Streaming Parser
- Task 07: StreamReader Base

## Estimated Complexity
**Medium** - Iterator pattern with streaming

## Acceptance Criteria
- [x] Full Iterator interface implementation
- [x] Works with foreach loops
- [x] skip() efficiently skips elements
- [x] limit() correctly limits output
- [x] toArray() loads remaining elements
- [x] count() returns correct value when possible
- [x] Memory remains constant during iteration
- [x] Unit tests with 100% coverage
- [x] Integration tests with large arrays
