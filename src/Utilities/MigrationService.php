<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities;

class MigrationService
{
    public const VALID_AUDIENCE_ENDPOINT = 'https://graph.microsoft.com';

    public static function isTokenInvalid(string $accessToken): bool
    {
        $migrate = false;
        [, $payload,] = \explode('.', $accessToken);
        $payloadString = \base64_decode($payload);
        if ($payloadString) {
            $tokenArray = \json_decode($payloadString, true);
            if (json_last_error() === \JSON_ERROR_NONE
                && isset($tokenArray['aud'])
                && $tokenArray['aud'] !== static::VALID_AUDIENCE_ENDPOINT) {
                $migrate = true;
            }
        }
        return $migrate;
    }
}
