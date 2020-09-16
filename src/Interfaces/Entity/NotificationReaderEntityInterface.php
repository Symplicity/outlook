<?php

namespace Symplicity\Outlook\Interfaces\Entity;

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
    public function setSequenceNumber(?int $sequenceNumber): self;

    // Mark Getters
    public function getType(): ?string;
    public function getOutlookId(): ?string;
    public function getSubscriptionId(): ?string;
    public function getSubscriptionExpirationDateTime(): ?string;
    public function getSequenceNumber(): ?int;
    public function getChangeType(): ?string;
    public function getResource(): ?string;
    public function getODataType(): ?string;
    public function getODataId(): ?string;
    public function getEtag(): ?string;
    public function getId(): ?string;
}
