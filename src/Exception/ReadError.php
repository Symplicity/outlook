<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Exception;

class ReadError extends \Exception
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
