---
title: ObjectIterator Implementation
status: done
priority: High
description: Implement the ObjectIterator class for streaming iteration over JSON objects.
---

## Objectives
- Implement Iterator interface for foreach compatibility
- Implement Countable interface
- Support has() and get() for property access
- Provide toArray() for loading into memory
- Memory efficient (only current property in memory)

## Deliverables
1. `src/Reader/ObjectIterator.php` implementing:

   **Iterator Interface:**
   - `current(): mixed` - Current property value
   - `key(): string|null` - Current property name
   - `next(): void` - Move to next property
   - `rewind(): void` - Reset to first property
   - `valid(): bool` - Check if current position is valid

   **Countable Interface:**
   - `count(): int` - Property count (-1 if unknown)

   **Extended Functionality:**
   - `has(string $key): bool` - Check if property exists
   - `get(string $key, mixed $default = null): mixed` - Get property value
   - `toArray(): array` - Load all into PHP array

## API Reference
See API_SIGNATURE.md lines 569-688

## Technical Considerations
- Use Parser::parseObject() generator internally
- Track current property name
- Implement has()/get() by iterating until found
- Handle rewind for seekable streams only
- Lazy evaluation (parse on demand)
- Store parsed key-value pairs for has()/get() optimization

## Dependencies
- Task 06: Streaming Parser
- Task 07: StreamReader Base

## Estimated Complexity
**Medium** - Similar to ArrayIterator with key differences

## Acceptance Criteria
- [x] Full Iterator interface implementation
- [x] Works with foreach loops
- [x] has() correctly checks for properties
- [x] get() retrieves property values or returns default
- [x] toArray() loads all properties
- [x] count() returns correct value when possible
- [x] Memory remains constant during iteration
- [x] Unit tests with 100% coverage
- [x] Integration tests with large objects
