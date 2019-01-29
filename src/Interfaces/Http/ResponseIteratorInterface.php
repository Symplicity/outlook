<?php

namespace Symplicity\Outlook\Interfaces\Http;

interface ResponseIteratorInterface
{
    public function setItems(string $url, RequestOptionsInterface $requestOptions) : ResponseIteratorInterface;
    public function each() : ?\Generator;
    public function getDeltaLink() : string;
}