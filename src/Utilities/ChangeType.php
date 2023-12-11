<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities;

enum ChangeType: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
    case MISSED = 'missed';
    case UNKNOWN = 'unknown';
}
