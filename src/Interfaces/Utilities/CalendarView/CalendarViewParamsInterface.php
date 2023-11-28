<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Utilities\CalendarView;

interface CalendarViewParamsInterface
{
    public function getStartDateTime(): string;

    public function getEndDateTime(): string;

    public function getFilter(): ?string;

    public function getOrderBy(): ?array;

    public function getTop(): ?int;

    public function getSkip(): ?int;

    public function getCount(): ?bool;

    public function getSelect(): ?array;

    public function getDeltaToken(): ?string;

    public function getHeaders(): ?array;

    public function getPreferHeaders(): ?string;

    public function setStartDateTime(string $startDateTime): self;

    public function setEndDateTime(string $endDateTime): self;
}
