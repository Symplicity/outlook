<?php

declare(strict_types=1);

namespace Symplicity\Outlook;

use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Entities\Token as TokenEntity;
use Symplicity\Outlook\Http\Connection;
use Symplicity\Outlook\Http\RequestOptions;
use Symplicity\Outlook\Interfaces\Entity\TokenInterface as TokenEntityInterface;
use Symplicity\Outlook\Interfaces\Http\ConnectionInterface;
use Symplicity\Outlook\Interfaces\TokenInterface;
use Symplicity\Outlook\Utilities\RequestType;
use Symplicity\Outlook\Utilities\ResponseHandler;

class Token implements TokenInterface
{
    protected const VERSION = 'v2.0';
    protected const AUTHORITY = 'https://login.microsoftonline.com';
    protected const COMMON = 'common/oauth2';
    protected const TOKEN_URL = 'token';
    protected const AUTHORIZE = 'authorize';

    private $clientId;
    private $clientSecret;

    protected $args = [];

    protected $scopes = [
        'openid',
        'offline_access',
        'https://outlook.office.com/calendars.readwrite'
    ];

    public function __construct(string $clientId, string $clientSecret, array $args = [])
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->args = $args;
    }

    public function request(string $code, string $redirectUrl) : TokenEntityInterface
    {
        $requestData = $this->queryParams('authorization_code', $code, $redirectUrl);
        $url = static::AUTHORITY . DIRECTORY_SEPARATOR . static::COMMON . DIRECTORY_SEPARATOR . static::VERSION . DIRECTORY_SEPARATOR . static::TOKEN_URL;
        $requestOptions = new RequestOptions($url, RequestType::Post(), ['body' => $requestData]);

        $response = $this->getConnectionHandler()
            ->createClient()
            ->request($requestOptions->getMethod(), $url, [
                'headers' => $requestOptions->getHeaders(),
                'query' => $requestOptions->getQueryParams(),
                'form_params' => $requestOptions->getBody()
            ]);

        return new TokenEntity(ResponseHandler::toArray($response));
    }

    public function refresh(string $refreshToken, string $redirectUrl) : TokenEntityInterface
    {
        $requestData = $this->queryParams('refresh_token', $refreshToken, $redirectUrl);
        $requestData['refresh_token'] = $refreshToken;

        $url = static::AUTHORITY . DIRECTORY_SEPARATOR . static::COMMON . DIRECTORY_SEPARATOR . static::VERSION . DIRECTORY_SEPARATOR . static::TOKEN_URL;
        $requestOptions = new RequestOptions($url, RequestType::Post(), ['body' => $requestData]);
        $response = $this->getConnectionHandler()
            ->createClient()
            ->request(RequestType::Post, $url, [
                'headers' => $requestOptions->getHeaders(),
                'query' => $requestOptions->getQueryParams(),
                'form_params' => $requestOptions->getBody()
            ]);

        return new TokenEntity(ResponseHandler::toArray($response));
    }

    protected function queryParams(string $grantType, string $code, string $redirectUrl) : array
    {
        return [
            'grant_type' => $grantType,
            'code' => $code,
            'redirect_uri' => $redirectUrl,
            'scope' => implode(" ", $this->scopes),
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        ];
    }

    public function getAuthorizationUrl(array $state, string $redirectUrl) : string
    {
        $queryParams = [
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUrl,
            'response_type' => 'code',
            'scope' => implode(" ", $this->scopes),
            'state' => json_encode($state)
        ];

        $authorizeUrl = static::AUTHORITY . DIRECTORY_SEPARATOR . static::COMMON . DIRECTORY_SEPARATOR . static::VERSION . DIRECTORY_SEPARATOR . static::AUTHORIZE;
        $authorizeUrl .= '?' . http_build_query($queryParams);
        return $authorizeUrl;
    }

    protected function getConnectionHandler(): ConnectionInterface
    {
        $logger = $this->args['logger'] ?? null;

        if (!$logger instanceof LoggerInterface) {
            throw new \InvalidArgumentException('Missing logger parameter in args');
        }

        return new Connection($logger);
    }
}
