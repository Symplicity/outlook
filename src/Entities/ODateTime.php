<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

use Symplicity\Outlook\Interfaces\Entity\ODateTimeInterface;

class ODateTime implements ODateTimeInterface
{
    protected $dateTime;
    protected $timezone;

    /**
     * ODateTime constructor.
     * @param \DateTimeInterface $dateTime
     * @param string $timezone
     */
    public function __construct(\DateTimeInterface $dateTime, string $timezone)
    {
        $this->dateTime = $dateTime;
        $this->timezone = $timezone;
    }

    public function toArray() : array
    {
        return [
            'DateTime' => $this->dateTime->format('Y-m-d\TH:i:s'),
            'TimeZone' => $this->timezone
        ];
    }

    public function setDateToEndOfDay() : \DateTimeInterface
    {
        return $this->dateTime->modify('tomorrow')->setTime(0, 0, 0);
    }
}
