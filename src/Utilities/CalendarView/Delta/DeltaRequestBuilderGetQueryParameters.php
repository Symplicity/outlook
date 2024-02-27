<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities\CalendarView\Delta;

use Microsoft\Graph\Generated\Users\Item\CalendarView\Delta\DeltaRequestBuilderGetQueryParameters as BaseDeltaRequestBuilderGetQueryParameters;
use Microsoft\Kiota\Abstractions\QueryParameter;

class DeltaRequestBuilderGetQueryParameters extends BaseDeltaRequestBuilderGetQueryParameters
{
    /**
     * @var array<string>|null $expand Expand related entities
     * @QueryParameter("expand")
     */
    public ?array $expand = null;

    /**
     * @param array<string>|null $expand Expand related entities
     */
    public function __construct(?bool $count = null, ?string $endDateTime = null, ?string $filter = null, ?array $orderby = null, ?string $search = null, ?array $select = null, ?int $skip = null, ?string $startDateTime = null, ?int $top = null, ?array $expand = null)
    {
        parent::__construct($count, $endDateTime, $filter, $orderby, $search, $select, $skip, $startDateTime, $top);
        $this->expand = $expand;
    }
}
