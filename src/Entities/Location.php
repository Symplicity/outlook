<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

class Location
{
    protected $displayName;

    public function __construct(array $location)
    {
        $this->displayName = $location['DisplayName'];
    }

    public function getLocationDisplayName()
    {
        return $this->displayName;
    }
}
