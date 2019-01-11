<?php

namespace Symplicity\Outlook\Interfaces\Entity;

interface ResponseBodyInterface
{
    public function getContent() : string;
    public function getContentType() : string;
    public function getSanitizedContent() : string;
}
