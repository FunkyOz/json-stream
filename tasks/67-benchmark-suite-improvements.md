---
title: Comprehensive Performance Benchmark Suite
status: todo
priority: Medium
description: Create a comprehensive benchmark suite that measures individual component throughput for tracking optimization progress
---

## Objectives
- Create granular benchmarks for each component (Lexer, Parser, BufferManager, PathEvaluator)
- Enable tracking of optimization progress across tasks
- Provide reproducible, statistically significant results

## Deliverables
1. Component-level benchmark scripts
2. Comparison framework (before/after with statistical analysis)
3. Baseline measurements for all components

## Technical Details

**Location:** `benchmarks/` directory

**Current State:**
The existing benchmarks (`benchmarks/PerformanceBenchmark.php`) measure end-to-end throughput but don't isolate individual components. This makes it hard to:
- Know which optimization had the most impact
- Detect regressions in specific components
- Target optimization effort effectively

**Proposed Benchmarks:**

### 1. Lexer Microbenchmarks
```php
// Tokenization-only throughput (no parsing)
class LexerBenchmark
{
    public function benchTokenize1MBArray(): void { /* tokenize without parsing */ }
    public function benchTokenizeStrings(): void { /* JSON with many string values */ }
    public function benchTokenizeNumbers(): void { /* JSON with many numeric values */ }
    public function benchTokenizeMixed(): void { /* realistic mixed JSON */ }
    public function benchSkipWhitespace(): void { /* pretty-printed JSON */ }
}
```

### 2. Parser Microbenchmarks
```php
class ParserBenchmark
{
    public function benchParseShallowArray(): void { /* [1,2,3,...,N] */ }
    public function benchParseDeepNesting(): void { /* {a:{b:{c:...}}} */ }
    public function benchParseObjectKeys(): void { /* many string keys */ }
    public function benchSkipValue(): void { /* skip large structures */ }
}
```

### 3. PathEvaluator Microbenchmarks
```php
class PathEvaluatorBenchmark
{
    public function benchSimpleProperty(): void { /* $.name matching */ }
    public function benchWildcard(): void { /* $.items[*] matching */ }
    public function benchDeepPath(): void { /* $.a.b.c.d matching */ }
    public function benchRecursive(): void { /* $..name matching */ }
    public function benchFilter(): void { /* $[?(@.x > 1)] matching */ }
}
```

### 4. End-to-End Regression Benchmarks
```php
class RegressionBenchmark
{
    // Fixed test data, deterministic results
    public function benchRead1MBArray(): void {}
    public function benchRead30MBJsonPath(): void {}
    public function benchStreamAndSkip(): void {}
}
```

### 5. Comparison Runner
```bash
# Run benchmarks on current branch
php benchmarks/run-suite.php --output=baseline.json

# Run after optimization
php benchmarks/run-suite.php --output=optimized.json

# Compare results
php benchmarks/compare.php baseline.json optimized.json
```

## Dependencies
- None (infrastructure task)

## Estimated Complexity
**Medium** - Creating meaningful, reproducible benchmarks

## Implementation Notes
- Use multiple runs (5-10) with median/percentile reporting
- Warm up PHP's JIT before measuring
- Use fixed-size test data generated deterministically
- Report both throughput (MB/s) and latency (ms/item)
- Consider using phpbench for structured benchmarking
- Must work in CI for regression detection

## Acceptance Criteria
- [ ] Component-level benchmarks for Lexer, Parser, PathEvaluator
- [ ] End-to-end regression benchmarks with fixed test data
- [ ] Comparison script that reports deltas with statistical significance
- [ ] Baseline measurements recorded
- [ ] Benchmarks are reproducible (< 5% variance between runs)
- [ ] Documentation for running benchmarks
