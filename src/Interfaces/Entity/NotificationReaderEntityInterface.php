<?php

namespace Symplicity\Outlook\Interfaces\Entity;

use Symplicity\Outlook\Utilities\ChangeType;

interface NotificationReaderEntityInterface
{
    /**
     * Check if property $var has a value
     * @param string $var
     * @return bool
     */
    public function has(string $var): bool;

    public function setSubscriptionId(?string $subscriptionId): self;
    public function setChangeType(?string $changeType): self;
    public function setResource(?string $resource): self;
    public function setId(?string $id): self;

    // Mark Getters
    public function getSubscriptionId(): ?string;
    public function getSubscriptionExpirationDateTime(): ?string;
    public function getChangeType(): ?ChangeType;
    public function getResource(): ?string;
    public function getODataType(): ?string;
    public function getODataId(): ?string;
    public function getEtag(): ?string;
    public function getId(): ?string;
    public function getTenantId(): ?string;
}
