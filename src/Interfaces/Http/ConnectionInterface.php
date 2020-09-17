<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Http;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Symplicity\Outlook\Exception\ConnectionException;

interface ConnectionInterface
{
    /**
     * Creates a guzzle client.
     * @return ClientInterface
     */
    public function createClient() : ClientInterface;

    /**
     * Creates a guzzle client with a retry and delayed handler.
     * @return ClientInterface
     */
    public function createClientWithRetryHandler() : ClientInterface;

    /**
     * Calls outlook using the guzzle get request
     * @param string $url
     * @param RequestOptionsInterface $requestOptions
     * @param array $args
     * @return mixed
     * @throws ConnectionException
     */
    public function get(string $url, RequestOptionsInterface $requestOptions, array $args = []) : ResponseInterface;

    /**
     * Post/Patch to outlook using the guzzle post request.
     * @param string $url
     * @param RequestOptionsInterface $requestOptions
     * @return mixed
     * @throws ConnectionException
     */
    public function upsert(string $url, RequestOptionsInterface $requestOptions) : ResponseInterface;

    /**
     * Delete request to outlook
     * @param string $url
     * @param RequestOptionsInterface $requestOptions
     * @return ResponseInterface
     */
    public function delete(string $url, RequestOptionsInterface $requestOptions) : ResponseInterface;

    /**
     * Batch post/get/patch using the guzzle pool handler.
     * @param RequestOptionsInterface $requestOptions
     * @return mixed
     */
    public function batch(RequestOptionsInterface $requestOptions);

    /**
     * Batch delete using the guzzle pool handler.
     * @param RequestOptionsInterface $requestOptions
     * @return mixed
     */
    public function batchDelete(RequestOptionsInterface $requestOptions);
}
