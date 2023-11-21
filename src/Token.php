<?php

declare(strict_types=1);

namespace Symplicity\Outlook;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use Microsoft\Graph\Core\GraphConstants;
use Microsoft\Kiota\Authentication\Cache\InMemoryAccessTokenCache;
use Microsoft\Kiota\Authentication\Oauth\ProviderFactory;
use Microsoft\Kiota\Authentication\PhpLeagueAccessTokenProvider;
use Symplicity\Outlook\Entities\Token as TokenEntity;
use Symplicity\Outlook\Interfaces\Entity\TokenInterface as TokenEntityInterface;
use Symplicity\Outlook\Interfaces\TokenInterface;

class Token implements TokenInterface
{
    use AuthorizationContextTrait;

    public const TENANT_ID = 'common';

    protected const VERSION = 'v2.0';
    protected const AUTHORITY = 'https://login.microsoftonline.com';
    protected const AUTHORIZE = 'authorize';
    protected const TOKEN = 'token';

    protected array $scopes = [
        'openid',
        'offline_access',
        'https://graph.microsoft.com/calendars.readwrite'
    ];

    protected ?string $email = null;
    protected ?string $displayName = null;

    public function __construct(private readonly string $clientId, private readonly string $clientSecret, protected array $args = [])
    {
    }

    /**
     * @throws \Exception
     */
    public function request(string $code, string $redirectUrl): TokenEntityInterface
    {
        $tokenRequestContext = $this->getAuthorizationCodeContext($code, $redirectUrl);
        $tokenCache = new InMemoryAccessTokenCache();

        $tokenProvider = new PhpLeagueAccessTokenProvider(
            tokenRequestContext: $tokenRequestContext,
            scopes: $this->scopes,
            accessTokenCache: $tokenCache
        );

        // TODO: May be have requestAsync ??
        $token = $tokenProvider->getAuthorizationTokenAsync(GraphConstants::REST_ENDPOINT)->wait();
        $key = $this->getCacheKey($token);
        $identifiers = $tokenCache->getAccessToken($key);

        return (new TokenEntity())
            ->setAccessToken($token)
            ->setRefreshToken($identifiers?->getRefreshToken())
            ->setExpiresIn($identifiers?->getExpires())
            ->setIdToken($identifiers?->getToken())
            ->setEmailAddress($this->email)
            ->setDisplayName($this->displayName)
            ->setTokenReceivedOn();
    }

    /**
     * @throws IdentityProviderException
     */
    public function refresh(string $refreshToken, string $redirectUrl): TokenEntityInterface
    {
        $tokenRequestContext = $this->getClientCredentialContext();
        $params = $tokenRequestContext->getRefreshTokenParams($refreshToken);

        $oauthProvider = ProviderFactory::create($tokenRequestContext);
        $response = $oauthProvider->getAccessToken('refresh_token', $params);
        $this->getCacheKey($response->getToken());

        return (new TokenEntity())
            ->setAccessToken($response->getToken())
            ->setRefreshToken($response->getRefreshToken())
            ->setExpiresIn($response->getExpires())
            ->setIdToken($response->getToken())
            ->setEmailAddress($this->email)
            ->setDisplayName($this->displayName)
            ->setTokenReceivedOn();
    }

    public function getAuthorizationUrl(array $state, string $redirectUrl): string
    {
        $baseUrl = static::AUTHORITY . DIRECTORY_SEPARATOR . static::TENANT_ID . DIRECTORY_SEPARATOR . 'oauth2' . DIRECTORY_SEPARATOR . static::VERSION . DIRECTORY_SEPARATOR;

        $tokenAuthorizationProvider = new GenericProvider([
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'redirectUri' => $redirectUrl,
            'scope' => $this->scopes,
            'scopeSeparator' => ' ',
            'urlAuthorize' => $baseUrl . static::AUTHORIZE,
            'urlAccessToken' => $baseUrl . static::TOKEN,
            'urlResourceOwnerDetails' => 'https://graph.microsoft.com/oidc/userinfo'
        ]);

        $state = \json_encode($state);
        return $tokenAuthorizationProvider->getAuthorizationUrl([
            'state' => $state,
            'scope' => $this->scopes
        ]);
    }

    private function getCacheKey(string $accessToken): ?string
    {
        $tokenParts = explode('.', $accessToken);
        if (count($tokenParts) === 3) {
            $payload = json_decode(base64_decode($tokenParts[1]), true);
            $this->email = $payload['upn'] ?? null;
            $this->displayName = $payload['name'] ?? null;
            if (is_array($payload)
                && array_key_exists('sub', $payload)
                && ($subject = $payload['sub'])) {
                return sprintf('common-%s-%s', $this->clientId, $subject);
            }
        }

        return null;
    }
}
