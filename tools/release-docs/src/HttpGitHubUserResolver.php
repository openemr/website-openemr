<?php

/**
 * GitHub REST API implementation of GitHubUserResolver.
 *
 * One `GET /users/{username}` per unique username. Sequential (not
 * batched) because typical release ranges have 5-20 unique
 * contributors — plenty of rate-limit headroom (5000/hr authenticated,
 * 60/hr anonymous) and no meaningful latency benefit to GraphQL
 * batching at that scale.
 *
 * Never throws. On any failure (network, 404, 403, rate-limit,
 * malformed JSON, empty `name`), returns the input username as the
 * canonical name so the acknowledgements render still completes.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

final class HttpGitHubUserResolver implements GitHubUserResolver
{
    private const API_BASE = 'https://api.github.com/users/';
    private const REQUEST_TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly ?string $token = null,
    ) {
    }

    /**
     * Convenience factory: build with a default Guzzle client + read
     * the token from GITHUB_TOKEN env if present. Callers that need
     * to inject a mock client (tests) or use a custom token (bearer
     * for a GitHub App vs a PAT) should use the constructor directly.
     */
    public static function fromEnvironment(): self
    {
        $token = getenv('GITHUB_TOKEN');
        return new self(new Client(), $token !== false && $token !== '' ? $token : null);
    }

    public function resolveNames(array $usernames): array
    {
        $result = [];
        // Dedup first: one API call per unique username, per-run cache
        // is the returned map itself. Callers apply the map back to
        // per-record data without re-hitting the API.
        foreach (array_unique($usernames) as $username) {
            $result[$username] = $this->fetchDisplayName($username);
        }
        return $result;
    }

    private function fetchDisplayName(string $username): string
    {
        try {
            $headers = [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'openemr-release-docs',
            ];
            if ($this->token !== null) {
                $headers['Authorization'] = 'Bearer ' . $this->token;
            }
            $response = $this->client->request('GET', self::API_BASE . rawurlencode($username), [
                'headers' => $headers,
                'http_errors' => false,
                'timeout' => self::REQUEST_TIMEOUT_SECONDS,
            ]);
            if ($response->getStatusCode() !== 200) {
                return $username;
            }
            $body = (string) $response->getBody();
            /** @var mixed $decoded */
            $decoded = json_decode($body, associative: true);
            if (!is_array($decoded) || !isset($decoded['name']) || !is_string($decoded['name'])) {
                return $username;
            }
            $name = trim($decoded['name']);
            return $name === '' ? $username : $name;
        } catch (\Throwable) {
            return $username;
        }
    }
}
