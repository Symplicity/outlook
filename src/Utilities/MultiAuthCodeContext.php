<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities;

use League\OAuth2\Client\Token\AccessToken;
use Microsoft\Kiota\Authentication\Oauth\AuthorizationCodeContext;
use Microsoft\Kiota\Authentication\Oauth\DelegatedPermissionTrait;

class MultiAuthCodeContext extends AuthorizationCodeContext
{
    use DelegatedPermissionTrait {
        setCacheKey as public setParentCacheKey;
    }

    private ?AccessToken $accessToken = null;

    public function setCacheKey(?AccessToken $accessToken = null): void
    {
        $this->setParentCacheKey($accessToken);
        if (empty($this->getCacheKey())) {
            $this->accessToken = $accessToken;
        }
    }

    public function getReceivedToken(): ?AccessToken
    {
        return $this->accessToken;
    }
}
