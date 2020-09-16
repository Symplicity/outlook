<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

use DateTimeImmutable;
use Symplicity\Outlook\Interfaces\Entity\SubscriptionResponseEntityInterface;

class SubscriptionResponse implements SubscriptionResponseEntityInterface
{
    public $context;
    public $type;
    public $dataId;
    public $id;
    public $resource;
    public $changeType;
    public $notificationUrl;
    public $subscriptionExpirationDate;
    public $clientState;

    public function __construct(array $data)
    {
        $this->context = $data['@odata.context'] ?? null;
        $this->type = $data['@odata.type'] ?? null;
        $this->dataId = $data['@odata.id'] ?? null;
        $this->id = $data['Id'] ?? null;
        $this->resource = $data['Resource'] ?? null;
        $this->changeType = $data['ChangeType'] ?? null;
        $this->notificationUrl = $data['NotificationURL'] ?? null;
        $this->subscriptionExpirationDate = $data['SubscriptionExpirationDateTime'] ?? null;
        $this->clientState = $data['ClientState'] ?? null;
    }

    public function getSubscriptionExpirationDate(): ?DateTimeImmutable
    {
        if (!isset($this->subscriptionExpirationDate)) {
            return null;
        }

        try {
            return new DateTimeImmutable($this->subscriptionExpirationDate);
        } catch (\Exception $e) {
        }

        return null;
    }
}
