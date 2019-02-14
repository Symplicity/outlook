<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities;

use MyCLabs\Enum\Enum;

class EventTypes extends Enum
{
    public const Single = 'SingleInstance';
    public const Occurrence = 'Occurrence';
    public const Exception = 'Exception';
    public const Master = 'SeriesMaster';
}
