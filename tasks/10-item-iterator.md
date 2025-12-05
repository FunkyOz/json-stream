---
title: ItemIterator Implementation
status: done
priority: Medium
description: Implement the ItemIterator class for iterating over any JSON structure (arrays, objects, or mixed).
---

## Objectives
- Implement Iterator interface for foreach compatibility
- Support type checking methods
- Handle both arrays and objects uniformly
- Provide toArray() for loading into memory
- Automatic type detection

## Deliverables
1. `src/Reader/ItemIterator.php` implementing:

   **Iterator Interface:**
   - `current(): mixed` - Current item value
   - `key(): string|int|null` - Current key (string for objects, int for arrays)
   - `next(): void` - Move to next item
   - `rewind(): void` - Reset to first item
   - `valid(): bool` - Check if current position is valid

   **Type Checking Methods:**
   - `getType(): string` - Returns type of current item
   - `isArray(): bool` - Check if current item is array
   - `isObject(): bool` - Check if current item is object

   **Extended Functionality:**
   - `toArray(): array` - Load all into PHP array

## API Reference
See API_SIGNATURE.md lines 690-794

## Technical Considerations
- Detect root JSON type on first iteration
- Use appropriate parser method based on type
- Track both string and integer keys
- Handle scalar values at root level
- Type detection for each yielded value

## Dependencies
- Task 06: Streaming Parser
- Task 07: StreamReader Base

## Estimated Complexity
**Medium** - Generic handling of multiple types

## Acceptance Criteria
- [x] Full Iterator interface implementation
- [x] Works with arrays, objects, and scalars
- [x] Type checking methods accurate
- [x] getType() returns correct type strings
- [x] toArray() works for all JSON types
- [x] Memory remains constant during iteration
- [x] Unit tests with mixed structures
- [x] Edge cases (scalar root values)
