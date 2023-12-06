<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Exception;

use RuntimeException;

class SubscribeFailedException extends RuntimeException
{
    private ?string $oDataErrorMessage = null;

    public function setOdataErrorMessage(?string $error): void
    {
        $this->oDataErrorMessage = $error;
    }

    public function getODataErrorMessage(): ?string
    {
        return $this->oDataErrorMessage;
    }
}
