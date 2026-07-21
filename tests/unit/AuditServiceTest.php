<?php

use App\Services\AuditService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AuditServiceTest extends CIUnitTestCase
{
    public function testSensitiveValuesAreNeverPreservedInAuditPayloads(): void
    {
        $payload = (new AuditService())->sanitize([
            'username'         => 'operador',
            'password'         => 'plain-secret',
            'csrf_token'       => 'csrf-secret',
            'nested'           => ['access_token' => 'token-secret', 'reason' => 'Entrada'],
            'authorization_number' => 'GUIA-001',
        ]);

        $this->assertSame('operador', $payload['username']);
        $this->assertSame('[PROTECTED]', $payload['password']);
        $this->assertSame('[PROTECTED]', $payload['csrf_token']);
        $this->assertSame('[PROTECTED]', $payload['nested']['access_token']);
        $this->assertSame('Entrada', $payload['nested']['reason']);
        $this->assertSame('GUIA-001', $payload['authorization_number']);
        $this->assertStringNotContainsString('plain-secret', json_encode($payload));
    }

    public function testLargeAndDeepPayloadsAreBounded(): void
    {
        $payload = (new AuditService())->sanitize([
            'long' => str_repeat('x', 2500),
            'deep' => ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => 'hidden']]]]]],
        ]);

        $this->assertSame(2000, mb_strlen($payload['long']));
        $this->assertSame('[TRUNCATED]', $payload['deep']['a']['b']['c']['d']['e']);
    }
}
