<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Entity;

interface ExtensionInterface
{
    public function __get($name);
    public function getODataType(): ?string;
    public function getODataId(): ?string;
    public function getId(): ?string;
    public function getExtensionName(): ?string;
}
