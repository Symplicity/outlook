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
     * @param \DateTime $dateTime
     * @param string Format Iana Timezone $timezone
     */
    public function __construct(\DateTime $dateTime, string $timezone)
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
}
