<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Benchmarks\PerformanceBenchmark;

// CAVEAT: Convert memory_usage to MB
$currentMemory = memory_get_usage() / (1024 ** 2);
if ($currentMemory < 256) {
    ini_set('memory_limit', 256 .'M');
}

// Run benchmarks
$benchmark = new PerformanceBenchmark();
$benchmark->run();
