<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SharedReverbData extends Data
{
    public function __construct(
        public string $key,
        public string $host,
        public int $port,
        public string $scheme,
    ) {}

    /**
     * Read the browser connection from the config, or null while Reverb has no key or host.
     */
    public static function fromConfig(): ?self
    {
        $key = config('reverb.client.key');
        $host = config('reverb.client.host');

        if (! is_string($key) || $key === '' || ! is_string($host) || $host === '') {
            return null;
        }

        return new self($key, $host, (int) config('reverb.client.port'), (string) config('reverb.client.scheme'));
    }
}
