<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities\EventView;

use Microsoft\Graph\Core\Authentication\GraphPhpLeagueAuthenticationProvider;
use Microsoft\Graph\Core\GraphClientFactory;
use Microsoft\Graph\GraphRequestAdapter;
use Microsoft\Graph\GraphServiceClient as MSGraphServiceClient;
use Symplicity\Outlook\Utilities\CalendarView\GraphServiceCalendarView;

class GraphServiceEvent extends GraphServiceCalendarView
{
    public function client(mixed $params = null): MSGraphServiceClient
    {
        $tokenRequestContext = $this->getClientCredentialContext();

        $handlerStack = GraphClientFactory::getDefaultHandlerStack();
        $client = GraphClientFactory::createWithConfig(array_merge(
            static::getDefaultConfig(),
            ['handler' => $handlerStack]
        ));

        $this->requestAdapter = new GraphRequestAdapter(
            new GraphPhpLeagueAuthenticationProvider($tokenRequestContext),
            $client
        );

        return new MSGraphServiceClient(
            tokenRequestContext: $tokenRequestContext,
            requestAdapter: $this->requestAdapter
        );
    }
}
