<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Utilities\CalendarView;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Middleware\CalendarViewDeltaTokenQueryParamMiddleware;
use Symplicity\Outlook\RequestConfigurationTrait;
use Symplicity\Outlook\Tests\GuzzleHttpTransactionTestTrait;
use Symplicity\Outlook\Utilities\CalendarView\CalendarViewParams;
use Symplicity\Outlook\Utilities\CalendarView\GraphServiceCalendarView;

class CalendarViewParamsTest extends TestCase
{
    use GuzzleHttpTransactionTestTrait;
    use RequestConfigurationTrait;

    private readonly string $token;

    public function testValues()
    {
        $this->token = 'token';
        $params = new CalendarViewParams();
        $params->setOrderBy(['start', 'end']);
        $params->setDeltaToken('foo-delta-token==');
        $params->setTimezone('Eastern Standard Time');
        $params->setTop(10);
        $params->setSkip(50);
        $params->setSelect(['start', 'end']);
        $params->setHeaders([
            'Prefer' => 'odata.maxpagesize=50,odata.track-changes'
        ]);

        $container = [];
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{}'),
        ]);

        $client = $this->getClientWithTransactionHandler($container, $mock, CalendarViewDeltaTokenQueryParamMiddleware::init([
            'deltaToken' => $params->getDeltaToken()
        ]));

        $service = new GraphServiceCalendarView('foo', 'bar', $this->token);
        $requestConfiguration = $this->getCalendarViewRequestConfiguration($params);

        $service->setHttpClient($client);
        $service
            ->client($params)
            ->me()
            ->calendarView()
            ->delta()
            ->get($requestConfiguration)
            ->wait();

        /** @var Request $transactionRequest */
        $transactionRequest = $container[0]['request'] ?? null;
        $this->assertNotEmpty($transactionRequest);
        $this->assertSame('%24top=10&%24select=start,end&%24orderby=start,end&$deltaToken=foo-delta-token%3D%3D', $transactionRequest->getUri()->getQuery());
    }
}
