<?php
declare(strict_type=1);

namespace Symplicity\Outlook\Interfaces\Entity;

use Symplicity\Outlook\Utilities\PatternType;
use Symplicity\Outlook\Utilities\RecurrenceIndex;

interface RecurrenceEntityInterface
{
    public function getType() : PatternType;
    public function getInterval() : int;
    public function getMonth() : int;
    public function getIndex() : RecurrenceIndex;
    public function getDaysOfWeek() : array;
    public function getDayOfMonth() : int;
    public function getRangeDates() : DateEntityInterface;
    public function getNumberOfOccurrences(): int;
}
