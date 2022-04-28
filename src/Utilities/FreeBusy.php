<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Utilities;

use MyCLabs\Enum\Enum;

class FreeBusy extends Enum
{
    public const Busy = 'Busy';
    public const Free = 'Free';
    public const WorkingElsewhere = 'WorkingElsewhere';
    public const Tentative = 'Tentative';
    public const Away = 'Away';
}
