<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities;

use MyCLabs\Enum\Enum;

class RangeType extends Enum
{
    public const EndDate = 'EndDate';
    public const NoEnd = 'NoEnd';
    public const Numbered = 'Numbered';
}
