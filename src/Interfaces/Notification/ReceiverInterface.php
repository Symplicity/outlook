<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Notification;

use Closure;
use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Entities\NotificationReaderEntity;
use Symplicity\Outlook\Interfaces\CalendarInterface;

interface ReceiverInterface
{
    /**
     * Hydrate items once notification is received
     * @param array<string, mixed> $data
     * @return $this
     */
    public function hydrate(array $data = []): self;

    /**
     * Calls getEvents which calls abstract method saveEventsToLocal, deleteEvents
     * before getEvents is called , willCreate method will be called
     * after getEvents is executed, didCreate method will be called.
     * @param CalendarInterface $calendar
     * @param LoggerInterface $logger
     * @param array<string, string> $params
     * @param array<string, mixed> $args
     * @param Closure|null $beforeReturn
     * @return void
     */
    public function exec(CalendarInterface $calendar, LoggerInterface $logger, array $params = [], array $args = [], ?Closure $beforeReturn = null): void;

    /**
     * Set entities received from notifications
     * @param NotificationReaderEntity[] $entities
     */
    public function setEntities(array $entities): void;

    /**
     * Get entities
     * @return NotificationReaderEntity[]
     */
    public function getEntities(): array;
}
