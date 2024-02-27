<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities\CalendarView;

use Microsoft\Graph\Generated\Users\Item\CalendarView\CalendarViewRequestBuilder as BaseCalendarViewRequestBuilder;
use Symplicity\Outlook\Utilities\CalendarView\Delta\DeltaRequestBuilder;

class CalendarViewRequestBuilder extends BaseCalendarViewRequestBuilder
{
    public function delta(): DeltaRequestBuilder
    {
        return new DeltaRequestBuilder($this->pathParameters, $this->requestAdapter);
    }
}
