# JsonStream PHP - Task Index

This directory contains all implementation tasks for the JsonStream PHP library, organized by development phase.

## Overview

**Total Tasks:** 54
**Completed Tasks:** 36 (67%)
**Current Status:** v1.0 Release Ready - Reader-Only | 97.4% Coverage | Coverage Improvement & Analysis Fixes Planned
**Estimated Timeline:** 8-12 weeks for complete implementation + 8-10 hours for 100% coverage + 2-10 hours for 98%+ coverage + Analysis fixes

**Coverage Improvement Phase Added**: Phase 11 added with 5 tasks to improve coverage from 97.4% to 98%+ (maximum practical level)
**Analysis Report Fixes Added**: Phase 10 added with 13 tasks to address issues found in comprehensive code analysis (ANALYSIS_REPORT.md, Dec 8, 2025)
**All Core Tasks Complete**: JsonStream v1.0 is ready for release as a reader-only library.
**Phase 9 Complete**: Maximum practical coverage achieved (97.4%). Remaining 2.6% is defensive/unreachable code.
**Phase 8 Complete**: Writer feature preserved in `feature/writer` branch. Main branch contains reader-only functionality.
**Phase 7 Complete**: All core implementation, testing, and code review tasks complete. Production-ready library with 100% type coverage, comprehensive documentation, and all quality checks passing.
**Phase 6 Testing Complete**: All testing tasks (16-19) complete with 100% code coverage, 511 total tests passing, and comprehensive performance benchmarks.
**Critical Memory Issue Resolved**: Task 24 completed - JSONPath expressions like `$.Ads[*]` now stream with constant memory (0 MB delta) using hybrid approach.

---

## Phase 1: Foundation (Critical Priority)

These tasks must be completed first as they provide the base infrastructure.

| # | Task | Priority | Complexity | Status | Dependencies |
|---|------|----------|------------|--------|--------------|
| 01 | [Project Setup](01-project-setup.md) | Critical | Low | `done` | None |
| 02 | [Config Constants](02-config-constants.md) | High | Low | `done` | Task 01 |
| 03 | [Exception Classes](03-exception-classes.md) | High | Low | `done` | Task 01 |

**Phase Duration:** ~1 week
**Deliverables:** Project structure, constants, exception handling

---

## Phase 2: Core Infrastructure (Critical Priority)

Core parsing and buffering components that power all readers and writers.

| # | Task | Priority | Complexity | Status | Dependencies |
|---|------|----------|------------|--------|--------------|
| 04 | [Buffer Manager](04-buffer-manager.md) | Critical | Medium | `done` | Tasks 02, 03 |
| 05 | [Lexer/Tokenizer](05-lexer-tokenizer.md) | Critical | High | `done` | Tasks 03, 04 |
| 06 | [Streaming Parser](06-streaming-parser.md) | Critical | High | `done` | Tasks 02, 03, 05 |

**Phase Duration:** ~2-3 weeks
**Deliverables:** Token stream, JSON parser, buffer management

---

## Phase 3: Reader Implementation (High Priority)

Implementation of all reading/parsing functionality.

| # | Task | Priority | Complexity | Status | Dependencies |
|---|------|----------|------------|--------|--------------|
| 07 | [StreamReader Base](07-stream-reader-base.md) | High | Medium | `done` | Tasks 03-06 |
| 08 | [ArrayIterator](08-array-iterator.md) | High | Medium | `done` | Tasks 06, 07 |
| 09 | [ObjectIterator](09-object-iterator.md) | High | Medium | `done` | Tasks 06, 07 |
| 10 | [ItemIterator](10-item-iterator.md) | Medium | Medium | `done` | Tasks 06, 07 |

**Phase Duration:** ~2 weeks
**Deliverables:** Complete reading functionality, all iterator types

---

## Phase 4: Writer Implementation (High Priority)

Implementation of all writing/serialization functionality.

| # | Task | Priority | Complexity | Status | Dependencies |
|---|------|----------|------------|--------|--------------|
| 11 | [StreamWriter Base](11-stream-writer-base.md) | High | Medium | `done` | Tasks 02, 03 |
| 12 | [ArrayWriter](12-array-writer.md) | High | Medium | `done` | Task 11 |
| 13 | [ObjectWriter](13-object-writer.md) | High | Medium | `done` | Tasks 11, 12 |

**Phase Duration:** ~1-2 weeks
**Deliverables:** Complete writing functionality, nested structure support

---

## Phase 5: Advanced Features (Medium Priority)

Optional but valuable features that enhance library capabilities.

| # | Task | Priority | Complexity | Status | Dependencies |
|---|------|----------|------------|--------|--------------|
| 14 | [JSONPath Engine](14-jsonpath-engine.md) | Medium | High | `done` | Tasks 03, 06, 07 |
| 15 | [Performance Optimization](15-performance-optimization.md) | Low | High | `done` | Tasks 01-13 |
| 24 | [Streaming JSONPath Memory Optimization](24-streaming-jsonpath-memory-optimization.md) | Critical | High | `done` | Tasks 14, 15, 23 |
| 25 | [Complex Pattern Streaming](25-complex-pattern-streaming.md) | Low | High | `todo` | Task 24 |

**Phase Duration:** ~2-3 weeks
**Deliverables:** JSONPath filtering, performance improvements, streaming JSONPath, complex pattern streaming

---

## Phase 5.5: Critical Bug Fixes (Critical Priority)

Critical bugs discovered during testing that block production readiness.

| # | Task | Priority | Complexity | Status | Dependencies |
|---|------|----------|------------|--------|--------------|
| 22 | [Fix Large Array Parsing Bug](22-fix-large-array-parsing-bug.md) | Critical | High | `done` | Tasks 04-10 |

**Phase Duration:** ~1-2 weeks
**Deliverables:** Fixed parser for large arrays, stress tests, production-ready parsing

⚠️ **BLOCKS**: Task 19 (Performance Benchmarks) - benchmarks cannot run until parsing bug is fixed

---

## Phase 6: Testing (High Priority)

Comprehensive test coverage to ensure quality and reliability.

| # | Task | Priority | Complexity | Status | Dependencies |
|---|------|----------|------------|--------|--------------|
| 16 | [Unit Tests - Reader](16-unit-tests-reader.md) | High | Medium | `done` | Tasks 07-10 |
| 17 | [Unit Tests - Writer](17-unit-tests-writer.md) | High | Medium | `done` | Tasks 11-13 |
| 18 | [Integration Tests](18-integration-tests.md) | High | Medium | `done` | All core tasks |
| 19 | [Performance Benchmarks](19-performance-benchmarks.md) | Medium | Medium | `done` | All core tasks |
| 23 | [JSONPath Validation & Edge Cases](23-jsonpath-benchmarks-and-validation.md) | High | High | `done` | Tasks 14, 15 |

**Phase Duration:** ~2 weeks
**Deliverables:** 100% test coverage, benchmarks, integration validation

---

## Phase 7: Documentation & Release (Medium Priority)

User-facing documentation and final polish for release.

| # | Task | Priority | Complexity | Status | Dependencies |
|---|------|----------|------------|--------|--------------|
| 20 | [README & Examples](20-readme-and-examples.md) | Medium | Low | `done` | All implementations |
| 21 | [Code Review & Polish](21-code-review-and-polish.md) | Medium | Medium | `done` | All tasks |

**Phase Duration:** ~1 week
**Deliverables:** Documentation, examples, release-ready code

---

## Phase 8: v1.0 Release Preparation (High Priority)

Final preparations for v1.0 release.

| # | Task | Priority | Complexity | Status | Dependencies |
|---|------|----------|------------|--------|--------------|
| 26 | [Remove Writer for v1.0 Release](26-remove-writer-for-v1-release.md) | High | Medium | `done` | Task 21 |

**Phase Duration:** ~1 day
**Deliverables:** Reader-only v1.0 release, writer preserved in `feature/writer` branch

---

## Phase 9: 100% Code Coverage (High Priority)

Achieve 100% code coverage for all production code. Current coverage: 88.2%.

| # | Task | Priority | Complexity | Status | Dependencies |
|---|------|----------|------------|--------|--------------|
| 27 | [Test Exception Classes](27-test-exception-classes.md) | Critical | Low | `done` | None |
| 28 | [Complete BufferManager Coverage](28-complete-buffermanager-coverage.md) | High | Low | `done` | Task 27 |
| 29 | [Complete Lexer Coverage](29-complete-lexer-coverage.md) | High | Low | `done` | Task 27 |
| 30 | [Complete Parser Coverage](30-complete-parser-coverage.md) | High | Medium | `done` | Task 27 |
| 31 | [Complete PathEvaluator Coverage](31-complete-pathevaluator-coverage.md) | High | Medium | `done` | Task 30 |
| 32 | [Complete PathExpression Coverage](32-complete-pathexpression-coverage.md) | High | Low | `done` | Task 31 |
| 33 | [Complete PathFilter Coverage](33-complete-pathfilter-coverage.md) | High | Low | `done` | Task 31 |
| 34 | [Complete FilterSegment Coverage](34-complete-filtersegment-coverage.md) | High | Low | `done` | Task 31 |
| 35 | [Complete RootSegment Coverage](35-complete-rootsegment-coverage.md) | High | Low | `done` | Task 31 |
| 36 | [Complete Reader Classes Coverage](36-complete-reader-classes-coverage.md) | Medium | Low | `done` | Tasks 27-35 |

**Phase Duration:** ~8-10 hours
**Deliverables:** 100% code coverage on all production code

---

## Phase 10: Analysis Report Fixes (Mixed Priority)

Address issues found in comprehensive code analysis (ANALYSIS_REPORT.md, Dec 8, 2025). Organized by priority: Critical issues first, then security/robustness, then optimizations.

### Phase 10.1: Critical Issues

| # | Task | Priority | Complexity | Status | Dependencies |
|---|------|----------|------------|--------|--------------|
| 37 | [Fix Stream Position in Fluent Methods](37-stream-position-fluent-methods.md) | Critical | Medium | `todo` | None |
| 38 | [Add UTF-16 Lone Surrogate Validation](38-utf16-surrogate-validation.md) | High | Low | `todo` | None |
| 39 | [Document Negative Index Streaming Limitations](39-negative-index-streaming-mode.md) | High | Medium | `todo` | None |

**Sub-Phase Duration:** Priority for v1.0.1
**Deliverables:** Stream position handling, UTF-8 validity, streaming documentation

### Phase 10.2: Security & Robustness

| # | Task | Priority | Complexity | Status | Dependencies |
|---|------|----------|------------|--------|--------------|
| 40 | [Add Integer Overflow Handling](40-integer-overflow-handling.md) | Medium | Medium | `todo` | None |
| 41 | [Add ObjectIterator Cache Limits](41-objectiterator-cache-limits.md) | Medium | Medium | `todo` | Task 02 |
| 42 | [Add PathFilter Depth Tracking](42-pathfilter-depth-tracking.md) | Medium | Medium | `todo` | None |
| 48 | [Prevent ReDoS in Filter Expressions](48-redos-filter-expression.md) | Medium | Low | `todo` | None |

**Sub-Phase Duration:** For v1.0.1 or v1.1.0
**Deliverables:** Robust number parsing, bounded memory, depth limits, ReDoS protection

### Phase 10.3: Code Quality & Optimization

| # | Task | Priority | Complexity | Status | Dependencies |
|---|------|----------|------------|--------|--------------|
| 43 | [Optimize isAssociativeArray() Method](43-optimize-associative-array-check.md) | Low | Low | `todo` | None |
| 44 | [Cache PathExpression Analysis](44-cache-pathexpression-analysis.md) | Low | Low | `todo` | Task 39 |
| 45 | [Review PHPStan Ignore Comments](45-review-phpstan-ignores.md) | Low | Low-Medium | `todo` | None |
| 46 | [Set IOException File Path Consistently](46-ioexception-filepath-consistency.md) | Low | Low | `todo` | None |
| 47 | [Remove Unused Config Constants](47-unused-config-constants.md) | Low | Low | `todo` | Task 26 |
| 49 | [Optimize readChunk() Concatenation](49-optimize-readchunk-concatenation.md) | Low | Low | `todo` | None |

**Sub-Phase Duration:** Can be deferred to v1.1.0 or v1.2.0
**Deliverables:** Cleaner code, better performance, reduced technical debt

**Overall Phase Duration:** ~2-3 weeks (depending on priorities)

---

## Phase 11: Coverage Improvement to 98%+ (High Priority)

Improve test coverage from 97.4% to maximum practical level (~98-98.5%). After comprehensive analysis, 100% coverage is not achievable or valuable - remaining code consists of defensive error handling, coverage tool limitations, and race conditions. See [coverage-analysis-100-percent.md](coverage-analysis-100-percent.md) for detailed analysis.

### Phase 11.1: High-Priority Coverage Improvements (Recommended)

Quick wins that improve coverage with meaningful tests.

| # | Task | Priority | Complexity | Status | Coverage Gain |
|---|------|----------|------------|--------|---------------|
| 50 | [Verify PathEvaluator Coverage](50-verify-pathevaluator-coverage.md) | High | Low | `todo` | +0.1% |
| 52 | [Add UTF-8 Character Tests](52-utf8-character-coverage.md) | High | Low | `todo` | +0.5% |
| 54 | [StreamReader Edge Cases](54-streamreader-edge-cases.md) | High | Low | `todo` | +0.1% |

**Sub-Phase Duration:** ~2-3 hours
**Deliverables:** +0.7% coverage improvement (97.4% → 98.1%)
**Recommendation:** ✅ Execute these tasks for best ROI

### Phase 11.2: Medium-Priority Coverage Improvements (Optional)

Edge cases that are reachable but uncommon in practice.

| # | Task | Priority | Complexity | Status | Coverage Gain |
|---|------|----------|------------|--------|---------------|
| 51 | [PathParser Edge Cases](51-pathparser-edge-cases.md) | Medium | Medium | `todo` | +0.2% |
| 53 | [Complex Streaming Patterns](53-complex-streaming-patterns.md) | Medium | High | `todo` | +0.3% |

**Sub-Phase Duration:** ~4-6 hours
**Deliverables:** +0.5% coverage improvement (98.1% → 98.6%)
**Recommendation:** ⚠️ Optional - diminishing returns

### Phase 11.3: Documentation

Document intentionally uncovered code.

| # | Task | Priority | Complexity | Status | Coverage Gain |
|---|------|----------|------------|--------|---------------|
| 55 | [Document Defensive Code](55-document-defensive-code.md) | Low | Low | `todo` | 0% |

**Sub-Phase Duration:** ~1 hour
**Deliverables:** Code comments and DEFENSIVE_CODE.md explaining uncovered defensive code
**Recommendation:** ✅ Always execute for maintainability

**Overall Phase Duration:** ~2-10 hours (depending on priorities)
**Expected Final Coverage:** 98.1% (Phase 11.1) or 98.6% (Phase 11.1 + 11.2)

---

## Quick Reference

### Critical Path (Minimum Viable Product)
To get a working MVP, complete these tasks in order:
1. **Phase 1** (Tasks 01-03): Foundation
2. **Phase 2** (Tasks 04-06): Core Infrastructure
3. **Phase 3** (Tasks 07-09): Basic Reading (skip ItemIterator)
4. **Phase 4** (Tasks 11-13): Basic Writing
5. **Phase 5.5** (Task 22): Fix Large Array Bug ⚠️ **CRITICAL**
6. **Phase 6** (Tasks 16-17): Unit Tests

**MVP Duration:** ~6 weeks

### Production Ready
For production-ready implementation:
1. Complete MVP path above
2. **Task 22** (Critical bug fix) - MUST be completed
3. Tasks 18-19 (Integration tests and benchmarks)
- **Total Duration:** ~8-10 weeks

### Full Feature Set
For complete implementation with all features:
- All tasks (1-49)
- **Total Duration:** ~10-12 weeks + analysis fixes

### Coverage Improvement (Phase 11)
**For immediate improvement (recommended):**
- Tasks 50, 52, 54 (High-priority) - Quick wins → 98.1% coverage

**For maximum coverage (optional):**
- Tasks 51, 53 (Medium-priority) - Edge cases → 98.6% coverage
- Task 55 (Documentation) - Always do for maintainability

**Note:** 100% coverage not achievable - remaining 1.4-1.9% is defensive/unreachable code

### Analysis Report Fixes (Phase 10)
**For v1.0.1 patch release:**
- Tasks 37-39 (Critical issues) - Must fix
- Tasks 40-42, 48 (Security/robustness) - Recommended

**For v1.1.0 minor release:**
- Tasks 43-47, 49 (Optimizations/cleanup) - Nice to have

### Recommended Order
1. Start with Phase 1 (foundation)
2. Complete Phase 2 (infrastructure)
3. Choose either Phase 3 (readers) OR Phase 4 (writers) based on priority
4. Complete the other phase
5. Add Phase 5 (advanced features) as needed
6. Complete Phase 6 (testing) throughout development
7. Finish with Phase 7 (documentation)

---

## Task Status Legend

- `todo` - Not started
- `in_progress` - Currently being worked on
- `done` - Completed and tested

---

## Complexity Ratings

- **Low** - Straightforward implementation, ~1-2 days
- **Medium** - Moderate complexity, ~3-5 days
- **High** - Complex implementation, ~1-2 weeks

---

## Notes

- Tasks should generally be completed in numerical order within each phase
- Some tasks can be parallelized (e.g., reader and writer implementations)
- Testing should be done alongside implementation, not just at the end
- Performance optimization is optional but recommended
- JSONPath engine is optional but adds significant value

---

**Last Updated:** 2025-12-11
**Document Version:** 1.7

## Recent Changes
- **2025-12-11**: Added Phase 11 (Coverage Improvement to 98%+) - 5 new tasks (50-55) to improve coverage from 97.4% to maximum practical level. Comprehensive analysis shows 100% coverage is not achievable (defensive code, tool limitations, race conditions). Recommended: Execute Phase 11.1 (tasks 50, 52, 54) for 98.1% coverage in 2-3 hours.
- **2025-12-11**: Updated Phase 9 status to Complete - Maximum practical coverage (97.4%) achieved, remaining 2.6% is defensive/unreachable code.
- **2025-12-11**: Added Phase 10 (Analysis Report Fixes) - 13 new tasks (37-49) to address issues found in comprehensive code analysis (ANALYSIS_REPORT.md, Dec 8, 2025). Critical issues include stream position handling, UTF-16 validation, and negative index documentation. Security improvements include integer overflow handling, cache limits, and ReDoS prevention. Code quality improvements include optimizations and cleanup.
- **2025-12-05**: **Completed Task 26** (Remove Writer for v1.0 Release) - Writer feature preserved in `feature/writer` branch, main branch is now reader-only. All tests passing, benchmarks working, v1.0 ready for release.
- **2025-12-05**: Added Task 26 (Remove Writer for v1.0 Release) - Preserve writer in `feature/writer` branch and remove from main for reader-only v1.0 release
- **2025-12-04**: Added Task 25 (Complex Pattern Streaming) - Low priority future optimization to extend streaming support to complex patterns like `$.users[*].name`, nested wildcards, and filter expressions
- **2025-12-04**: **Completed Task 24** (Streaming JSONPath Memory Optimization) - Implemented hybrid approach with true streaming for simple patterns like `$.Ads[*]` (0 MB memory delta) and PathFilter fallback for complex patterns. All 126 JSONPath tests passing.
- **2025-12-04**: Added Task 24 (Streaming JSONPath Memory Optimization) - Critical memory issue where JSONPath expressions like `$.Ads[*]` buffer entire arrays in memory instead of streaming
- **2025-12-01**: Added Task 23 (JSONPath Validation & Edge Cases) - Comprehensive testing using data-10.json and data-100.json with edge case coverage
- **2025-12-01**: Added Task 22 (Fix Large Array Parsing Bug) - Critical bug discovered during Task 15 (Performance Optimization)
- **2025-11-30**: Initial task breakdown created
