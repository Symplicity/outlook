<?php

namespace Symplicity\Outlook\Interfaces\Http;

interface ResponseIteratorInterface
{
    /**
     * Sets the items that is received from outlook
     * @param string $url
     * @param RequestOptionsInterface $requestOptions
     * @return ResponseIteratorInterface
     */
    public function setItems(string $url, RequestOptionsInterface $requestOptions) : ResponseIteratorInterface;

    /**
     * Returns a generator callback for accessing entities from outlook
     * @return \Generator|null
     */
    public function each() : ?\Generator;

    /**
     * Gets the delta link once the items from generator is completed.
     * @return null|string
     */
    public function getDeltaLink() : ?string;
}
