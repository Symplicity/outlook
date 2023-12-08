<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Notification;

use Microsoft\Graph\Generated\Models\Subscription as MsSubscription;
use Symplicity\Outlook\Exception\SubscribeFailedException;

interface SubscriptionInterface
{
    /**
     * Subscribe to items
     * @param MsSubscription $subscriptionEntity
     * @param array<string, mixed> $args
     * @return MsSubscription|null
     * @throws SubscribeFailedException
     */
    public function subscribe(MsSubscription $subscriptionEntity, array $args = []): ?MsSubscription;

    /**
     * Renew subscription
     * @param string $subscriptionId
     * @param \DateTime $expiration
     * @param array<string, mixed> $args
     * @return MsSubscription|null
     * @throws SubscribeFailedException
     */
    public function renew(string $subscriptionId, \DateTime $expiration, array $args = []): ?MsSubscription;

    /**
     * Delete any subscription
     * @param string $subscriptionId
     * @param array<string, mixed> $args
     * @throws SubscribeFailedException
     */
    public function delete(string $subscriptionId, array $args = []): void;
}
