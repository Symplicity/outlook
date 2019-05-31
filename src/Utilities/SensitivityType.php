<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities;

use MyCLabs\Enum\Enum;

class SensitivityType extends Enum
{
    public const Normal = 'Normal';
    public const Personal = 'Personal';
    public const Private = 'Private';
    public const Confidential = 'Confidential';
}
