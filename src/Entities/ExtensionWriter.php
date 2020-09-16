<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

use Symplicity\Outlook\Exception\WriteError;
use Symplicity\Outlook\Interfaces\Entity\ExtensionWriterInterface;

abstract class ExtensionWriter implements ExtensionWriterInterface
{
    protected $oDataType;
    protected $extensionName;

    public function __construct(array $data = [])
    {
        $this->setODataType($data['@odata.type'] ?? null);
        $this->setExtensionName($data['ExtensionName'] ?? null);
    }

    public function jsonSerialize()
    {
        $oDataType = $this->getODataType();
        $extensionName = $this->getExtensionName();

        if (!isset($oDataType, $extensionName)) {
            throw new WriteError('Missing outlook data type or extension');
        }

        return [
            '@odata.type' => $this->getODataType(),
            'ExtensionName' => $this->getExtensionName()
        ];
    }

    // Mark Fluent Setters
    protected function setODataType($oDataType): ExtensionWriterInterface
    {
        $this->oDataType = $oDataType;
        return $this;
    }

    protected function setExtensionName($extensionName): ExtensionWriterInterface
    {
        $this->extensionName = $extensionName;
        return $this;
    }

    // Mark Getters
    public function getODataType(): string
    {
        return $this->oDataType;
    }

    public function getExtensionName(): string
    {
        return $this->extensionName;
    }
}