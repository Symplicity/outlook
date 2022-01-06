<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Http;

interface RequestInterface
{
    /**
     * Get Headers with refreshed token
     * @param string $url
     * @param array $params
     * @return array
     */
    public function getHeadersWithToken(string $url, array $params = []) : array;
}
