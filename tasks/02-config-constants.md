---
title: Configuration Constants
status: done
priority: High
description: Implement the JsonStream\Config class with all configuration constants defined in the API specification.
---

## Objectives
- Create Config class with all constants
- Define buffer size limits (MIN, DEFAULT, MAX)
- Define depth limits
- Define parser mode constants
- Define encoding option constants (for future use)

## Deliverables
1. `src/Config.php` file with:
   - Buffer size constants (1KB min, 8KB default, 1MB max)
   - Depth limit constants (1 min, 512 default, 4096 max)
   - Parser mode constants (STRICT, RELAXED)
   - Encoding option constants

## API Reference
See API_SIGNATURE.md lines 1465-1520

## Dependencies
- Task 01: Project Setup

## Estimated Complexity
**Low** - Simple constant definitions

## Acceptance Criteria
- [x] Config class created with proper namespace
- [x] All constants defined as per specification
- [x] Constants are properly typed (PHP 8.0+)
- [x] Class is documented with PHPDoc
