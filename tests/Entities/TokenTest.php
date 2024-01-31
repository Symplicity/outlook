<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Entities;

use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Entities\Token;

class TokenTest extends TestCase
{
    public function testToken()
    {
        $token = new Token();
        $token->setAccessToken('abc==')
            ->setExpiresIn(10800)
            ->setRefreshToken('cde==');

        $this->assertSame('abc==', $token->getAccessToken());
        $this->assertLessThanOrEqual(10800, $token->getExpiresIn());

        $token->setExpiresIn(10800, ['skip_time_check' => true]);
        $this->assertSame(10800, $token->getExpiresIn());

        $token->setExpiresIn(null);
        $this->assertNull($token->getExpiresIn());
    }
}
