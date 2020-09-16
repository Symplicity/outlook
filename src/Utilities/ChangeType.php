<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Utilities;

use MyCLabs\Enum\Enum;

class ChangeType extends Enum
{
    public const created = 'Created';
    public const updated = 'Updated';
    public const deleted = 'Deleted';
    public const missing = 'Missing';
    public const unknown = 'Unknown';
}