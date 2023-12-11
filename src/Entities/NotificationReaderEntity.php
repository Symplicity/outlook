<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

use Symplicity\Outlook\Interfaces\Entity\NotificationReaderEntityInterface;
use Symplicity\Outlook\Utilities\ChangeType;

class NotificationReaderEntity implements \JsonSerializable, NotificationReaderEntityInterface
{
    protected ?string $subscriptionId = null;
    protected ?string $subscriptionExpirationDateTime = null;
    protected ?ChangeType $changeType = null;
    protected ?string $resource = null;
    protected ?string $oDataType = null;
    protected ?string $oDataId = null;
    protected ?string $eTag = null;
    protected ?string $id = null;
    protected ?string $tenantId = null;

    /** @param array<string, mixed> $data */
    public function __construct(array $data = [])
    {
        $this->subscriptionId = $data['subscriptionId'] ?? null;
        $this->subscriptionExpirationDateTime = $data['subscriptionExpirationDateTime'] ?? null;
        $this->resource = $data['resource'] ?? null;
        $this->oDataType = $data['resourceData']['@odata.type'] ?? null;
        $this->oDataId = $data['resourceData']['@odata.id'] ?? null;
        $this->eTag = $data['resourceData']['@odata.etag'] ?? null;
        $this->id = $data['resourceData']['id'] ?? null;
        $this->tenantId = $data['tenantId'] ?? null;
        $this->setChangeType($data['changeType'] ?? null);
    }

    /** @return array<string, string | ChangeType | null> */
    public function jsonSerialize(): array
    {
        return [
            'res' => $this->resource,
            'id' => $this->id,
            'subId' => $this->subscriptionId,
            'cT' => $this->changeType
        ];
    }

    public function has(string $var): bool
    {
        if (isset($this->$var)) {
            return true;
        }

        return false;
    }

    public function setSubscriptionId(?string $subscriptionId): NotificationReaderEntityInterface
    {
        $this->subscriptionId = $subscriptionId;
        return $this;
    }

    public function setChangeType(?string $changeType = null): NotificationReaderEntityInterface
    {
        $this->changeType = ChangeType::UNKNOWN;
        if (isset($changeType) && ($value = ChangeType::tryFrom($changeType))) {
            $this->changeType = $value;
        }

        return $this;
    }

    public function setResource(?string $resource): NotificationReaderEntityInterface
    {
        $this->resource = $resource;
        return $this;
    }

    public function setId(?string $id): NotificationReaderEntityInterface
    {
        $this->id = $id;
        return $this;
    }

    // Mark Getters
    public function getSubscriptionId(): ?string
    {
        return $this->subscriptionId;
    }

    public function getSubscriptionExpirationDateTime(): ?string
    {
        return $this->subscriptionExpirationDateTime;
    }

    public function getChangeType(): ?ChangeType
    {
        return $this->changeType;
    }

    public function getResource(): ?string
    {
        return $this->resource;
    }

    public function getODataType(): ?string
    {
        return $this->oDataType;
    }

    public function getODataId(): ?string
    {
        return $this->oDataId;
    }

    public function getEtag(): ?string
    {
        return $this->eTag;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }
}
