<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Exception;

use Throwable;

class MissingResourceURLException extends \RuntimeException
{
    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        parent::__construct('Missing resource url', 422, $previous);
    }
}