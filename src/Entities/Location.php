<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

use Symplicity\Outlook\Interfaces\Entity\LocationInterface;

class Location implements LocationInterface
{
    protected $displayName;

    public function __construct(array $location)
    {
        $this->displayName = $location['DisplayName'];
    }

    public function getLocationDisplayName() : string
    {
        return $this->displayName;
    }
}
