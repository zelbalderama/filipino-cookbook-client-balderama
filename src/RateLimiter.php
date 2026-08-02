<?php

declare(strict_types=1);

// This class provides a simple file-based rate-limiting system.
// It limits requests passing through the client application only.
// It does not modify or protect Abuan's original API from direct requests.
final class RateLimiter
{
    // Store the rate-limit configuration when the class is created.
    public function __construct(
        private string $storageDirectory,
        private int $maxRequests = 30,
        private int $windowSeconds = 60
    ) {
        // Reject invalid values because the request limit and time window
        // must always be greater than zero.
        if (
            $this->maxRequests < 1
            || $this->windowSeconds < 1
        ) {
            throw new InvalidArgumentException(
                'Rate-limit values must be greater than zero.'
            );
        }

        // Create the storage directory when it does not exist yet.
        // The directory stores JSON files containing request timestamps.
        if (
            !is_dir($this->storageDirectory)
            && !mkdir(
                $this->storageDirectory,
                0775,
                true
            )
            && !is_dir($this->storageDirectory)
        ) {
            throw new RuntimeException(
                'Unable to create the rate-limit storage directory.'
            );
        }
    }

    // Record one request for the supplied key.
    // The key normally contains the visitor's IP address.
    public function consume(string $key): array
    {
        // Get the current Unix timestamp.
        $now = time();

        // Calculate the earliest timestamp that is still inside
        // the active rate-limit window.
        $windowStart = $now - $this->windowSeconds;

        // Hash the key so that the visitor's raw IP address
        // is not directly used as the filename.
        $file = $this->storageDirectory
            . DIRECTORY_SEPARATOR
            . hash('sha256', $key)
            . '.json';

        // Open or create the rate-limit file.
        // The c+ mode allows both reading and writing.
        $handle = fopen($file, 'c+');

        // Stop when the file cannot be opened.
        if ($handle === false) {
            throw new RuntimeException(
                'Unable to open the rate-limit file.'
            );
        }

        try {
            // Lock the file so simultaneous requests cannot update it
            // at the same time and damage the saved data.
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException(
                    'Unable to lock the rate-limit file.'
                );
            }

            // Move the file pointer to the beginning before reading.
            rewind($handle);

            // Read the previously saved request timestamps.
            $contents = stream_get_contents($handle);

            // Start with an empty timestamp list.
            $timestamps = [];

            // Decode the file only when it contains data.
            if (
                is_string($contents)
                && trim($contents) !== ''
            ) {
                $decoded = json_decode(
                    $contents,
                    true
                );

                // Keep only valid timestamps that are still
                // inside the current rate-limit window.
                if (is_array($decoded)) {
                    $timestamps = array_values(
                        array_filter(
                            $decoded,
                            static fn ($timestamp): bool =>
                                is_int($timestamp)
                                && $timestamp > $windowStart
                        )
                    );
                }
            }

            // Count the requests still active in the current window.
            $currentCount = count($timestamps);

            // Block the request when the maximum limit has been reached.
            if ($currentCount >= $this->maxRequests) {
                // Find the oldest active request timestamp.
                $oldestTimestamp = min($timestamps);

                // Calculate how many seconds the user must wait
                // before another request is allowed.
                $retryAfter = max(
                    1,
                    (
                        $oldestTimestamp
                        + $this->windowSeconds
                    ) - $now
                );

                // Return information showing that the request is blocked.
                return [
                    'allowed' => false,
                    'limit' => $this->maxRequests,
                    'remaining' => 0,
                    'retry_after' => $retryAfter,
                    'reset_at' => $now + $retryAfter,
                ];
            }

            // Record the current request timestamp.
            $timestamps[] = $now;

            // Move back to the beginning of the file.
            rewind($handle);

            // Remove the old file contents before saving the new list.
            ftruncate($handle, 0);

            // Convert the timestamp list into JSON.
            $encoded = json_encode(
                $timestamps,
                JSON_THROW_ON_ERROR
            );

            // Save the updated timestamps to the file.
            if (fwrite($handle, $encoded) === false) {
                throw new RuntimeException(
                    'Unable to save the rate-limit information.'
                );
            }

            // Immediately write buffered data to the file.
            fflush($handle);

            // Return information showing that the request is allowed.
            return [
                'allowed' => true,
                'limit' => $this->maxRequests,
                'remaining' => max(
                    0,
                    $this->maxRequests
                    - count($timestamps)
                ),
                'retry_after' => 0,
                'reset_at' =>
                    $now + $this->windowSeconds,
            ];
        } finally {
            // Always unlock the file even when an error occurs.
            flock($handle, LOCK_UN);

            // Close the file handle and release system resources.
            fclose($handle);
        }
    }
}