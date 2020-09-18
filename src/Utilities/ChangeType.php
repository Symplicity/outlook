<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Utilities;

use MyCLabs\Enum\Enum;

/**
 * Class ChangeType
 * @method static ChangeType created()
 * @method static ChangeType updated()
 * @method static ChangeType deleted()
 * @method static ChangeType missed()
 * @method static ChangeType unknown()
 * @package Symplicity\Outlook\Utilities
 */
class ChangeType extends Enum
{
    public const created = 'Created';
    public const updated = 'Updated';
    public const deleted = 'Deleted';
    public const missed = 'Missed';
    public const unknown = 'Unknown';
}
