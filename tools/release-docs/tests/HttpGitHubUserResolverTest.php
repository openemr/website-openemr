<?php

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use OpenEMR\ReleaseDocs\HttpGitHubUserResolver;
use PHPUnit\Framework\TestCase;

final class HttpGitHubUserResolverTest extends TestCase
{
    public function testResolvesNameFromApiResponse(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode(['login' => 'bradymiller', 'name' => 'Brady Miller'])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $map = (new HttpGitHubUserResolver($client))->resolveNames(['bradymiller']);

        self::assertSame(['bradymiller' => 'Brady Miller'], $map);
    }

    public function testDedupesRepeatedUsernamesAndCallsApiOncePerUnique(): void
    {
        $captured = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode(['name' => 'Brady Miller'])),
        ]));
        $stack->push(Middleware::mapRequest(function (Request $r) use (&$captured): Request {
            $captured[] = $r->getUri()->__toString();
            return $r;
        }));
        $client = new Client(['handler' => $stack]);

        // Same username 5x should trigger exactly one API call.
        $map = (new HttpGitHubUserResolver($client))->resolveNames(array_fill(0, 5, 'bradymiller'));

        self::assertCount(1, $captured, 'API called exactly once per unique username');
        self::assertSame(['bradymiller' => 'Brady Miller'], $map);
    }

    public function testFallsBackToUsernameOn404(): void
    {
        $mock = new MockHandler([new Response(404)]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $map = (new HttpGitHubUserResolver($client))->resolveNames(['ghost-user']);

        self::assertSame(['ghost-user' => 'ghost-user'], $map);
    }

    public function testFallsBackToUsernameOn403RateLimit(): void
    {
        $mock = new MockHandler([new Response(403, ['X-RateLimit-Remaining' => '0'])]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $map = (new HttpGitHubUserResolver($client))->resolveNames(['bradymiller']);

        self::assertSame(['bradymiller' => 'bradymiller'], $map);
    }

    public function testFallsBackToUsernameWhenNameFieldMissing(): void
    {
        // User has no display name set on their GitHub profile.
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode(['login' => 'ghostacct'])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $map = (new HttpGitHubUserResolver($client))->resolveNames(['ghostacct']);

        self::assertSame(['ghostacct' => 'ghostacct'], $map);
    }

    public function testFallsBackToUsernameWhenNameFieldEmpty(): void
    {
        // `name` present but blank -- treat as absent.
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode(['name' => '   '])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $map = (new HttpGitHubUserResolver($client))->resolveNames(['spaceyacct']);

        self::assertSame(['spaceyacct' => 'spaceyacct'], $map);
    }

    public function testFallsBackToUsernameOnNetworkFailure(): void
    {
        // ConnectException = DNS failure / connection refused / timeout.
        $mock = new MockHandler([new ConnectException('cannot connect', new Request('GET', 'x'))]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $map = (new HttpGitHubUserResolver($client))->resolveNames(['bradymiller']);

        self::assertSame(['bradymiller' => 'bradymiller'], $map);
    }

    public function testSendsAuthorizationHeaderWhenTokenProvided(): void
    {
        $captured = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode(['name' => 'Whoever'])),
        ]));
        $stack->push(Middleware::mapRequest(function (Request $r) use (&$captured): Request {
            $captured[] = $r->getHeaderLine('Authorization');
            return $r;
        }));
        $client = new Client(['handler' => $stack]);

        (new HttpGitHubUserResolver($client, 'ghp_fake_token'))->resolveNames(['bradymiller']);

        self::assertSame(['Bearer ghp_fake_token'], $captured);
    }

    public function testOmitsAuthorizationHeaderWhenTokenNull(): void
    {
        $captured = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode(['name' => 'Whoever'])),
        ]));
        $stack->push(Middleware::mapRequest(function (Request $r) use (&$captured): Request {
            $captured[] = $r->getHeaderLine('Authorization');
            return $r;
        }));
        $client = new Client(['handler' => $stack]);

        (new HttpGitHubUserResolver($client, null))->resolveNames(['bradymiller']);

        self::assertSame([''], $captured);
    }

    public function testUrlEncodesUsername(): void
    {
        $captured = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode(['name' => 'Test'])),
        ]));
        $stack->push(Middleware::mapRequest(function (Request $r) use (&$captured): Request {
            $captured[] = $r->getUri()->__toString();
            return $r;
        }));
        $client = new Client(['handler' => $stack]);

        // Not a legal GitHub username, but the resolver must be safe
        // regardless -- guards against upstream data flowing into a URL.
        (new HttpGitHubUserResolver($client))->resolveNames(['weird/name']);

        self::assertSame(['https://api.github.com/users/weird%2Fname'], $captured);
    }
}
