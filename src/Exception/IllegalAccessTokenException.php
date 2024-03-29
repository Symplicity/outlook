<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Exception;

class IllegalAccessTokenException extends \RuntimeException
{
    public function __construct(\Throwable $previous = null)
    {
        parent::__construct('Access token received is not valid', 422, $previous);
    }
}
