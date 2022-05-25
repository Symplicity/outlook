<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Http;

interface ConnectionTokenInterface
{
    /**
     * Checks if the token should be refreshed.
     */
    public function shouldRefreshToken() : bool;

    /**
     * Tries to refresh the current token and returns a new Header with it.
     */
    public function tryRefreshHeaderToken(): array;

    /**
     * Set the new values of the token and returns the access token.
     */
    public function getNewAccessToken() : ?string;

    /**
     * Set the URL of the request.
     * @param string $url
     */
    public function setRequestURL(string $url) : void;

}
