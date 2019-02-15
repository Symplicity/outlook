<?php

namespace Symplicity\Outlook\Interfaces\Utils;

use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;

interface BatchResponseInterface
{
    /**
     * Get Status code for Batch Responses
     * @return int
     */
    public function getStatusCode(): int;

    /**
     * Get reason for failure if any
     * @return null|string
     */
    public function getStatus() : ?string;

    /**
     * Get complete response for failure
     * @return null|string
     */
    public function getReason() : ?string;

    /**
     * If api call is successful, a reader entity is forwarded for use.
     * @return null|ReaderEntityInterface
     */
    public function getResponse() : ?ReaderEntityInterface;
}
