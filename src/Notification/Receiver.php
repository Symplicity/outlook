<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Notification;

use Closure;
use Microsoft\Graph\Generated\Users\Item\Events\Item\EventItemRequestBuilderGetQueryParameters;
use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Entities\NotificationReaderEntity;
use Symplicity\Outlook\Exception\MissingResourceURLException;
use Symplicity\Outlook\Exception\ValidationException;
use Symplicity\Outlook\Interfaces\CalendarInterface;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Interfaces\Notification\ReceiverInterface;

abstract class Receiver implements ReceiverInterface
{
    /** @var array<NotificationReaderEntity> $entities */
    protected array $entities = [];

    public function hydrate(array $data = []): ReceiverInterface
    {
        $this->setEntities($data);
        return $this;
    }

    public function exec(CalendarInterface $calendar, LoggerInterface $logger, array $params = [], array $args = [], ?Closure $beforeReturn = null): void
    {
        foreach ($this->entities as $notificationEntity) {
            try {
                $this->validate($notificationEntity);
                $this->willWrite($calendar, $logger, $notificationEntity, $params);

                $id = $notificationEntity->getId();
                if ($id === null) {
                    throw new MissingResourceURLException();
                }

                $queryParameters = $this->getEventQueryParameters($params);
                $outlookEntity = $calendar->getEventBy($id, $queryParameters, beforeReturn: $beforeReturn, args: $args);
                $args = ['token' => $params['token'] ?? []];
                $this->didWrite($calendar, $logger, $outlookEntity, $notificationEntity, $args);
            } catch (\Exception $e) {
                $eventInfo = [
                    'resource' => $notificationEntity->getResource(),
                    'subscriptionId' => $notificationEntity->getSubscriptionId(),
                    'id' => $notificationEntity->getId(),
                    'error' => $e->getMessage()
                ];

                $this->eventWriteFailed($calendar, $logger, $eventInfo);
                $logger->error('Event did not process successfully', $eventInfo);
            }
        }
    }

    /**
     * @param array<string, mixed | NotificationReaderEntity> $entities
     */
    public function setEntities(array $entities): void
    {
        foreach ($entities as $entity) {
            if ($entity instanceof NotificationReaderEntity) {
                $this->entities[] = $entity;
            } else {
                $this->entities[] = new NotificationReaderEntity($entity);
            }
        }
    }

    public function getEntities(): array
    {
        return $this->entities;
    }

    // Mark Protected

    /**
     * @throws ValidationException
     */
    protected function validate(NotificationReaderEntity $entity): bool
    {
        if ($entity->has('resource')
            && $entity->has('subscriptionId')
            && $entity->has('id')) {
            return true;
        }

        throw new ValidationException('Missing resource/subscription-id/id');
    }

    /**
     * @param array<string, string[]> $args
     */
    protected function getEventQueryParameters(array $args = []): EventItemRequestBuilderGetQueryParameters
    {
        $queryParameters = new EventItemRequestBuilderGetQueryParameters();
        $queryParameters->expand = $args['expand'] ?? [];
        $queryParameters->select = $args['select'] ?? [];
        return $queryParameters;
    }

    /**
     * @param CalendarInterface $calender
     * @param LoggerInterface $logger
     * @param array<string, ?string> $info
     */
    abstract protected function eventWriteFailed(CalendarInterface $calender, LoggerInterface $logger, array $info): void;

    /**
     * @param CalendarInterface $calendar
     * @param LoggerInterface $logger
     * @param NotificationReaderEntity $notificationReaderEntity
     * @param array<string, ?string> $params
     */
    abstract protected function willWrite(CalendarInterface $calendar, LoggerInterface $logger, NotificationReaderEntity $notificationReaderEntity, array &$params = []): void;

    /**
     * @param array<string, ?string> $args
     */
    abstract protected function didWrite(CalendarInterface $calendar, LoggerInterface $logger, ?ReaderEntityInterface $entity, NotificationReaderEntity $notificationReaderEntity, array $args = []): void;
}
