<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities\CalendarView\Delta;

use Microsoft\Graph\Generated\Users\Item\CalendarView\Delta\DeltaRequestBuilderGetRequestConfiguration as BaseRequestConfiguration;

class DeltaRequestBuilderGetRequestConfiguration extends BaseRequestConfiguration
{
    /**
     * @param array<string>|null $expand Expand related entities
     */
    public static function createQueryParameters(?bool $count = null, ?string $endDateTime = null, ?string $filter = null, ?array $orderby = null, ?string $search = null, ?array $select = null, ?int $skip = null, ?string $startDateTime = null, ?int $top = null, ?array $expand = null): DeltaRequestBuilderGetQueryParameters
    {
        return new DeltaRequestBuilderGetQueryParameters($count, $endDateTime, $filter, $orderby, $search, $select, $skip, $startDateTime, $top, $expand);
    }
}
