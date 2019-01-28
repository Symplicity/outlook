<?php

namespace Symplicity\Outlook\Interfaces\Utils;

use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;

interface BatchResponseInterface
{
    public function getStatusCode(): int;
    public function getStatus() : ?string;
    public function getReason() : ?string;
    public function getResponse() : ?ReaderEntityInterface;
}
