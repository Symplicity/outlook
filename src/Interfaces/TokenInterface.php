<?php

namespace Symplicity\Outlook\Interfaces;

use Symplicity\Outlook\Interfaces\Entity\TokenInterface as TokenEntityInterface;

interface TokenInterface
{
    public function request(string $code, string $redirectUrl) : TokenEntityInterface;
    public function refresh(string $refreshToken, string $redirectUrl) : TokenEntityInterface;
    public function getAuthorizationUrl(array $state, string $redirectUrl) : string;
}
