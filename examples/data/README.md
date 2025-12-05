# Sample Data Files

This directory contains sample JSON data files used by the examples.

## Files

- **users.json** - Array of 1000 user objects with id, name, email, age, and active status
- **nested-data.json** - Complex nested structure with users and posts arrays
- **small-sample.json** - Small 10-item array for quick testing
- **generated-output.json** - Created by example 04-write-large-file.php
- **nested-structure.json** - Created by example 05-nested-structures.php

## Regenerating Data

To regenerate these sample files, run:

```bash
php examples/generate-sample-data.php
```

## File Sizes

- users.json: ~50KB
- nested-data.json: ~30KB
- small-sample.json: ~200 bytes
