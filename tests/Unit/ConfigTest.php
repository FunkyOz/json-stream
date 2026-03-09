<?php

declare(strict_types=1);

use JsonStream\Config;

describe('Config', function (): void {
    describe('buffer size constants', function (): void {
        it('defines MIN_BUFFER_SIZE', function (): void {
            expect(Config::MIN_BUFFER_SIZE)->toBe(1024);
        });

        it('defines DEFAULT_BUFFER_SIZE', function (): void {
            expect(Config::DEFAULT_BUFFER_SIZE)->toBe(8192);
        });

        it('defines MAX_BUFFER_SIZE', function (): void {
            expect(Config::MAX_BUFFER_SIZE)->toBe(1048576);
        });

        it('has valid buffer size hierarchy', function (): void {
            expect(Config::MIN_BUFFER_SIZE)
                ->toBeLessThan(Config::DEFAULT_BUFFER_SIZE);
            expect(Config::DEFAULT_BUFFER_SIZE)
                ->toBeLessThan(Config::MAX_BUFFER_SIZE);
        });
    });

    describe('depth constants', function (): void {
        it('defines MIN_DEPTH', function (): void {
            expect(Config::MIN_DEPTH)->toBe(1);
        });

        it('defines DEFAULT_MAX_DEPTH', function (): void {
            expect(Config::DEFAULT_MAX_DEPTH)->toBe(512);
        });

        it('defines MAX_DEPTH', function (): void {
            expect(Config::MAX_DEPTH)->toBe(4096);
        });

        it('has valid depth hierarchy', function (): void {
            expect(Config::MIN_DEPTH)
                ->toBeLessThan(Config::DEFAULT_MAX_DEPTH);
            expect(Config::DEFAULT_MAX_DEPTH)
                ->toBeLessThan(Config::MAX_DEPTH);
        });
    });

    describe('mode constants', function (): void {
        it('defines MODE_STRICT', function (): void {
            expect(Config::MODE_STRICT)->toBe(1);
        });
    });

    describe('instantiation', function (): void {
        it('cannot be instantiated', function (): void {
            $reflection = new ReflectionClass(Config::class);
            $constructor = $reflection->getConstructor();

            expect($constructor)->not->toBeNull();
            expect($constructor->isPrivate())->toBeTrue();
        });
    });
});
