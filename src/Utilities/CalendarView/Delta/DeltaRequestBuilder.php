<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities\CalendarView\Delta;

use Microsoft\Graph\Generated\Users\Item\CalendarView\Delta\DeltaRequestBuilder as BaseDeltaRequestBuilder;
use Microsoft\Kiota\Abstractions\BaseRequestBuilder;
use Microsoft\Kiota\Abstractions\RequestAdapter;

class DeltaRequestBuilder extends BaseDeltaRequestBuilder
{
    /**
     * Instantiates a new DeltaRequestBuilder and sets the default values.
     * @param array<string, mixed>|string $pathParametersOrRawUrl Path parameters for the request or a String representing the raw URL.
     * @param RequestAdapter $requestAdapter The request adapter to use to execute the requests.
     */
    public function __construct($pathParametersOrRawUrl, RequestAdapter $requestAdapter)
    {
        BaseRequestBuilder::__construct($requestAdapter, [], '{+baseurl}/users/{user%2Did}/calendarView/delta(){?startDateTime*,endDateTime*,expand*,%24top,%24skip,%24search,%24filter,%24count,%24select,%24orderby}');
        if (is_array($pathParametersOrRawUrl)) {
            $this->pathParameters = $pathParametersOrRawUrl;
        } else {
            $this->pathParameters = ['request-raw-url' => $pathParametersOrRawUrl];
        }
    }
}
