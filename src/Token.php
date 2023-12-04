<?php

declare(strict_types=1);

namespace Symplicity\Outlook;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use Microsoft\Graph\Core\GraphConstants;
use Microsoft\Kiota\Authentication\Cache\AccessTokenCache;
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

    protected const OAUTH_AUTHORIZE = 'authorize';
    protected const OAUTH_TOKEN = 'token';
    protected const OAUTH_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/';
    protected const OAUTH_USER_INFO_URL = 'https://graph.microsoft.com/oidc/userinfo';

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
    public function request(string $code, string $redirectUrl, ?AccessTokenCache $tokenCache = null, ?AbstractProvider $provider = null): TokenEntityInterface
    {
        $tokenRequestContext = $this->getAuthorizationCodeContext($code, $redirectUrl);
        $tokenCache ??= new InMemoryAccessTokenCache();
        $provider ??= ProviderFactory::create($tokenRequestContext);

        $tokenProvider = new PhpLeagueAccessTokenProvider(
            tokenRequestContext: $tokenRequestContext,
            scopes: $this->scopes,
            oauthProvider: $provider,
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
    public function refresh(string $refreshToken, string $redirectUrl, ?AbstractProvider $oauthProvider = null): TokenEntityInterface
    {
        $tokenRequestContext = $this->getClientCredentialContext();
        $params = $tokenRequestContext->getRefreshTokenParams($refreshToken);

        $oauthProvider ??= ProviderFactory::create($tokenRequestContext);
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
        $tokenAuthorizationProvider = new GenericProvider([
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'redirectUri' => $redirectUrl,
            'scope' => $this->scopes,
            'scopeSeparator' => ' ',
            'urlAuthorize' => static::OAUTH_URL . static::OAUTH_AUTHORIZE,
            'urlAccessToken' => static::OAUTH_URL . static::OAUTH_TOKEN,
            'urlResourceOwnerDetails' => static::OAUTH_USER_INFO_URL
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
