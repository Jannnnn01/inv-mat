<?php

use App\Support\DatabaseUrl;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class DatabaseUrlTest extends CIUnitTestCase
{
    public function testPostgreSqlUrlIsParsedWithoutLosingEncodedCredentials(): void
    {
        $config = DatabaseUrl::parse('postgresql://app%40user:p%40ss@db.example.test:6432/inventory?sslmode=require&channel_binding=require');

        $this->assertSame('db.example.test', $config['hostname']);
        $this->assertSame('app@user', $config['username']);
        $this->assertSame('p@ss', $config['password']);
        $this->assertSame('inventory', $config['database']);
        $this->assertSame(6432, $config['port']);
        $this->assertSame('require', $config['sslmode']);
        $this->assertFalse($config['DBDebug']);
    }

    /** @dataProvider invalidUrls */
    public function testInvalidOrNonPostgreSqlUrlsAreRejected(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);

        DatabaseUrl::parse($url);
    }

    /** @return array<string, array{string}> */
    public static function invalidUrls(): array
    {
        return [
            'empty' => [''],
            'wrong scheme' => ['mysql://user:pass@db.example.test/app'],
            'missing database' => ['postgresql://user:pass@db.example.test'],
            'invalid ssl mode' => ['postgresql://user:pass@db.example.test/app?sslmode=unsafe'],
        ];
    }
}
