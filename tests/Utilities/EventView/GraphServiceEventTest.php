<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Utilities\EventView;

use Microsoft\Kiota\Abstractions\RequestAdapter;
use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Utilities\EventView\GraphServiceEvent;

class GraphServiceEventTest extends TestCase
{
    public function testClient()
    {
        $graphClient = new GraphServiceEvent('foo', 'bar', 'token_1');
        $actualClient = $graphClient->client();
        $this->assertInstanceOf(RequestAdapter::class, $actualClient->getRequestAdapter());
        $this->assertSame('https://graph.microsoft.com/v1.0', $actualClient->getRequestAdapter()->getBaseUrl());
    }
}
