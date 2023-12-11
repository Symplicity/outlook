<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Utilities\CalendarView;

use Microsoft\Kiota\Abstractions\RequestAdapter;
use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Utilities\CalendarView\CalendarViewParams;
use Symplicity\Outlook\Utilities\CalendarView\GraphServiceCalendarView;

class GraphServiceCalendarViewTest extends TestCase
{
    public function testClient()
    {
        $params = new CalendarViewParams();
        $params->setOrderBy(['start', 'end']);
        $params->setDeltaToken('foo-delta-token==');
        $params->setFilter('Extensions(id eq \'symplicity.com\'');
        $graphClient = new GraphServiceCalendarView('foo', 'bar', 'token_1');
        $actualClient = $graphClient->client($params);
        $this->assertInstanceOf(RequestAdapter::class, $actualClient->getRequestAdapter());
        $this->assertSame('https://graph.microsoft.com/v1.0', $actualClient->getRequestAdapter()->getBaseUrl());
    }
}
