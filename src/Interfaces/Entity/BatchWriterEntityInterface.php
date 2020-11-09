<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Entity;

interface BatchWriterEntityInterface
{
    public function getId(): ?string;
    public function getMethod(): string;
    public function getUrl(): string;
}
