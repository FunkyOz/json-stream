---
title: Integration Tests
status: done
priority: High
description: Implement comprehensive integration tests that validate the entire JsonStream library working together with real files and streams.
---

## Objectives
- Test complete workflows (read → process → write)
- Test with real files of various sizes
- Test with actual PHP streams
- Verify memory efficiency
- Test error scenarios
- Use Pest PHP testing framework

## Test Scenarios

### Reader Integration Tests
1. **Large File Reading**
   - Create multi-MB test files
   - Read with StreamReader
   - Verify all data parsed correctly
   - Verify memory stays constant

2. **Stream Reading**
   - Read from php://input simulation
   - Read from network streams
   - Read from compressed streams

3. **JSONPath Filtering**
   - Large files with path filters
   - Verify only matching data returned
   - Verify memory efficiency

### Writer Integration Tests
1. **Large File Writing**
   - Generate multi-MB JSON files
   - Verify file is valid JSON
   - Verify pretty printing works
   - Verify memory stays constant

2. **Stream Writing**
   - Write to php://output
   - Write to string buffer
   - Write to temp files

### Round-Trip Tests
1. **Read → Write Cycle**
   - Read JSON file with StreamReader
   - Write with StreamWriter
   - Verify output matches input
   - Test with various structures

2. **Data Transformation**
   - Read large dataset
   - Transform each item
   - Write to new file
   - Verify transformations applied

### Error Scenarios
1. **Malformed JSON**
   - Invalid syntax
   - Verify ParseException thrown
   - Verify error positions accurate

2. **I/O Errors**
   - File not found
   - Permission denied
   - Disk full simulation

## Test Structure
```
tests/Integration/
├── LargeFileReadTest.php
├── LargeFileWriteTest.php
├── StreamReadWriteTest.php
├── RoundTripTest.php
├── JsonPathIntegrationTest.php
└── ErrorHandlingTest.php
```

## Test Data
- Create fixture files of various sizes (1KB, 1MB, 10MB, 100MB)
- Create files with different structures (arrays, objects, nested)
- Create malformed JSON files for error testing

## Dependencies
- All core tasks (01-14)

## Estimated Complexity
**Medium** - Test setup and validation

## Acceptance Criteria
- [x] All integration test files created
- [x] Tests cover complete workflows
- [x] Memory efficiency verified
- [x] Large file tests pass
- [x] Stream tests pass
- [x] Round-trip tests pass
- [x] Error scenarios handled correctly
- [x] Test fixtures created
- [x] `composer tests` passes (includes integration tests)
