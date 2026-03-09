# Phase 10 Benchmark Report

**Date:** 2026-03-06
**Environment:** macOS Darwin 25.3.0, PHP 8.x
**Methodology:** 3 runs per configuration, median values reported. Temporary test files regenerated between runs.

## Summary

Phase 10 changes introduce **no measurable performance regression**. All benchmarks are within normal run-to-run variance (~1-3%). Memory usage is identical before and after.

---

## 1. Reading Benchmark (1MB JSON array, 1,000 objects)

| Metric | Before | After | Delta |
|--------|--------|-------|-------|
| JsonStream Time | 35.54 ms | 36.01 ms | +1.3% |
| JsonStream Memory | 2.00 MB | 2.00 MB | 0% |
| json_decode Time | ~1.00 ms | ~1.00 ms | — |

**Analysis:** The ~0.5 ms difference is within normal variance. The integer overflow check in `scanNumber()` adds a comparison per digit but the cost is negligible — a single `if ($intPart > $maxBeforeOverflow)` check is branch-predicted away for normal numbers.

---

## 2. Buffer Size Comparison (1MB file)

| Buffer Size | Before | After | Delta |
|-------------|--------|-------|-------|
| 8 KB | 34.40 ms | 34.93 ms | +1.5% |
| 16 KB | 34.49 ms | 34.98 ms | +1.4% |
| 32 KB | 34.84 ms | 34.75 ms | -0.3% |
| 64 KB | 34.68 ms | 35.22 ms | +1.6% |

**Analysis:** All within noise. The `readChunk()` optimization (array + implode vs string concatenation) has no measurable impact at these sizes because `readChunk()` is only used for small reads (4 bytes for `\uXXXX` escapes, 3-4 bytes for keywords). The optimization becomes relevant for larger chunk reads.

---

## 3. Memory Usage Benchmarks (Constant Memory Verification)

| File Size | Before | After | Delta |
|-----------|--------|-------|-------|
| 107 KB (500 objects) | 17.49 ms / 0 B | 17.48 ms / 0 B | 0% |
| 215 KB (1,000 objects) | 34.22 ms / 0 B | 34.98 ms / 0 B | +2.2% |
| 433 KB (2,000 objects) | 71.02 ms / 0 B | 70.90 ms / 0 B | -0.2% |

**Analysis:** Memory delta is 0 B in all cases — constant memory streaming is preserved. Time variations are within noise.

---

## 4. JSONPath Benchmarks (30MB file, `$.Ads[*]`)

| Metric | Before | After | Delta |
|--------|--------|-------|-------|
| JsonStream Time | 4.18 s | 4.19 s | +0.2% |
| JsonStream Memory | 6.00 MB | 6.00 MB | 0% |
| json_decode Time | 84.55 ms | 85.43 ms | — |
| json_decode Memory | 175.94 MB | 175.94 MB | — |

**Analysis:** No regression. The PathExpression analysis caching (Task 44) avoids redundant segment iteration, but since these methods are called infrequently per parse session, the improvement is not visible at the macro level. The PathFilter depth tracking (Task 42) adds a single integer comparison per recursion level — unmeasurable overhead.

---

## Impact Analysis by Task

| Task | Expected Impact | Measured Impact |
|------|----------------|-----------------|
| **37** Stream position reset | No perf impact (only runs on fluent methods) | None |
| **38** UTF-16 surrogate validation | Negligible (only on `\u` escapes) | None |
| **39** Negative index validation | No perf impact (constructor check only) | None |
| **40** Integer overflow detection | +1 comparison per digit parsed | < 1% (within noise) |
| **41** ObjectIterator cache limits | +1 count check per cached property | None (benchmark doesn't use ObjectIterator) |
| **42** PathFilter depth tracking | +1 int comparison per recursion | None |
| **43** isAssociativeArray inline | Removes 1 function call per walk | None (micro-optimization) |
| **44** PathExpression caching | Removes redundant segment iteration | None (called infrequently) |
| **45** PHPStan ignore review | No code changes | None |
| **46** IOException file path | No perf-critical path | None |
| **47** Remove unused constants | No runtime impact | None |
| **48** ReDoS prevention | 1 strlen check on filter construction | None |
| **49** readChunk optimization | Array+implode for chunk reads | None (chunks are small) |

---

## Conclusion

All Phase 10 changes are **performance-neutral**. The security hardening, validation, and code quality improvements add no measurable overhead. This is expected because:

1. **Hot path unchanged**: The core tokenization loop (`scanToken` → `readByte` → `peek`) is untouched
2. **Validation at construction**: Most new checks run once at object creation (not per-token)
3. **Branch prediction friendly**: The overflow check (`$intPart > $maxBeforeOverflow`) is false 99.99% of the time
4. **Micro-optimizations cancel out**: `readChunk` array optimization and `isAssociativeArray` inline are too small to measure

The library maintains its performance characteristics: ~35 ms for 1MB, ~70 ms for 2MB (linear scaling), with 0 B memory delta for streaming operations.
