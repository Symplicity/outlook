<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces;

interface ConnectionInterface
{
    public function get(string $url, RequestOptionsInterface $requestOptions);
    public function batch(RequestOptionsInterface $requestOptions);
}
