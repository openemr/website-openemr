<?php

/**
 * Validate inbound release-dispatch payloads against the canonical schema.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Dispatch;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use RuntimeException;

final class DispatchValidator
{
    public function __construct(
        private readonly string $schemaPath,
    ) {
    }

    /**
     * @return list<string> Empty when the payload validates; one entry per error otherwise.
     */
    public function validate(string $payloadJson): array
    {
        $payload = json_decode($payloadJson, false, 512, JSON_THROW_ON_ERROR);
        $schema = $this->loadSchema();

        $validator = new Validator();
        $validator->setMaxErrors(20);
        $result = $validator->validate($payload, $schema);

        if (!$result->hasError()) {
            return [];
        }

        $error = $result->error();
        if (!$error instanceof ValidationError) {
            return ['unknown validation error'];
        }

        $formatted = (new ErrorFormatter())->format($error);
        $errors = [];
        foreach ($formatted as $location => $messages) {
            $atLocation = is_string($location) ? $location : (string) $location;
            $items = is_array($messages) ? $messages : [$messages];
            foreach ($items as $message) {
                $errors[] = sprintf('%s: %s', $atLocation, is_string($message) ? $message : json_encode($message));
            }
        }

        return $errors;
    }

    private function loadSchema(): object
    {
        $contents = @file_get_contents($this->schemaPath);
        if ($contents === false) {
            throw new RuntimeException("dispatch schema not readable: $this->schemaPath");
        }

        $decoded = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
        if (!is_object($decoded)) {
            throw new RuntimeException('dispatch schema root must decode to an object');
        }

        return $decoded;
    }
}
