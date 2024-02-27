<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities\CalendarView;

use Microsoft\Graph\Generated\Users\Item\CalendarView\CalendarViewRequestBuilderGetQueryParameters;
use Symplicity\Outlook\Interfaces\Utilities\CalendarView\CalendarViewParamsInterface;

class CalendarViewParams extends CalendarViewRequestBuilderGetQueryParameters implements CalendarViewParamsInterface
{
    private ?string $deltaToken = null;

    /** @var array<string, string>|null  */
    private ?array $headers = [];

    private ?string $preferHeaders = null;
    private ?string $timezone = 'Eastern Standard Time';

    /** @var array<string, mixed>  */
    private array $requestOptions = [];

    public function getStartDateTime(): ?string
    {
        return $this->startDateTime;
    }

    public function getEndDateTime(): ?string
    {
        return $this->endDateTime;
    }

    public function getFilter(): ?string
    {
        return $this->filter;
    }

    /**
     * @return string[]|null
     */
    public function getOrderBy(): ?array
    {
        return $this->orderby;
    }

    public function getTop(): ?int
    {
        return $this->top;
    }

    public function getSkip(): ?int
    {
        return $this->skip;
    }

    public function getCount(): ?bool
    {
        return $this->count;
    }

    /**
     * @return string[]|null
     */
    public function getSelect(): ?array
    {
        return $this->select;
    }

    /**
     * @return string[]|null
     */
    public function getExpand(): ?array
    {
        return $this->expand;
    }

    public function getDeltaToken(): ?string
    {
        return $this->deltaToken;
    }

    /**
     * @return array<string, string>|null
     */
    public function getHeaders(): ?array
    {
        return $this->headers;
    }

    public function getPreferHeaders(): ?string
    {
        return $this->preferHeaders ?? 'odata.maxpagesize=50,odata.track-changes,outlook.timezone="' . $this->timezone . '"';
    }

    public function getRequestOptions(): array
    {
        return $this->requestOptions;
    }

    public function setStartDateTime(string $startDateTime): CalendarViewParams
    {
        $this->startDateTime = $startDateTime;
        return $this;
    }

    public function setEndDateTime(string $endDateTime): CalendarViewParams
    {
        $this->endDateTime = $endDateTime;
        return $this;
    }

    public function setFilter(?string $filter): CalendarViewParams
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * @param string[]|null $expand
     */
    public function setExpand(?array $expand): CalendarViewParams
    {
        $this->expand = $expand;
        return $this;
    }

    /**
     * @param string[]|null $orderBy
     */
    public function setOrderBy(?array $orderBy): CalendarViewParams
    {
        $this->orderby = $orderBy;
        return $this;
    }

    public function setTop(?int $top): CalendarViewParams
    {
        $this->top = $top;
        return $this;
    }

    public function setSkip(?int $skip): CalendarViewParams
    {
        $this->skip = $skip;
        return $this;
    }

    public function setCount(?bool $count): CalendarViewParams
    {
        $this->count = $count;
        return $this;
    }

    /**
     * @param string[]|null $select
     */
    public function setSelect(?array $select): CalendarViewParams
    {
        $this->select = $select;
        return $this;
    }

    public function setDeltaToken(?string $deltaToken): CalendarViewParams
    {
        $this->deltaToken = $deltaToken;
        return $this;
    }

    /**
     * @param array<string, string>|null $headers
     */
    public function setHeaders(?array $headers): CalendarViewParams
    {
        $this->headers = $headers;
        return $this;
    }

    public function setPreferHeaders(?string $preferHeaders): CalendarViewParams
    {
        $this->preferHeaders = $preferHeaders;
        return $this;
    }

    public function setTimezone(?string $timezone): CalendarViewParams
    {
        $this->timezone = $timezone;
        return $this;
    }

    /**
     * @param array<string, mixed> $requestOptions
     */
    public function setRequestOptions(array $requestOptions): CalendarViewParams
    {
        $this->requestOptions = $requestOptions;
        return $this;
    }
}
