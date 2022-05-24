<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Http;

use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Interfaces\Http\ConnectionTokenInterface;
use Symplicity\Outlook\Interfaces\Http\RequestInterface;
use Symplicity\Outlook\Token;

class ConnectionToken implements ConnectionTokenInterface
{
    public $requestArgs = [];

    protected $logger;

    /** @var RequestInterface $requestHandler */
    private $requestHandler;

    public function __construct(?LoggerInterface $logger, ?RequestInterface $requestHandler, array $requestArgs = [])
    {
        $this->logger = $logger;
        $this->requestHandler = $requestHandler;
        $this->requestArgs = $requestArgs;
    }

    public function tryRefreshHeaderToken() : array
    {
        if (isset($this->requestArgs['url'], $this->requestHandler)) {
            if (isset($this->requestArgs['token']['token_received_on'], $this->requestArgs['token']['expires_in'])) {
                $this->logger->info('Refresh Token', [
                    'token_received_on' => $this->requestArgs['token']['token_received_on'],
                    'expires_in' => $this->requestArgs['token']['expires_in']
                ]);
            }
            $acessToken = $this->getNewAccessToken();
            return $this->requestHandler->getHeadersWithToken($this->requestArgs['url'], [
                'access_token' => $acessToken,
                'logger' => $this->logger
            ]);
        }

        return [];
    }

    public function shouldRefreshToken() : bool
    {
        if (isset($this->requestArgs['token']['token_received_on'], $this->requestArgs['token']['expires_in'])) {
            $token = $this->requestArgs['token'];
            if ((strtotime('now') - strtotime($token['token_received_on'])) > ($token['expires_in'] - 60)) {
                return true;
            }
        }

        return false;
    }

    public function getNewAccessToken() : ?string
    {
        if (isset($this->requestArgs['token']['clientID'], $this->requestArgs['token']['clientSecret'], $this->requestArgs['token']['outlookProxyUrl'])) {
            $token = $this->requestArgs['token'];
            $tokenObj = new Token($token['clientID'], $token['clientSecret'], ['logger' => $this->logger]);
            $tokenEntity = $tokenObj->refresh($token['refreshToken'], $token['outlookProxyUrl']);
            $date = $tokenEntity->tokenReceivedOn();

            $this->requestArgs['token']['token_received_on'] = $date->format('Y-m-d H:i:s') ?? '';
            $this->requestArgs['token']['expires_in'] = $tokenEntity->getExpiresIn() ?? '';
            $this->requestArgs['token']['refreshToken'] = $tokenEntity->getRefreshToken() ?? '';

            return $tokenEntity->getAccessToken() ?? '';
        }

        return null;
    }

    public function setRequestURL(string $url) : void
    {
        $this->requestArgs['url'] = $url;
    }
}
