<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Utilities;

use MyCLabs\Enum\Enum;

/**
 * Class RequestType
 * @method static RequestType Post()
 * @method static RequestType Get()
 * @method static RequestType Put()
 * @method static RequestType Delete()
 * @method static RequestType Patch()
 * @package Symplicity\Outlook\Utilities
 */
class RequestType extends Enum
{
    public const Get = 'GET';
    public const Post = 'POST';
    public const Put = 'PUT';
    public const Delete = 'DELETE';
    public const Patch = 'PATCH';
}
