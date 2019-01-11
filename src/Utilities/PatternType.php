<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities;

use MyCLabs\Enum\Enum;

final class PatternType extends Enum
{
    public const Daily = 'Daily';
    public const Weekly = 'Weekly';
    public const AbsoluteMonthly = 'AbsoluteMonthly';
    public const RelativeMonthly = 'RelativeMonthly';
    public const AbsoluteYearly = 'AbsoluteYearly';
    public const RelativeYearly = 'RelativeYearly';
}
