<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

use Symplicity\Outlook\Interfaces\Entity\ResponseBodyInterface;

class ResponseBody implements ResponseBodyInterface
{
    protected $contentType;
    protected $content;

    public function __construct(array $data = [])
    {
        $this->contentType = $data['ContentType'];
        $this->content = $data['Content'];
    }

    public function getContent() : string
    {
        return $this->content;
    }

    public function getContentType() : string
    {
        return $this->contentType;
    }

    public function getSanitizedContent() : string
    {
        return trim($this->content);
    }
}
