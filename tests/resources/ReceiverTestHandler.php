<?php

namespace Symplicity\Outlook\Tests\resources;

use PHPUnit\Framework\Assert;
use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Entities\NotificationReaderEntity;
use Symplicity\Outlook\Interfaces\CalendarInterface;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Notification\Receiver;

class ReceiverTestHandler extends Receiver
{
    public function __construct(public array &$receivedFailedWrites, public readonly Assert $assertionHandler)
    {
    }

    public function reset()
    {
        $this->receivedFailedWrites = [];
        $this->entities = [];
    }

    protected function eventWriteFailed(CalendarInterface $calender, LoggerInterface $logger, array $info): void
    {
        $this->receivedFailedWrites[] = $info;
    }

    protected function willWrite(
        CalendarInterface $calendar,
        LoggerInterface $logger,
        NotificationReaderEntity $notificationReaderEntity,
        array &$params = []
    ): void {
        if (isset($params['setResourceToNull'])) {
            $notificationReaderEntity->setResource(null);
            return;
        }
        $originalResource = $notificationReaderEntity->getResource();
        $filters = rawurlencode("Id eq ") . '\'Microsoft.OutlookServices.OpenTypeExtension.symplicitytest\'';
        $originalResource .= '?$expand=Extensions($filter=' . $filters . ')';
        $notificationReaderEntity->setResource($originalResource);
    }

    protected function didWrite(
        CalendarInterface $calendar,
        LoggerInterface $logger,
        ?ReaderEntityInterface $entity,
        NotificationReaderEntity $notificationReaderEntity,
        array $args = []
    ): void {
        $this->assertionHandler->assertSame('AAA==', $entity->getId());
        $this->assertionHandler->assertSame('Foo test', $entity->getTitle());
    }
}
