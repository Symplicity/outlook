<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities\CalendarView;

use GuzzleHttp\ClientInterface;
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

    public const DEFAULT_CONNECT_TIMEOUT = 3;
    public const DEFAULT_TIMEOUT = 4;
    public const BASE_URI = NationalCloud::GLOBAL;
    public const ENABLE_HTTP_ERROR = false;
    public const HTTP_VERIFY = false;

    protected ?RequestAdapter $requestAdapter;
    protected ?ClientInterface $httpClient = null;

    public function __construct(private readonly string $clientId, private readonly string $clientSecret, private readonly string $token)
    {
    }

    public function client(CalendarViewParams $params): MSGraphServiceClient
    {
        $tokenRequestContext = $this->getClientCredentialContext();

        $client = $this->httpClient;

        if (empty($client)) {
            $handlerStack = GraphClientFactory::getDefaultHandlerStack();
            $handlerStack->push(CalendarViewDeltaTokenQueryParamMiddleware::init([
                'deltaToken' => $params->getDeltaToken()
            ]));

            $client = GraphClientFactory::createWithConfig(array_merge(
                static::getDefaultConfig(),
                ['handler' => $handlerStack]
            ));
        }

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

    public function setHttpClient(?ClientInterface $client): GraphServiceCalendarView
    {
        $this->httpClient = $client;
        return $this;
    }

    protected static function getDefaultConfig(): array
    {
        $config = [
            GuzzleHttpOptions::CONNECT_TIMEOUT => static::DEFAULT_CONNECT_TIMEOUT,
            GuzzleHttpOptions::TIMEOUT => static::DEFAULT_TIMEOUT,
            GuzzleHttpOptions::HEADERS => [
                'Content-Type' => 'application/json',
            ],
            GuzzleHttpOptions::HTTP_ERRORS => static::ENABLE_HTTP_ERROR,
            'base_uri' => static::BASE_URI,
            GuzzleHttpOptions::VERIFY => static::HTTP_VERIFY
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
