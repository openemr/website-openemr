<?php

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Tests\Dispatch;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DispatchSchemaTest extends TestCase
{
    private const SCHEMA_PATH = __DIR__ . '/../../contracts/dispatch.schema.json';
    private const FIXTURE_DIR = __DIR__ . '/../fixtures/dispatch';

    /**
     * @return iterable<string, array{string}>
     */
    public static function validFixtures(): iterable
    {
        foreach (self::fixtureFiles('valid-*.json') as $path) {
            yield basename($path) => [$path];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidFixtures(): iterable
    {
        foreach (self::fixtureFiles('invalid-*.json') as $path) {
            yield basename($path) => [$path];
        }
    }

    #[DataProvider('validFixtures')]
    public function testValidFixturePasses(string $fixturePath): void
    {
        $result = self::validator()->validate(self::loadJson($fixturePath), self::loadSchema());

        if ($result->hasError()) {
            $error = $result->error();
            $formatted = $error instanceof ValidationError
                ? json_encode((new ErrorFormatter())->format($error), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : 'unknown';
            self::fail(sprintf('Expected %s to validate, got errors: %s', basename($fixturePath), $formatted));
        }

        self::assertFalse($result->hasError());
    }

    #[DataProvider('invalidFixtures')]
    public function testInvalidFixtureFails(string $fixturePath): void
    {
        $result = self::validator()->validate(self::loadJson($fixturePath), self::loadSchema());

        self::assertTrue(
            $result->hasError(),
            sprintf('Expected %s to fail validation but it passed', basename($fixturePath)),
        );
    }

    private static function validator(): Validator
    {
        $validator = new Validator();
        $validator->setMaxErrors(20);

        return $validator;
    }

    private static function loadJson(string $path): mixed
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Could not read fixture: $path");
        }

        return json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
    }

    private static function loadSchema(): object
    {
        $decoded = self::loadJson(self::SCHEMA_PATH);
        if (!is_object($decoded)) {
            throw new RuntimeException('Schema root must decode to an object');
        }

        return $decoded;
    }

    /**
     * @return list<string>
     */
    private static function fixtureFiles(string $pattern): array
    {
        $matches = glob(self::FIXTURE_DIR . '/' . $pattern);
        if ($matches === false) {
            throw new RuntimeException("glob failed for pattern: $pattern");
        }

        sort($matches);

        return $matches;
    }
}
