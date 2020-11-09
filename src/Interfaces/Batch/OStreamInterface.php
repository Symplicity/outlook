<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Batch;

use Psr\Http\Message\StreamInterface;

interface OStreamInterface
{
    public function create(): StreamInterface;
}
