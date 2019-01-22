<?php

namespace Symplicity\Outlook\Interfaces\Http;

use Symplicity\Outlook\Interfaces\RequestOptionsInterface;

interface ResponseIteratorInterface
{
    public function setItems(string $url, RequestOptionsInterface $requestOptions) : ResponseIteratorInterface;
    public function each() : ?\Generator;
    public function getDeltaLink() : string;
}