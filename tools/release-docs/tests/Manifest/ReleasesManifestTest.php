<?php

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Tests\Manifest;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReleasesManifestTest extends TestCase
{
    private const SCHEMA_PATH = __DIR__ . '/../../../../data/releases.schema.json';
    private const MANIFEST_PATH = __DIR__ . '/../../../../data/releases.json';

    public function testCommittedManifestValidates(): void
    {
        $result = self::validator()->validate(self::loadJson(self::MANIFEST_PATH), self::loadSchema());

        if ($result->hasError()) {
            $error = $result->error();
            $formatted = $error instanceof ValidationError
                ? json_encode((new ErrorFormatter())->format($error), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : 'unknown';
            self::fail("data/releases.json failed validation: $formatted");
        }

        self::assertFalse($result->hasError());
    }

    public function testDraftReleaseRequiresNullReleasedAt(): void
    {
        $manifest = self::object([
            '8.1.0' => [
                'status' => 'DRAFT',
                'branch' => 'rel-810',
                'sha' => 'abc1234567890abcdef0123456789abcdef01234',
                'released_at' => '2026-04-29',
            ],
        ]);

        $result = self::validator()->validate($manifest, self::loadSchema());

        self::assertTrue(
            $result->hasError(),
            'DRAFT entry with non-null released_at must fail validation',
        );
    }

    public function testFinalReleaseRequiresDateReleasedAt(): void
    {
        $manifest = self::object([
            '8.0.0' => [
                'status' => 'FINAL',
                'branch' => 'rel-800',
                'sha' => 'b91b73600327acb46252b9fce7d04467eea126fd',
                'released_at' => null,
            ],
        ]);

        $result = self::validator()->validate($manifest, self::loadSchema());

        self::assertTrue(
            $result->hasError(),
            'FINAL entry with null released_at must fail validation',
        );
    }

    public function testInvalidVersionKeyFails(): void
    {
        $manifest = self::object([
            'rel-810' => [
                'status' => 'DRAFT',
                'branch' => 'rel-810',
                'sha' => 'abc1234567890abcdef0123456789abcdef01234',
                'released_at' => null,
            ],
        ]);

        $result = self::validator()->validate($manifest, self::loadSchema());

        self::assertTrue($result->hasError(), 'Non-version key must fail validation');
    }

    public function testShortShaFails(): void
    {
        $manifest = self::object([
            '8.0.0' => [
                'status' => 'FINAL',
                'branch' => 'rel-800',
                'sha' => 'abc1234',
                'released_at' => '2026-02-13',
            ],
        ]);

        $result = self::validator()->validate($manifest, self::loadSchema());

        self::assertTrue($result->hasError(), 'Short SHA must fail validation');
    }

    private static function validator(): Validator
    {
        $validator = new Validator();
        $validator->setMaxErrors(20);

        return $validator;
    }

    /**
     * @param array<string, array<string, scalar|null>> $data
     */
    private static function object(array $data): object
    {
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        $decoded = json_decode($encoded, false, 512, JSON_THROW_ON_ERROR);
        if (!is_object($decoded)) {
            throw new RuntimeException('Helper must produce a JSON object');
        }

        return $decoded;
    }

    private static function loadJson(string $path): mixed
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Could not read $path");
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
}
