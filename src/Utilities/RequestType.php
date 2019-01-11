<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Utilities;

use MyCLabs\Enum\Enum;

class RequestType extends Enum
{
    public const Get = 'GET';
    public const Post = 'POST';
    public const Put = 'PUT';
    public const Delete = 'DELETE';
    public const Patch = 'PATCH';
}
