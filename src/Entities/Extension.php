<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

use Symplicity\Outlook\Interfaces\Entity\ExtensionInterface;

class Extension implements ExtensionInterface
{
    protected $oDataType;
    protected $oDataId;
    protected $id;
    protected $extensionName;
    protected $values;

    public function __construct(array $data = [])
    {
        $this->setODataType($data['@odata.type'] ?? null);
        $this->setODataId($data['@odata.id'] ?? null);
        $this->setId($data['Id'] ?? null);
        $this->setExtensionName($data['ExtensionName'] ?? null);
        $this->unset($data, '@odata.type', '@odata.id', 'Id', 'ExtensionName');
        $this->setValues($data);
    }

    public function __get($name)
    {
        if (!empty($this->values[$name])) {
            $this->$name = $this->values[$name];
            return $this->$name;
        }

        return null;
    }

    // Mark Getters
    public function getODataType(): ?string
    {
        return $this->oDataType;
    }

    public function getODataId(): ?string
    {
        return $this->oDataId;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getExtensionName(): ?string
    {
        return $this->extensionName;
    }

    // Mark Setters
    protected function setODataType(?string $oDataType): self
    {
        $this->oDataType = $oDataType;
        return $this;
    }

    protected function setODataId(?string $oDataId): self
    {
        $this->oDataId = $oDataId;
        return $this;
    }

    protected function setId(?string $id): self
    {
        $this->id = $id;
        return $this;
    }

    protected function setExtensionName(?string $extensionName): self
    {
        $this->extensionName = $extensionName;
        return $this;
    }

    protected function unset(array &$data, string ...$_): void
    {
        foreach ($_ as $key) {
            if (isset($data[$key])) {
                unset($data[$key]);
            }
        }
    }

    // Mark Private
    private function setValues(array $values = [])
    {
        $this->values = $values;
        return $this;
    }
}
