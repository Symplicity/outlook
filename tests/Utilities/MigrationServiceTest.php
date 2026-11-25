<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Tests\Utilities;

use PHPUnit\Framework\Attributes\TestWith;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Symplicity\Outlook\Utilities\MigrationService;

class MigrationServiceTest extends TestCase
{
    /**
     * @return void
     */
    #[TestWith(['https://graph.microsoft.com', false])]
    #[TestWith(['https://outlook.office.com', true])]
    #[TestWith(['https://test.symplicity.com', true])]
    public function testIsTokenInvalid(string $audience, bool $expected)
    {
        $key = 'outlook_' . bin2hex(\random_bytes(32));
        $jwt = JWT::encode(['aud' => $audience, 'sub' => '123'], $key, 'HS256');
        $isMigrationRequired = MigrationService::isTokenInvalid($jwt);
        $this->assertSame($expected, $isMigrationRequired);
    }
}
