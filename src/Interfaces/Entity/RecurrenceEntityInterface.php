<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Entity;

use Closure;
use Microsoft\Graph\Generated\Models\DayOfWeek;
use Microsoft\Graph\Generated\Models\RecurrencePatternType;
use Microsoft\Graph\Generated\Models\RecurrenceRangeType;
use Microsoft\Graph\Generated\Models\WeekIndex;

interface RecurrenceEntityInterface
{
    public function getType(): ?RecurrencePatternType;

    public function getInterval(): ?int;

    public function getMonth(): ?int;

    public function getIndex(): ?WeekIndex;

    public function getFirstDayOfWeek(): ?DayOfWeek;

    /**
     * @return DayOfWeek[]
     */
    public function getDaysOfWeek(): array;

    public function getDayOfMonth(): ?int;

    public function getRangeDates(): ?DateEntityInterface;

    public function getNumberOfOccurrences(): ?int;

    public function getOccurrence(): ?Closure;

    public function getRangeType(): ?RecurrenceRangeType;
}
