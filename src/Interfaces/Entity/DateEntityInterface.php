<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Entity;

interface DateEntityInterface
{
    public function getStartDate() : ?string;
    public function getEndDate() : ?string;
    public function getModifiedDate() : ?string;
    public function getTimezone() : ?string;
}
