<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

use Closure;
use Symplicity\Outlook\Interfaces\Entity\DateEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\RecurrenceEntityInterface;
use Symplicity\Outlook\Utilities\DayOfTheWeek;
use Symplicity\Outlook\Utilities\PatternType;
use Symplicity\Outlook\Utilities\RangeType;
use Symplicity\Outlook\Utilities\RecurrenceIndex;

class Recurrence implements RecurrenceEntityInterface
{
    protected $type;
    protected $interval;
    protected $month;
    protected $index;
    protected $firstDayOfWeek;
    protected $dayOfMonth;
    protected $daysOfWeek;
    protected $rangeType;
    protected $rangeDates;
    protected $numberOfOccurrences = 0;
    protected $occurrence;

    public function __construct(array $data = [])
    {
        $this->setType($data['Pattern']['Type']);
        $this->setInterval($data['Pattern']['Interval']);
        $this->setMonth($data['Pattern']['Month']);
        $this->setIndex($data['Pattern']['Index']);
        $this->setFirstDayOfWeek($data['Pattern']['FirstDayOfWeek']);
        $this->setDayOfMonth($data['Pattern']['DayOfMonth']);
        $this->setDaysOfWeek($data['Pattern']['DaysOfWeek'] ?? []);
        $this->setRangeType($data['Range']['Type']);
        $this->setRangeDates($data);
        $this->setNumberOfOccurrences($data['Range']['NumberOfOccurrences']);
    }

    public function getType() : PatternType
    {
        return $this->type;
    }

    public function getInterval() : int
    {
        return $this->interval;
    }

    public function getMonth() : int
    {
        return $this->month;
    }

    public function getIndex() : RecurrenceIndex
    {
        return $this->index;
    }

    public function getFirstDayOfWeek() : DayOfTheWeek
    {
        return $this->firstDayOfWeek;
    }

    public function getDayOfMonth() : int
    {
        return $this->dayOfMonth;
    }

    public function getDaysOfWeek() : array
    {
        return $this->daysOfWeek;
    }

    public function getRangeType() : RangeType
    {
        return $this->rangeType;
    }

    public function getRangeDates() : DateEntityInterface
    {
        return $this->rangeDates;
    }

    public function getNumberOfOccurrences(): int
    {
        return $this->numberOfOccurrences;
    }

    public function getOccurrence() : Closure
    {
        return $this->occurrence;
    }

    // Mark: Mutator/Private
    public function setType(string $type): void
    {
        $this->type = new PatternType($type);
    }

    public function setInterval(int $interval): void
    {
        $this->interval = $interval;
    }

    public function setMonth(int $month): void
    {
        $this->month = $month;
    }

    public function setIndex(string $index): void
    {
        $this->index = new RecurrenceIndex($index);
    }

    public function setFirstDayOfWeek(string $firstDayOfWeek): void
    {
        $this->firstDayOfWeek = DayOfTheWeek::$firstDayOfWeek();
    }

    public function setDayOfMonth(int $dayOfMonth): void
    {
        $this->dayOfMonth = $dayOfMonth;
    }

    public function setDaysOfWeek(?array $daysOfWeek): void
    {
        $this->daysOfWeek = $daysOfWeek ?? [];
    }

    public function setRangeType(string $rangeType): void
    {
        $this->rangeType = new RangeType($rangeType);
    }

    public function setRangeDates(array $range): void
    {
        $this->rangeDates = new DateEntity([
            'start' => $range['Range']['StartDate'],
            'end' => $range['Range']['EndDate'],
            'timezone' => $range['Range']['RecurrenceTimeZone']
        ]);
    }

    public function setNumberOfOccurrences(int $numberOfOccurrences): void
    {
        $this->numberOfOccurrences = $numberOfOccurrences;
    }
}
