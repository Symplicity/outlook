<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

use Microsoft\Kiota\Abstractions\Types\Date;
use Symplicity\Outlook\Interfaces\Entity\DateEntityInterface;

class DateEntity implements DateEntityInterface
{
    public const DEFAULT_DATETIME_FORMAT = 'Y-m-d\TH:i:s.u';

    protected ?string $start = null;
    protected ?string $end = null;
    protected ?string $timezone;
    protected ?string $modified;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        if (isset($data['start'])) {
            $this->start = (string) $data['start'];
        }

        if (isset($data['end'])) {
            $this->end = (string) $data['end'];
        }

        $this->timezone = $data['timezone'] ?? null;
        $this->modified = $data['modified'] ?? null;
    }

    public function getStartDate(): ?string
    {
        return $this->start;
    }

    public function getEndDate(): ?string
    {
        return $this->end;
    }

    public function getModifiedDate(): ?string
    {
        return $this->modified;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }
}
