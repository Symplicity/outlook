<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Utilities\CalendarView;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Microsoft\Graph\GraphServiceClient;
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
        $requestAdapter = $actualClient->getRequestAdapter();
        $this->assertInstanceOf(RequestAdapter::class, $requestAdapter);
        $this->assertSame('https://graph.microsoft.com/v1.0', $requestAdapter->getBaseUrl());

        $values = $this->getGuzzleConfig($requestAdapter, $actualClient);
        $this->assertIsArray($values);
        $this->assertSame(GraphServiceCalendarView::DEFAULT_CONNECT_TIMEOUT, $values['connect_timeout']);
        $this->assertSame(GraphServiceCalendarView::DEFAULT_TIMEOUT, $values['timeout']);
        $this->assertSame(GraphServiceCalendarView::HTTP_VERIFY, $values['verify']);

        $params->setRequestOptions([
            RequestOptions::CONNECT_TIMEOUT => 6.0,
            RequestOptions::TIMEOUT => 8.0,
            RequestOptions::VERIFY => true
        ]);

        $actualClient = $graphClient->client($params);
        $requestAdapter = $actualClient->getRequestAdapter();
        $values = $this->getGuzzleConfig($requestAdapter, $actualClient);
        $this->assertIsArray($values);
        $this->assertSame(6.0, $values['connect_timeout']);
        $this->assertSame(8.0, $values['timeout']);
        $this->assertTrue($values['verify']);
    }

    private function getGuzzleConfig(RequestAdapter $requestAdapter, GraphServiceClient $client): mixed
    {
        $reflection = new \ReflectionObject($requestAdapter);
        $reflectionProperty = $reflection->getParentClass()->getParentClass()->getProperty('guzzleClient');

        /** @var Client $guzzleClient */
        $guzzleClient = $reflectionProperty->getValue($client->getRequestAdapter());

        $guzzleConfigProperty = new \ReflectionProperty(Client::class, 'config');
        return $guzzleConfigProperty->getValue($guzzleClient);
    }
}
