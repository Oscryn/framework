<?php

namespace Oscryn\Encryption;

use InvalidArgumentException;
use RuntimeException;

class Encrypter
{
    protected string $key;
    protected string $cipher = 'AES-256-CBC';

    public function __construct(string $key)
    {
        $key = $this->parseKey($key);

        if (strlen($key) !== 32) {
            throw new InvalidArgumentException('The application key must be 32 bytes (base64:... with 32 raw bytes).');
        }

        $this->key = $key;
    }

    public static function get(): static
    {
        $key = env('APP_KEY');

        if ($key === '') {
            throw new RuntimeException('No application key set. Run "php migrate.php key:generate".');
        }

        return new static($key);
    }

    public function encrypt(mixed $value): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(
            json_encode($value),
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new RuntimeException('Failed to encrypt data.');
        }

        $mac = hash_hmac('sha256', $iv.$encrypted, $this->key, true);

        return base64_encode(json_encode([
            'iv'    => base64_encode($iv),
            'value' => base64_encode($encrypted),
            'mac'   => base64_encode($mac),
        ]));
    }

    public function decrypt(string $payload): mixed
    {
        $data = json_decode(base64_decode($payload), true);

        if (!is_array($data) || !isset($data['iv'], $data['value'], $data['mac'])) {
            throw new RuntimeException('Invalid encrypted payload.');
        }

        $iv = base64_decode($data['iv']);
        $value = base64_decode($data['value']);
        $mac = base64_decode($data['mac']);

        $expected = hash_hmac('sha256', $iv.$value, $this->key, true);

        if (!hash_equals($expected, $mac)) {
            throw new RuntimeException('Invalid MAC. The payload has been tampered with.');
        }

        $decrypted = openssl_decrypt($value, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            throw new RuntimeException('Failed to decrypt data.');
        }

        return json_decode($decrypted, true);
    }

    protected function parseKey(string $key): string
    {
        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }

        return $key;
    }
}
