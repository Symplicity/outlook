<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Entities;

use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Entities\Delete;
use Symplicity\Outlook\Interfaces\Entity\DeleteInterface;

class DeleteTest extends TestCase
{
    public function testGetGuid()
    {
        $delete = new Delete('abc', '123');
        $delete = $delete->setInternalEventType('kiosk');
        $this->assertInstanceOf(DeleteInterface::class, $delete);
        $this->assertEquals('abc', $delete->getGuid());
        $this->assertEquals('123', $delete->getInternalId());
        $this->assertEquals('kiosk', $delete->getInternalEventType());
        $this->assertEquals('/me/events/abc', $delete->getUrl());
    }
}
