<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities;

use MyCLabs\Enum\Enum;

class RecurrenceIndex extends Enum
{
    public const first = 'First';
    public const second = 'Second';
    public const third = 'Third';
    public const fourth = 'Fourth';
    public const last = 'Last';
}
