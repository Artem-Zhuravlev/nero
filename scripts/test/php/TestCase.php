<?php
declare(strict_types=1);

namespace Ocs\Test;

/** Minimal assertion base class for the dependency-free PHP runner (SPEC-021 REQ-TST-010). */
abstract class TestCase
{
    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }

    public function runSetUp(): void
    {
        $this->setUp();
    }

    public function runTearDown(): void
    {
        $this->tearDown();
    }

    protected function fail(string $message): void
    {
        throw new AssertionFailed($message);
    }

    /** @param mixed $expected @param mixed $actual */
    protected function assertSame($expected, $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $this->fail(($message !== '' ? $message . ': ' : '') . 'expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true));
        }
    }

    protected function assertTrue($value, string $message = ''): void
    {
        $this->assertSame(true, $value === true, $message !== '' ? $message : 'expected true');
    }

    protected function assertFalse($value, string $message = ''): void
    {
        $this->assertSame(false, $value !== false, $message !== '' ? $message : 'expected false');
    }

    protected function assertCount(int $expected, array $array, string $message = ''): void
    {
        $this->assertSame($expected, count($array), $message !== '' ? $message : 'count');
    }

    protected function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        if (strpos($haystack, $needle) === false) {
            $this->fail(($message !== '' ? $message . ': ' : '') . 'expected to find ' . var_export($needle, true) . ' in ' . var_export($haystack, true));
        }
    }

    /** @param string[] $lines */
    protected function assertLineMatches(string $regex, array $lines, string $message = ''): void
    {
        foreach ($lines as $l) {
            if (preg_match($regex, $l)) {
                return;
            }
        }
        $this->fail(($message !== '' ? $message . ': ' : '') . 'no line matches ' . $regex . ' in ' . json_encode($lines));
    }

    protected function assertThrows(string $class, callable $fn, string $messageRegex = ''): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            if (!($e instanceof $class)) {
                $this->fail('expected ' . $class . ' but got ' . get_class($e) . ': ' . $e->getMessage());
            }
            if ($messageRegex !== '' && !preg_match($messageRegex, $e->getMessage())) {
                $this->fail('exception message "' . $e->getMessage() . '" does not match ' . $messageRegex);
            }
            return;
        }
        $this->fail('expected exception ' . $class . ' was not thrown');
    }
}
