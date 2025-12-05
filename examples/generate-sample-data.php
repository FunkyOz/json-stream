<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

/**
 * Generate Sample Data Files
 *
 * This script is no longer used. Sample data files are now pre-generated.
 * The examples directory includes pre-built JSON files for demonstration.
 *
 * To regenerate sample data files manually, you can use:
 * - php examples/01-read-large-file.php
 * - php examples/02-jsonpath-filtering.php
 * - etc.
 */
echo "=== Sample Data Files ===\n\n";
echo "Sample data files are pre-generated and included in the examples/data/ directory.\n";
echo "StreamWriter has been removed as part of the v1.0 release.\n\n";
echo "The following sample files are available:\n";
echo "  - users.json - Array of user objects\n";
echo "  - nested-data.json - Complex nested structure with users and posts\n";
echo "  - small-sample.json - Small 10-item array for quick testing\n\n";
echo "See examples/data/README.md for more information.\n";
