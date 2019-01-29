<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Http;

interface RequestOptionsInterface
{
    public function addHeader(string $key, $value) : void;
    public function addBody(array $body) : void;
    public function getHeaders() : array;
    public function getQueryParams() : array;
    public function getRawHeaders() : array;
    public function getBody() : array;
    public function clear(string $headerKey) : void;
    public function toArray() : array;
    public function addPreferenceHeaders(array $preferenceHeaders) : void;
    public function getPreferredTimezone() : string;
}
