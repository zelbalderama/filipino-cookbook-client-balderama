<?php

declare(strict_types=1);

// Load the basic API request class.
require_once __DIR__ . '/ApiClient.php';

// Load the client-side rate-limiting class.
require_once __DIR__ . '/RateLimiter.php';

// This class wraps ApiClient and checks the rate limit
// before sending GET or POST requests to Abuan's API.
final class SecureApiClient
{
    // Store the basic API client inside this wrapper.
    private ApiClient $client;

    // Receive the API URL, Bearer token, and RateLimiter instance.
    public function __construct(
        string $baseUrl,
        string $token,
        private RateLimiter $rateLimiter
    ) {
        // Create the basic API client that performs the actual cURL requests.
        $this->client = new ApiClient(
            $baseUrl,
            $token
        );
    }

    // Send a secured GET request to the selected API endpoint.
    public function get(string $endpoint): array
    {
        // Pass the GET request to the shared request method.
        return $this->request(
            'GET',
            $endpoint
        );
    }

    // Send a secured POST request with JSON data.
    public function post(
        string $endpoint,
        array $payload
    ): array {
        // Pass the POST request and its data
        // to the shared request method.
        return $this->request(
            'POST',
            $endpoint,
            $payload
        );
    }

    // Check the rate limit before calling the actual API client.
    private function request(
        string $method,
        string $endpoint,
        ?array $payload = null
    ): array {
        // Read the IP address of the visitor using the client application.
        // Use a fallback value when the IP address is unavailable.
        $clientIp =
            $_SERVER['REMOTE_ADDR']
            ?? 'unknown-client';

        // Consume one request from the visitor's rate-limit allowance.
        $limit = $this->rateLimiter->consume(
            'cookbook-client:' . $clientIp
        );

        // Return HTTP 429 information when the visitor
        // has already reached the request limit.
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

        // Send a POST request when the method is POST.
        // Otherwise, send a GET request.
        $response = $method === 'POST'
            ? $this->client->post(
                $endpoint,
                $payload ?? []
            )
            : $this->client->get($endpoint);

        // Include the current rate-limit information in the response.
        $response['rate_limit'] = $limit;

        // Return the API response to client-api.php.
        return $response;
    }
}