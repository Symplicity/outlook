<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Notification;

use RuntimeException;
use Symplicity\Outlook\Interfaces\Entity\SubscriptionEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\SubscriptionResponseEntityInterface;

interface SubscriptionInterface
{
    /**
     * Subscribe to items
     * @param SubscriptionEntityInterface $subscriptionEntity
     * @param string $accessToken
     * @return SubscriptionResponseEntityInterface
     * @throws RuntimeException
     */
    public function subscribe(SubscriptionEntityInterface $subscriptionEntity, string $accessToken): SubscriptionResponseEntityInterface;

    /**
     * Renew subscription
     * @param string $subscriptionId
     * @param string $accessToken
     * @param array $args
     * @return SubscriptionResponseEntityInterface
     * @throws RuntimeException
     */
    public function renew(string $subscriptionId, string $accessToken, array $args = []): SubscriptionResponseEntityInterface;

    /**
     * Delete any subscription
     * @param string $subscriptionId
     * @param string $accessToken
     * @return bool
     */
    public function delete(string $subscriptionId, string $accessToken): bool;
}
