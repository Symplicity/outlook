<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Http;

use Symplicity\Outlook\Utilities\RequestType;

interface RequestOptionsInterface
{
    /**
     * Add headers to the request.
     * @param string $key
     * @param $value
     */
    public function addHeader(string $key, $value) : void;

    /**
     * Add body to the request
     * @param array $body
     */
    public function addBody(array $body) : void;

    /**
     * Get all request headers
     * @return array
     */
    public function getHeaders() : array;

    /**
     * Get all query parameters associated with the request.
     * @return array
     */
    public function getQueryParams() : array;

    /**
     * Get all headers in raw format.
     * @return array
     */
    public function getRawHeaders() : array;

    /**
     * Get request body.
     * @return array
     */
    public function getBody() : array;

    /**
     * Clear a specific header key.
     * @param string $headerKey
     */
    public function clear(string $headerKey) : void;

    /**
     * Get all requestOption properties as array
     * @return array
     */
    public function toArray() : array;

    /**
     * Adds specific outlook headers to the request
     * @param array $preferenceHeaders
     */
    public function addPreferenceHeaders(array $preferenceHeaders) : void;

    /**
     * Add your preferred timezone to the request, default is Eastern Standard Time, use IANA Timeto change.
     * @return string
     */
    public function getPreferredTimezone() : string;

    /**
     * Get type of request (Post, Put, Get, Delete)
     * @return RequestType
     */
    public function getMethod() : RequestType;

    /**
     * Resets uuid for client call
     */
    public function resetUUID() : void;
}
