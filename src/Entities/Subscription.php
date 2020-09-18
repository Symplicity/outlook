<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

use Ramsey\Uuid\Uuid;
use RuntimeException;
use Symplicity\Outlook\Interfaces\Entity\SubscriptionEntityInterface;

class Subscription implements SubscriptionEntityInterface
{
    public const DEFAULT_DATA_TYPE = '#Microsoft.OutlookServices.PushSubscription';

    protected $dataType;
    protected $resource;
    protected $notificationUrl;
    protected $changeType;
    protected $clientState;

    // Mark Setters
    public function setDataType(string $dataType): SubscriptionEntityInterface
    {
        $this->dataType = $dataType;
        return $this;
    }

    public function setResource(string $resource): SubscriptionEntityInterface
    {
        $this->resource = $resource;
        return $this;
    }

    public function setNotificationUrl(string $notificationUrl): SubscriptionEntityInterface
    {
        $this->notificationUrl = $notificationUrl;
        return $this;
    }

    public function setChangeType(array $changeType = []): SubscriptionEntityInterface
    {
        $this->changeType = join(',', $changeType);
        return $this;
    }

    public function setClientState(string $clientState): SubscriptionEntityInterface
    {
        $this->clientState = $clientState;
        return $this;
    }

    // Mark Protected
    protected function verify(): bool
    {
        if (!isset($this->notificationUrl, $this->resource)) {
            throw new RuntimeException('Missing properties');
        }

        return true;
    }

    // Mark Implementation
    public function jsonSerialize()
    {
        $this->verify();
        return [
            '@odata.type' => $this->dataType ?? static::DEFAULT_DATA_TYPE,
            'Resource' => $this->resource,
            'NotificationURL' => $this->notificationUrl,
            'ChangeType' => $this->changeType ?? 'Created,Deleted,Updated,Missed',
            'ClientState' => $this->clientState ?? (Uuid::uuid4())->toString()
        ];
    }
}
