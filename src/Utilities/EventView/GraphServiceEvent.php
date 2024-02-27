<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities\EventView;

use GuzzleHttp\Client;
use Microsoft\Graph\Core\Authentication\GraphPhpLeagueAuthenticationProvider;
use Microsoft\Graph\Core\GraphClientFactory;
use Microsoft\Graph\GraphRequestAdapter;
use Symplicity\Outlook\Utilities\CalendarView\GraphServiceCalendarView;
use Symplicity\Outlook\Utilities\CalendarView\GraphServiceClient;

class GraphServiceEvent extends GraphServiceCalendarView
{
    public function client(mixed $params = null): GraphServiceClient
    {
        $tokenRequestContext = $this->getClientCredentialContext();

        if (isset($params['client']) && $params['client'] instanceof Client) {
            $client = $params['client'];
        } else {
            $handlerStack = GraphClientFactory::getDefaultHandlerStack();
            $guzzleConfig = array_merge(
                static::getDefaultConfig(),
                $params[static::GUZZLE_HTTP_CONFIG_KEY] ?? [],
                ['handler' => $handlerStack]
            );
            $client = GraphClientFactory::createWithConfig($guzzleConfig);
        }

        $this->requestAdapter = new GraphRequestAdapter(
            new GraphPhpLeagueAuthenticationProvider($tokenRequestContext),
            $client
        );

        return new GraphServiceClient(
            tokenRequestContext: $tokenRequestContext,
            requestAdapter: $this->requestAdapter
        );
    }
}
