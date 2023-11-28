<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities\CalendarView;

use GuzzleHttp\RequestOptions as GuzzleHttpOptions;
use Microsoft\Graph\Core\Authentication\GraphPhpLeagueAuthenticationProvider;
use Microsoft\Graph\Core\GraphClientFactory;
use Microsoft\Graph\Core\NationalCloud;
use Microsoft\Graph\GraphRequestAdapter;
use Microsoft\Graph\GraphServiceClient as MSGraphServiceClient;
use Microsoft\Kiota\Abstractions\RequestAdapter;
use Symplicity\Outlook\AuthorizationContextTrait;
use Symplicity\Outlook\Middleware\CalendarViewDeltaTokenQueryParamMiddleware;

class GraphServiceCalendarView
{
    use AuthorizationContextTrait;

    private ?RequestAdapter $requestAdapter;

    public function __construct(private readonly string $clientId, private readonly string $clientSecret, private readonly string $token)
    {
    }

    public function client(CalendarViewParams $params): MSGraphServiceClient
    {
        $tokenRequestContext = $this->getClientCredentialContext();

        $handlerStack = GraphClientFactory::getDefaultHandlerStack();
        $handlerStack->push(CalendarViewDeltaTokenQueryParamMiddleware::init([
            'deltaToken' => $params->getDeltaToken()
        ]));

        $client = GraphClientFactory::createWithConfig(array_merge(
            static::getDefaultConfig(),
            ['handler' => $handlerStack]
        ));

        $this->requestAdapter = new GraphRequestAdapter(
            new GraphPhpLeagueAuthenticationProvider($tokenRequestContext),
            $client
        );

        return new MSGraphServiceClient(
            tokenRequestContext: $tokenRequestContext,
            requestAdapter: $this->requestAdapter
        );
    }

    public function getRequestAdapter(): RequestAdapter
    {
        return $this->requestAdapter;
    }

    protected static function getDefaultConfig(): array
    {
        $config = [
            GuzzleHttpOptions::CONNECT_TIMEOUT => 3,
            GuzzleHttpOptions::TIMEOUT => 4,
            GuzzleHttpOptions::HEADERS => [
                'Content-Type' => 'application/json',
            ],
            GuzzleHttpOptions::HTTP_ERRORS => false,
            'base_uri' => NationalCloud::GLOBAL,
        ];

        if (extension_loaded('curl') && defined('CURL_VERSION_HTTP2')) {
            $curlVersion = curl_version();
            if ($curlVersion && ($curlVersion['features'] & CURL_VERSION_HTTP2) === CURL_VERSION_HTTP2) {
                $config[GuzzleHttpOptions::VERSION] = '2';
            }
        }

        return $config;
    }
}
