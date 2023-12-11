<?php

namespace Symplicity\Outlook\Interfaces;

use Symplicity\Outlook\Interfaces\Entity\TokenInterface as TokenEntityInterface;

interface TokenInterface
{
    /**
     * Request Token from Outlook
     * @param string $code
     * @param string $redirectUrl
     * @return TokenEntityInterface
     */
    public function request(string $code, string $redirectUrl) : TokenEntityInterface;

    /**
     * Get new token from Outlook
     * @param string $refreshToken
     * @param string $redirectUrl
     * @return TokenEntityInterface
     */
    public function refresh(string $refreshToken, string $redirectUrl) : TokenEntityInterface;

    /**
     * Get AuthorizationUrl.
     * @param array<string, string> $state
     * @param string $redirectUrl
     * @return string
     */
    public function getAuthorizationUrl(array $state, string $redirectUrl) : string;
}
