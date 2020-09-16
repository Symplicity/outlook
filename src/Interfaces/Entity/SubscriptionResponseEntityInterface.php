<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Entity;

use DateTimeImmutable;

interface SubscriptionResponseEntityInterface
{
    public function getSubscriptionExpirationDate(): ?DateTimeImmutable;
}
