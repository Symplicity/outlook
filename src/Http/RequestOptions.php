<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Http;

use Ramsey\Uuid\Uuid;
use Symplicity\Outlook\Interfaces\Http\RequestOptionsInterface;
use Symplicity\Outlook\Utilities\RequestType;

class RequestOptions implements RequestOptionsInterface
{
    protected const AUTHENTICATION_SCHEME = 'Bearer';
    protected const BATCH_SCHEME = 'batch';

    public const DEFAULT_TIMEZONE = 'Eastern Standard Time';
    public const DEFAULT_ACCEPT_HEADER = 'application/json';
    public const BATCH_PREFIX = 'batch_';

    protected $url;
    protected $method;
    protected $queryParams = [];
    protected $headers = [];
    protected $body = [];
    protected $token;
    protected $timezone;
    protected $batchId;
    protected $preferenceHeaders;
    protected $batchBoundary;

    public function __construct(string $url, RequestType $methodType, array $args = [])
    {
        $this->url = $url;
        $this->method = $methodType;
        $this->headers = $args['headers'] ?? [];
        $this->body = $args['body'] ?? [];
        $this->queryParams = $args['queryParams'] ?? [];
        $this->token = $args['token'] ?? null;
        $this->timezone = $args['timezone'] ?? static::DEFAULT_TIMEZONE;
        $this->preferenceHeaders = $args['preferenceHeaders'] ?? [];
    }

    public function addDefaultHeaders(bool $skipDelta = false)
    {
        if ($this->token === null) {
            throw new \InvalidArgumentException('Missing Token');
        }

        $this->resetUUID();
        if (!$skipDelta) {
            $this->addDelta();
        }

        $this->addHeader('User-Agent', 'php-symplicity');
        $this->addHeader('Authorization', $this->getAccessToken());
        $this->addHeader('Accept', 'application/json');
        $this->addHeader('return-client-request-id', true);
    }

    public function addBatchHeaders(array $args = [])
    {
        if ($this->token == null) {
            throw new \InvalidArgumentException('Missing Token');
        }

        $this->setBatchBoundary();
        $this->resetUUID();
        $this->addHeader('Host', 'outlook.office.com');
        $this->addHeader('Authorization', $this->getAccessToken());
        $this->addHeader('return-client-request-id', true);
        $this->addHeader('Accept', $args['Accept'] ?? static::DEFAULT_ACCEPT_HEADER);
        $this->addHeader('Content-Type', 'multipart/mixed; boundary=' . $this->getBatchBoundary());
        $this->addHeader('Prefer', 'odata.continue-on-error');
    }

    public function addHeader(string $key, $value) : void
    {
        if (!array_key_exists($key, $this->headers)) {
            $this->headers[$key] = $value;
        }
    }

    public function addBody(array $body) : void
    {
        $this->body = $body;
    }

    public function getHeaders() : array
    {
        return $this->headers;
    }

    public function getQueryParams() : array
    {
        return $this->queryParams;
    }

    public function getRawHeaders() : array
    {
        return array_map(
            function($k, $v) {
                return "$k:$v";
            },
            array_keys($this->headers),
            array_values($this->headers)
        );
    }

    public function getBody() : array
    {
        return $this->body;
    }

    public function addDelta() : void
    {
        if (!empty($this->queryParams['delta'])) {
            $this->queryParams['$deltaToken'] = $this->queryParams['delta'];
        }

        unset($this->queryParams['delta']);
    }

    public function clear(string $headerKey) : void
    {
        if (isset($this->headers[$headerKey])) {
            unset($this->headers[$headerKey]);
        }
    }

    public function toArray() : array
    {
        return get_object_vars($this);
    }

    public function addPreferenceHeaders(array $preferenceHeaders) : void
    {
        $this->clear('Prefer');
        $options = implode(', ', $preferenceHeaders);
        $this->addHeader('Prefer', $options);
    }

    public function getPreferredTimezone() : string
    {
        return $this->timezone;
    }

    public function resetUUID() : void
    {
        $this->clear('client-request-id');
        $this->addHeader('client-request-id', Uuid::uuid1()->toString());
    }

    public function getAccessToken(): string
    {
        return sprintf('%s %s', static::AUTHENTICATION_SCHEME, $this->token);
    }

    public function getBatchId()
    {
        return static::BATCH_SCHEME . '_' . $this->batchId;
    }

    public function getMethod() : string
    {
        return $this->method->getValue();
    }

    public function getDefaultPreferenceHeaders() : array
    {
        return $this->preferenceHeaders;
    }

    public function getBatchBoundary(): ?string
    {
        return $this->batchBoundary;
    }

    // Mark: Private
    private function setBatchBoundary()
    {
        $uuid = Uuid::uuid1()->toString();
        $this->batchBoundary = static::BATCH_PREFIX . $uuid;
        return $this;
    }
}
