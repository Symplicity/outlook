<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Utilities;

use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Utilities\MigrationService;

class MigrationServiceTest extends TestCase
{
    /**
     * @testWith ["https://graph.microsoft.com", false]
     *           ["https://outlook.office.com", true]
     *           ["https://test.symplicity.com", true]
     * @return void
     */
    public function testIsTokenInvalid(string $audience, bool $expected)
    {
        $jwt = JWT::encode(['aud' => $audience, 'sub' => '123'], '123', 'HS256');
        $isMigrationRequired = MigrationService::isTokenInvalid($jwt);
        $this->assertSame($expected, $isMigrationRequired);
    }
}
