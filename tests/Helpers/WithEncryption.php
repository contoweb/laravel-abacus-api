<?php

namespace Contoweb\AbacusApi\Tests\Helpers;

trait WithEncryption
{
    /**
     * Set up encryption for tests
     * Generates a random APP_KEY to enable Laravel's encrypt/decrypt functions
     */
    protected function setUpEncryption(): void
    {
        $key = 'base64:' . base64_encode(random_bytes(32));

        config([
            'app.key' => $key,
            'app.cipher' => 'AES-256-CBC',
        ]);
    }
}
