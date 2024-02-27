<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities\CalendarView;

use Microsoft\Graph\Generated\Users\Item\UserItemRequestBuilder as BaseUserItemRequestBuilder;

class UserItemRequestBuilder extends BaseUserItemRequestBuilder
{
    public function calendarView(): CalendarViewRequestBuilder
    {
        return new CalendarViewRequestBuilder($this->pathParameters, $this->requestAdapter);
    }
}
