<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

use Closure;
use Microsoft\Graph\Generated\Models\DayOfWeek;
use Microsoft\Graph\Generated\Models\PatternedRecurrence;
use Microsoft\Graph\Generated\Models\RecurrencePatternType;
use Microsoft\Graph\Generated\Models\RecurrenceRange;
use Microsoft\Graph\Generated\Models\RecurrenceRangeType;
use Microsoft\Graph\Generated\Models\WeekIndex;
use Symplicity\Outlook\Interfaces\Entity\DateEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\RecurrenceEntityInterface;

class Recurrence implements RecurrenceEntityInterface
{
    protected ?RecurrencePatternType $type = null;

    protected ?int $interval = null;

    protected ?int $month = null;

    protected ?WeekIndex $index = null;

    protected ?DayOfWeek $firstDayOfWeek = null;

    protected ?int $dayOfMonth;

    protected ?RecurrenceRangeType $rangeType = null;

    protected ?DateEntityInterface $rangeDates = null;

    protected ?int $numberOfOccurrences = 0;

    protected ?Closure $occurrence = null;

    /** @var array<DayOfWeek> */
    protected array $daysOfWeek;

    public function __construct(?PatternedRecurrence $recurrence)
    {
        $pattern = $recurrence->getPattern();
        $range = $recurrence->getRange();

        $this->setType($pattern->getType());
        $this->setInterval($pattern->getInterval());
        $this->setMonth($pattern->getMonth());
        $this->setIndex($pattern->getIndex());
        $this->setFirstDayOfWeek($pattern->getFirstDayOfWeek());
        $this->setDayOfMonth($pattern->getDayOfMonth());
        $this->setDaysOfWeek($pattern->getDaysOfWeek());
        $this->setRangeType($range->getType());
        $this->setRangeDates($range);
        $this->setNumberOfOccurrences($range->getNumberOfOccurrences());
    }

    public function getType(): ?RecurrencePatternType
    {
        return $this->type;
    }

    public function getInterval(): ?int
    {
        return $this->interval;
    }

    public function getMonth(): ?int
    {
        return $this->month;
    }

    public function getIndex(): ?WeekIndex
    {
        return $this->index;
    }

    public function getFirstDayOfWeek(): ?DayOfWeek
    {
        return $this->firstDayOfWeek;
    }

    public function getDayOfMonth(): ?int
    {
        return $this->dayOfMonth;
    }

    public function getDaysOfWeek(): array
    {
        return $this->daysOfWeek;
    }

    public function getRangeType(): RecurrenceRangeType
    {
        return $this->rangeType;
    }

    public function getRangeDates(): DateEntityInterface
    {
        return $this->rangeDates;
    }

    public function getNumberOfOccurrences(): int
    {
        return $this->numberOfOccurrences;
    }

    public function getOccurrence(): Closure
    {
        return $this->occurrence;
    }

    // Mark: Mutator/Private
    public function setType(?RecurrencePatternType $type): void
    {
        $this->type = $type;
    }

    public function setInterval(?int $interval): void
    {
        $this->interval = $interval;
    }

    public function setMonth(?int $month): void
    {
        $this->month = $month;
    }

    public function setIndex(?WeekIndex $index): void
    {
        $this->index = $index;
    }

    public function setFirstDayOfWeek(?DayOfWeek $firstDayOfWeek): void
    {
        $this->firstDayOfWeek = $firstDayOfWeek;
    }

    public function setDayOfMonth(?int $dayOfMonth): void
    {
        $this->dayOfMonth = $dayOfMonth;
    }

    public function setDaysOfWeek(?array $daysOfWeek): void
    {
        $this->daysOfWeek = $daysOfWeek ?? [];
    }

    public function setRangeType(?RecurrenceRangeType $rangeType): void
    {
        $this->rangeType = $rangeType;
    }

    public function setRangeDates(?RecurrenceRange $range): void
    {
        $this->rangeDates = new DateEntity([
            'start' => $range->getStartDate(),
            'end' => $range->getEndDate(),
            'timezone' => $range->getRecurrenceTimeZone()
        ]);
    }

    public function setNumberOfOccurrences(?int $numberOfOccurrences): void
    {
        $this->numberOfOccurrences = $numberOfOccurrences;
    }
}
