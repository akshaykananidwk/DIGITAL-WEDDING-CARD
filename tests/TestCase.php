<?php

/**
 * A very small test harness.
 *
 * Deliberately dependency-free: the whole point of this application is that it
 * runs on plain PHP hosting, so its tests must run there too - no Composer,
 * no PHPUnit, no Node.
 */

declare(strict_types=1);

namespace Tests;

abstract class TestCase
{
    /** @var array<int,array{name:string,ok:bool,message:string}> */
    protected array $results = [];

    abstract public function name(): string;

    abstract public function run(): void;

    /** @return array<int,array{name:string,ok:bool,message:string}> */
    public function results(): array
    {
        return $this->results;
    }

    // ------------------------------------------------------------------
    //  Assertions
    // ------------------------------------------------------------------

    protected function pass(string $name, string $message = ''): void
    {
        $this->results[] = ['name' => $name, 'ok' => true, 'message' => $message];
    }

    protected function fail(string $name, string $message): void
    {
        $this->results[] = ['name' => $name, 'ok' => false, 'message' => $message];
    }

    protected function assertTrue(string $name, bool $condition, string $detail = ''): void
    {
        if ($condition) {
            $this->pass($name);
            return;
        }
        $this->fail($name, $detail === '' ? 'Expected true.' : $detail);
    }

    protected function assertFalse(string $name, bool $condition, string $detail = ''): void
    {
        if (!$condition) {
            $this->pass($name);
            return;
        }
        $this->fail($name, $detail === '' ? 'Expected false.' : $detail);
    }

    protected function assertSame(string $name, mixed $expected, mixed $actual): void
    {
        if ($expected === $actual) {
            $this->pass($name);
            return;
        }
        $this->fail($name, 'Expected ' . $this->describe($expected) . ', got ' . $this->describe($actual) . '.');
    }

    protected function assertEquals(string $name, mixed $expected, mixed $actual): void
    {
        if ($expected == $actual) {
            $this->pass($name);
            return;
        }
        $this->fail($name, 'Expected ' . $this->describe($expected) . ', got ' . $this->describe($actual) . '.');
    }

    protected function assertContains(string $name, string $needle, string $haystack): void
    {
        if (str_contains($haystack, $needle)) {
            $this->pass($name);
            return;
        }
        $this->fail($name, 'Missing "' . $needle . '" in ' . $this->excerpt($haystack));
    }

    protected function assertNotContains(string $name, string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            $this->pass($name);
            return;
        }
        $this->fail($name, 'Unexpected "' . $needle . '" in ' . $this->excerpt($haystack));
    }

    protected function assertGreaterThan(string $name, float $minimum, float $actual): void
    {
        $actual > $minimum
            ? $this->pass($name, (string) $actual)
            : $this->fail($name, 'Expected more than ' . $minimum . ', got ' . $actual . '.');
    }

    protected function assertMatches(string $name, string $pattern, string $subject): void
    {
        preg_match($pattern, $subject) === 1
            ? $this->pass($name)
            : $this->fail($name, 'Pattern ' . $pattern . ' did not match ' . $this->excerpt($subject));
    }

    /** Assert that a callable throws, optionally with a message fragment. */
    protected function assertThrows(string $name, callable $callback, string $expectedFragment = ''): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            if ($expectedFragment === '' || str_contains($e->getMessage(), $expectedFragment)) {
                $this->pass($name, $e::class);
                return;
            }
            $this->fail($name, 'Threw ' . $e::class . ' with "' . $e->getMessage() . '", expected "' . $expectedFragment . '".');
            return;
        }
        $this->fail($name, 'Expected an exception, none was thrown.');
    }

    private function describe(mixed $value): string
    {
        return match (true) {
            is_bool($value)  => $value ? 'true' : 'false',
            is_null($value)  => 'null',
            is_scalar($value) => (string) $value,
            default          => $this->excerpt((string) json_encode($value)),
        };
    }

    private function excerpt(string $value): string
    {
        $clean = preg_replace('/\s+/', ' ', $value) ?? $value;
        return mb_strimwidth($clean, 0, 160, '…');
    }
}
