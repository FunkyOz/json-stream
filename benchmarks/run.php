<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Benchmarks\PerformanceBenchmark;

// Run benchmarks
$benchmark = new PerformanceBenchmark();
$benchmark->run();
