<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities;

use MyCLabs\Enum\Enum;

class SensitivityType extends Enum
{
    public const Normal = 0;
    public const Personal = 1;
    public const Private = 2;
    public const Confidential = 3;
}
