<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

final class BatchErrorEntity
{
    public const UNKNOWN_ERROR_CODE = 'unknown';

    private $id;
    private $statusCode;
    private $errorCode;
    private $errorMessage;

    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? null;
        $this->statusCode = $data['status'] ?? 0;
        $this->errorCode = $data['body']['code'] ?? self::UNKNOWN_ERROR_CODE;
        $this->errorMessage = $data['body']['message'] ?? null;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getReason(): ?string
    {
        return $this->errorMessage;
    }
}
