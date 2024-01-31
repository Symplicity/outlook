<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities\CalendarView;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions as GuzzleHttpOptions;
use Microsoft\Graph\Core\Authentication\GraphPhpLeagueAuthenticationProvider;
use Microsoft\Graph\Core\GraphClientFactory;
use Microsoft\Graph\Core\NationalCloud;
use Microsoft\Graph\GraphRequestAdapter;
use Microsoft\Graph\GraphServiceClient as MSGraphServiceClient;
use Microsoft\Kiota\Abstractions\RequestAdapter;
use Symplicity\Outlook\AuthorizationContextTrait;
use Symplicity\Outlook\Interfaces\Utilities\CalendarView\CalendarViewParamsInterface;
use Symplicity\Outlook\Middleware\CalendarViewDeltaTokenQueryParamMiddleware;

class GraphServiceCalendarView
{
    use AuthorizationContextTrait;

    public const DEFAULT_CONNECT_TIMEOUT = 3;
    public const DEFAULT_TIMEOUT = 4;
    public const BASE_URI = NationalCloud::GLOBAL;
    public const ENABLE_HTTP_ERROR = false;
    public const HTTP_VERIFY = false;
    public const GUZZLE_HTTP_CONFIG_KEY = 'guzzleConfig';

    protected ?RequestAdapter $requestAdapter;
    protected ?Client $httpClient = null;

    public function __construct(private readonly string $clientId, private readonly string $clientSecret, private readonly string $token) // @phpstan-ignore-line
    {
    }

    public function client(CalendarViewParamsInterface $params): MSGraphServiceClient
    {
        $tokenRequestContext = $this->getClientCredentialContext();

        $client = $this->httpClient;

        if (empty($client)) {
            $handlerStack = GraphClientFactory::getDefaultHandlerStack();
            $handlerStack->push(CalendarViewDeltaTokenQueryParamMiddleware::init([
                'deltaToken' => $params->getDeltaToken()
            ]));

            $guzzleConfig = array_merge(
                static::getDefaultConfig(),
                $params->getRequestOptions(),
                ['handler' => $handlerStack]
            );

            $client = GraphClientFactory::createWithConfig($guzzleConfig);
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

    public function getRequestAdapter(): ?RequestAdapter
    {
        return $this->requestAdapter;
    }

    public function setHttpClient(?Client $client): GraphServiceCalendarView
    {
        $this->httpClient = $client;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
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
