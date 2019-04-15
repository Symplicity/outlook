<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use Symplicity\Outlook\Entities\Reader;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Interfaces\Utils\BatchResponseInterface;

class BatchResponse implements BatchResponseInterface
{
    public $state;
    public $statusCode;
    public $status;
    public $reason;
    public $response;

    public function __construct(array $response)
    {
        $this->state = $response['state'] ?? null;
        if (isset($response['value']) && ($oResponse = $response['value']) instanceof ResponseInterface) {
            $this->setStatusCode($oResponse->getStatusCode());
            $this->setStatus();
            $this->setReason($oResponse->getReasonPhrase());
            $this->setResponse($oResponse->getBody()->getContents());
        } elseif (isset($response['reason']) && ($oResponse = $response['reason']) instanceof \Exception) {
            $this->setStatusCode($oResponse->getCode());
            $this->setReason($oResponse->getMessage());
            $this->status = PromiseInterface::REJECTED;
        }
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getStatus() : ?string
    {
        return $this->status;
    }

    public function getReason() : ?string
    {
        return $this->reason;
    }

    public function getResponse() : ?ReaderEntityInterface
    {
        return $this->response;
    }

    public function setStatusCode(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    public function setStatus(): void
    {
        $this->status = in_array($this->getStatusCode(), [200, 201, 202, 204], true) ? PromiseInterface::FULFILLED : PromiseInterface::REJECTED;
    }

    public function setReason(string $reason): void
    {
        $this->reason = $reason;
    }

    public function setResponse(string $response): void
    {
        // return if status code is 204, no need to hydrate reader.
        if ($this->getStatusCode() == 204) {
            return;
        }

        try {
            $responseStream = \GuzzleHttp\json_decode($response, true);
            $this->response = (new Reader())->hydrate($responseStream);
        } catch (\Exception $e) {
            // Ignore response probably a delete.
        }
    }
}
