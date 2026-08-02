<?php

declare(strict_types=1);

/**
 * Simple file-based fixed-window rate limiter.
 *
 * This limits requests made through the client application only.
 * It does not modify or protect the original classmate API from direct calls.
 */
final class RateLimiter
{
    public function __construct(
        private string $storageDirectory,
        private int $maxRequests = 30,
        private int $windowSeconds = 60
    ) {
        if ($this->maxRequests < 1 || $this->windowSeconds < 1) {
            throw new InvalidArgumentException(
                'Rate-limit values must be greater than zero.'
            );
        }

        if (
            !is_dir($this->storageDirectory)
            && !mkdir($this->storageDirectory, 0775, true)
            && !is_dir($this->storageDirectory)
        ) {
            throw new RuntimeException(
                'Unable to create the rate-limit storage directory.'
            );
        }
    }

    /**
     * @return array{
     *     allowed: bool,
     *     limit: int,
     *     remaining: int,
     *     retry_after: int,
     *     reset_at: int
     * }
     */
    public function consume(string $key): array
    {
        $now = time();
        $windowStart = $now - $this->windowSeconds;
        $file = $this->storageDirectory
            . DIRECTORY_SEPARATOR
            . hash('sha256', $key)
            . '.json';

        $handle = fopen($file, 'c+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open the rate-limit file.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock the rate-limit file.');
            }

            rewind($handle);
            $contents = stream_get_contents($handle);
            $timestamps = [];

            if (is_string($contents) && trim($contents) !== '') {
                $decoded = json_decode($contents, true);

                if (is_array($decoded)) {
                    $timestamps = array_values(array_filter(
                        $decoded,
                        static fn ($timestamp): bool =>
                            is_int($timestamp) && $timestamp > $windowStart
                    ));
                }
            }

            $currentCount = count($timestamps);

            if ($currentCount >= $this->maxRequests) {
                $oldestTimestamp = min($timestamps);
                $retryAfter = max(
                    1,
                    ($oldestTimestamp + $this->windowSeconds) - $now
                );

                return [
                    'allowed' => false,
                    'limit' => $this->maxRequests,
                    'remaining' => 0,
                    'retry_after' => $retryAfter,
                    'reset_at' => $now + $retryAfter,
                ];
            }

            $timestamps[] = $now;

            rewind($handle);
            ftruncate($handle, 0);

            $encoded = json_encode($timestamps, JSON_THROW_ON_ERROR);

            if (fwrite($handle, $encoded) === false) {
                throw new RuntimeException(
                    'Unable to save the rate-limit information.'
                );
            }

            fflush($handle);

            return [
                'allowed' => true,
                'limit' => $this->maxRequests,
                'remaining' => max(
                    0,
                    $this->maxRequests - count($timestamps)
                ),
                'retry_after' => 0,
                'reset_at' => $now + $this->windowSeconds,
            ];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
