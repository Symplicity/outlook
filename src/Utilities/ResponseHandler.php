<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities;

use Psr\Http\Message\ResponseInterface;

class ResponseHandler
{
    public static function toArray(ResponseInterface $response) : array
    {
        $body = $response->getBody()->getContents();
        $body = json_decode($body, true);
        if ($body === null) {
            return [];
        }

        return $body;
    }
}
