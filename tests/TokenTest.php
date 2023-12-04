<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Interfaces\Entity\TokenInterface;
use Symplicity\Outlook\Token;

class TokenTest extends TestCase
{
    protected Token $tokenHandler;

    public function setUp(): void
    {
        $logger = new Logger('outlook_calendar');
        $logger->pushHandler(new NullHandler());

        $this->tokenHandler = new Token('foo', 'bar', [
            'logger' => $logger
        ]);
    }

    /**
     * @dataProvider getStream
     * @param array $jwt
     * @param \Exception|null $exception
     * @throws \Exception
     */
    public function testRequest(array $jwt, ?\Exception $exception)
    {
        $code = $exception ? 400 : 200;
        $mock = new MockHandler([
            new Response($code, [], Utils::streamFor(json_encode($jwt))),
        ]);

        $provider = $this->getProvider($mock);

        if ($exception !== null) {
            $this->expectExceptionObject($exception);
        }

        $token = $this->tokenHandler->request('123', 'symplicity.com', provider: $provider);
        $this->assertInstanceOf(TokenInterface::class, $token);
        $this->assertNotEmpty($token->getAccessToken());
        $this->assertNotEmpty($token->getRefreshToken());
        $this->assertNotEmpty($token->getExpiresIn());
        $this->assertEquals('foobar@bar.com', $token->getEmailAddress());
        $this->assertEquals('Foo Bar', $token->getDisplayName());
        $this->assertInstanceOf(\DateTimeInterface::class, $token->tokenReceivedOn());
        $this->assertNotEmpty($token->getIdToken());
        $this->assertEquals('Foo Bar', (string) $token);
    }

    /**
     * @dataProvider getStream
     * @param array $jwt
     * @param \Exception|null $exception
     * @throws IdentityProviderException
     */
    public function testRefresh(array $jwt, ?\Exception $exception)
    {
        $code = $exception ? 400 : 200;
        $mock = new MockHandler([
            new Response($code, [], Utils::streamFor(json_encode($jwt))),
        ]);

        $provider = $this->getProvider($mock);

        if ($exception) {
            $this->expectExceptionMessage('Required option not passed: "access_token"');
        }

        $token = $this->tokenHandler->refresh('123', 'symplicity.com', $provider);
        $this->assertInstanceOf(TokenInterface::class, $token);
        $this->assertNotEmpty($token->getAccessToken());
        $this->assertNotEmpty($token->getRefreshToken());
        $this->assertNotEmpty($token->getExpiresIn());
    }

    public function testAuthorizationUrl()
    {
        $authUrl = $this->tokenHandler->getAuthorizationUrl(['abc'], 'test.com');
        $this->assertEquals('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?state=%5B%22abc%22%5D&scope=openid%20offline_access%20https%3A%2F%2Fgraph.microsoft.com%2Fcalendars.readwrite&response_type=code&approval_prompt=auto&redirect_uri=test.com&client_id=foo', $authUrl);

        $authUrl = $this->tokenHandler->getAuthorizationUrl(['123'], 'test.com');
        $this->assertEquals('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?state=%5B%22123%22%5D&scope=openid%20offline_access%20https%3A%2F%2Fgraph.microsoft.com%2Fcalendars.readwrite&response_type=code&approval_prompt=auto&redirect_uri=test.com&client_id=foo', $authUrl);
    }

    private function getProvider(MockHandler $mock): AbstractProvider
    {
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $options = [
            'urlAuthorize' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'urlAccessToken' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'urlResourceOwnerDetails' => 'https://graph.microsoft.com/oidc/userinfo'
        ];

        return new GenericProvider(
            options: $options,
            collaborators: ['httpClient' => $client]
        );
    }

    public static function getStream(): array
    {
        $payload = [
            'sub' => '123',
            'upn' => 'foobar@bar.com',
            'name' => 'Foo Bar'
        ];

        $jwt = JWT::encode($payload, 'bar', 'HS256');

        return [
            [
               [
                    "token_type" => "Bearer",
                    "scope" => "openid profile email https://graph.microsoft.com/Calendars.ReadWrite",
                    "ext_expires_in" => 4348,
                    "access_token" => $jwt,
                    "refresh_token" => $jwt,
                    "expires_in" => 10800,
                    "upn" => "foobar@bar.com",
                    "id_token" => "abc"
               ],
                null
            ],
            [
                [],
                new \InvalidArgumentException('Required option not passed: "access_token"')
            ]
        ];
    }
}
