<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Notification;

use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Interfaces\CalendarInterface;

interface ReceiverInterface
{
    /**
     * Hydrate items once notification is received
     * @param array $data
     * @return $this
     */
    public function hydrate(array $data = []): self;

    /**
     * Calls getEvents which calls abstract method saveEventsToLocal, deleteEvents
     * before getEvents is called , willCreate method will be called
     * after getEvents is executed, didCreate method will be called.
     * @param CalendarInterface $calendar
     * @param LoggerInterface $logger
     * @param array $params
     * @return mixed
     */
    public function exec(CalendarInterface $calendar, LoggerInterface $logger, array $params = []);

    /**
     * Set entities received from notifications
     * @param array $entities
     * @return $this
     */
    public function setEntities(array $entities): self;

    /**
     * Get entities
     * @return array
     */
    public function getEntities(): array;

    /**
     * Get client State
     * @return string
     */
    public function getState(): string;
}
