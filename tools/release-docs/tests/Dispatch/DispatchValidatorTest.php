<?php

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Tests\Dispatch;

use OpenEMR\ReleaseDocs\Dispatch\DispatchValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DispatchValidatorTest extends TestCase
{
    private const SCHEMA_PATH = __DIR__ . '/../../contracts/dispatch.schema.json';
    private const FIXTURE_DIR = __DIR__ . '/../fixtures/dispatch';

    /**
     * @return iterable<string, array{string}>
     */
    public static function validFixtures(): iterable
    {
        foreach (self::glob('valid-*.json') as $path) {
            yield basename($path) => [$path];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidFixtures(): iterable
    {
        foreach (self::glob('invalid-*.json') as $path) {
            yield basename($path) => [$path];
        }
    }

    #[DataProvider('validFixtures')]
    public function testValidPayloadProducesNoErrors(string $fixturePath): void
    {
        $payload = (string) file_get_contents($fixturePath);
        $errors = (new DispatchValidator(self::SCHEMA_PATH))->validate($payload);

        self::assertSame([], $errors, 'expected no errors for ' . basename($fixturePath));
    }

    #[DataProvider('invalidFixtures')]
    public function testInvalidPayloadProducesErrors(string $fixturePath): void
    {
        $payload = (string) file_get_contents($fixturePath);
        $errors = (new DispatchValidator(self::SCHEMA_PATH))->validate($payload);

        self::assertNotSame([], $errors, 'expected errors for ' . basename($fixturePath));
    }

    /**
     * @return list<string>
     */
    private static function glob(string $pattern): array
    {
        $matches = glob(self::FIXTURE_DIR . '/' . $pattern);
        if ($matches === false) {
            throw new RuntimeException("glob failed for $pattern");
        }
        sort($matches);

        return $matches;
    }
}
