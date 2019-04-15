<?php

namespace Symplicity\Outlook\Interfaces\Entity;

interface DeleteInterface
{
    public function getGuid() : string;
    public function getInternalId() : string;
    public function getUrl() : string;
    public function getInternalEventType() : ?string;
}
