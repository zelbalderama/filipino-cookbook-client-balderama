<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/RateLimiter.php';

/**
 * Wrapper around the existing ApiClient.
 *
 * No change is required in the selected classmate's API project.
 */
final class SecureApiClient
{
    private ApiClient $client;

    public function __construct(
        string $baseUrl,
        string $token,
        private RateLimiter $rateLimiter
    ) {
        $this->client = new ApiClient($baseUrl, $token);
    }

    public function get(string $endpoint): array
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown-client';

        /*
         * One shared request limit per visitor.
         * Add $endpoint to the key if you prefer a separate limit per endpoint.
         */
        $limit = $this->rateLimiter->consume(
            'cookbook-client:' . $clientIp
        );

        if (!$limit['allowed']) {
            return [
                'success' => false,
                'status_code' => 429,
                'message' =>
                    'Too many requests. Please try again after '
                    . $limit['retry_after']
                    . ' seconds.',
                'data' => null,
                'rate_limit' => $limit,
            ];
        }

        $response = $this->client->get($endpoint);
        $response['rate_limit'] = $limit;

        return $response;
    }
}
