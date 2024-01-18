<?php

declare(strict_types=1);

namespace Symplicity\Outlook;

use Microsoft\Kiota\Authentication\Oauth\AuthorizationCodeContext;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use Microsoft\Kiota\Authentication\Oauth\TokenRequestContext;
use Symplicity\Outlook\Utilities\MultiAuthCodeContext;

trait AuthorizationContextTrait
{
    private readonly string $clientId;
    private readonly string $clientSecret;

    public function getAuthorizationCodeContext(string $code, string $redirectUrl): TokenRequestContext
    {
        return new AuthorizationCodeContext(
            tenantId: Token::TENANT_ID,
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
            authCode: $code,
            redirectUri: $redirectUrl
        );
    }

    public function getMultiAuthCodeContext(string $code, string $redirectUrl): MultiAuthCodeContext
    {
        return new MultiAuthCodeContext(
            tenantId: Token::TENANT_ID,
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
            authCode: $code,
            redirectUri: $redirectUrl
        );
    }

    protected function getClientCredentialContext(): TokenRequestContext
    {
        return new ClientCredentialContext(
            tenantId: Token::TENANT_ID,
            clientId: $this->clientId,
            clientSecret: $this->clientSecret
        );
    }
}
