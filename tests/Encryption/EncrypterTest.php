<?php

declare(strict_types=1);

namespace Tests\Encryption;

use InvalidArgumentException;
use Oscryn\Encryption\Encrypter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EncrypterTest extends TestCase
{
    public function test_encrypt_decrypt_round_trip(): void
    {
        $encrypter = $this->encrypter();

        $encrypted = $encrypter->encrypt(['foo' => 'bar', 'n' => 1]);

        $this->assertSame(['foo' => 'bar', 'n' => 1], $encrypter->decrypt($encrypted));
    }

    public function test_decrypt_rejects_tampered_payload(): void
    {
        $encrypter = $this->encrypter();
        $encrypted = $encrypter->encrypt('secret');

        $data = json_decode(base64_decode($encrypted), true);
        $data['value'] = base64_encode('tampered');
        $tampered = base64_encode(json_encode($data));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MAC');

        $encrypter->decrypt($tampered);
    }

    public function test_decrypt_rejects_garbage_payload(): void
    {
        $this->expectException(RuntimeException::class);

        $this->encrypter()->decrypt('not-valid-base64!');
    }

    public function test_invalid_key_length_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Encrypter('too-short');
    }

    private function encrypter(): Encrypter
    {
        return new Encrypter('base64:'.base64_encode(str_repeat('a', 32)));
    }
}
