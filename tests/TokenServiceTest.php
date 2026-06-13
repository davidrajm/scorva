<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;
use ProjectReviews\Services\Crypto;
use ProjectReviews\Services\TokenService;

final class TokenServiceTest extends TestCase
{
    private TokenService $service;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__) . '/includes/services/Crypto.php';
        require_once dirname(__DIR__) . '/includes/services/TokenService.php';
        $this->service = new TokenService();
    }

    public function test_generate_token_is_64_char_hex_and_unique(): void
    {
        $first = $this->service->generate_token();
        $second = $this->service->generate_token();

        $this->assertMatchesRegularExpression(TokenService::TOKEN_PATTERN, $first);
        $this->assertSame(64, strlen($first));
        $this->assertNotSame($first, $second);
    }

    public function test_is_valid_token_format(): void
    {
        $this->assertTrue($this->service->is_valid_token_format($this->service->generate_token()));
        $this->assertFalse($this->service->is_valid_token_format(''));
        $this->assertFalse($this->service->is_valid_token_format('not-a-token'));
        $this->assertFalse($this->service->is_valid_token_format(str_repeat('z', 64)));
        $this->assertFalse($this->service->is_valid_token_format(substr($this->service->generate_token(), 0, 63)));
    }

    public function test_generate_password_has_expected_length(): void
    {
        $password = $this->service->generate_password();

        $this->assertSame(12, strlen($password));
        $this->assertNotSame($password, $this->service->generate_password());
    }

    public function test_password_hash_round_trip(): void
    {
        $password = 'Reviewer#42!';
        $hash = $this->service->hash_password($password);

        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertTrue($this->service->verify_password($password, $hash));
        $this->assertFalse($this->service->verify_password('wrong-pass', $hash));
        $this->assertFalse($this->service->verify_password('', $hash));
        $this->assertFalse($this->service->verify_password($password, ''));
    }

    public function test_password_encryption_round_trip(): void
    {
        $password = 'Reviewer#42!';
        $encrypted = $this->service->encrypt_password($password);

        $this->assertNotSame($password, $encrypted);
        $this->assertStringStartsWith(Crypto::ENC_PREFIX, $encrypted);
        $this->assertSame($password, $this->service->decrypt_password($encrypted));
    }

    public function test_encryption_is_idempotent(): void
    {
        $once = $this->service->encrypt_password('Reviewer#42!');
        $twice = $this->service->encrypt_password($once);

        $this->assertSame($once, $twice);
        $this->assertSame('Reviewer#42!', $this->service->decrypt_password($twice));
    }
}
