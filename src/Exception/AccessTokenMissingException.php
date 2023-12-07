<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Exception;

class AccessTokenMissingException extends \RuntimeException
{
    public function __construct($message = '', $code = 0, \Throwable $previous = null)
    {
        parent::__construct('Unable to get access token', 422, $previous);
    }
}
