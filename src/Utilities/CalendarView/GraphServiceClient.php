<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities\CalendarView;

use Microsoft\Graph\GraphServiceClient as BaseGraphServiceClient;

class GraphServiceClient extends BaseGraphServiceClient
{
    public function me(): UserItemRequestBuilder
    {
        $urlTplParameters = $this->pathParameters;
        $urlTplParameters['user%2Did'] = 'me-token-to-replace';
        return new UserItemRequestBuilder($urlTplParameters, $this->requestAdapter);
    }
}
