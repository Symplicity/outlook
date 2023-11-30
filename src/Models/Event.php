<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Models;

use Microsoft\Graph\Generated\Models\Event as MSEventModel;

class Event extends MSEventModel
{
    public function setIsDelete(): void
    {
        $this->getBackingStore()->set('delete_event', true);
    }

    public function getIsDelete(): ?bool
    {
        $val = $this->getBackingStore()->get('delete_event');
        if (is_null($val) || is_bool($val)) {
            return $val;
        }

        throw new \UnexpectedValueException("Invalid type found in backing store for 'delete_event'");
    }
}
