<?php

namespace Symplicity\Outlook\Entities;

class BatchResponseReader extends Reader
{
    protected $id;
    protected $webLink;
    protected $title;
    protected $eTag;
    protected $eventType;
    protected $seriesMasterId;
    protected $lastModifiedDateTime;
    protected $extensions = [];

    public function __construct(array $data = [])
    {
        $this->setEventType($data['Type']);
        $this->setId($data['Id']);
        $this->setWebLink($data['WebLink']);
        $this->setTitle($data['Subject']);
        $this->setETag($data['@odata.etag']);
        $this->setLastModifiedDateTime($data['LastModifiedDateTime']);
        $this->setSeriesMasterId($data['SeriesMasterId'] ?? null);
        $this->setExtensions($data['Extensions'] ?? []);
    }

    public function getLastModifiedDateTime(): ?string
    {
        return $this->lastModifiedDateTime;
    }

    // Mark: Private
    private function setLastModifiedDateTime(string $lastModifiedDateTime): self
    {
        $this->lastModifiedDateTime = $lastModifiedDateTime;
        return $this;
    }
}
