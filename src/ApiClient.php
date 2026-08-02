<?php

declare(strict_types=1);

// This class performs the actual HTTP requests
// sent from the client application to Abuan's API.
final class ApiClient
{
    // Receive and store the API base URL and Bearer token.
    public function __construct(
        private string $baseUrl,
        private string $token
    ) {
        // Remove the trailing slash to avoid duplicate slashes
        // when building endpoint URLs.
        $this->baseUrl = rtrim(
            $this->baseUrl,
            '/'
        );
    }

    // Send a GET request to an API endpoint.
    public function get(string $endpoint): array
    {
        // Pass the GET request to the shared request method.
        return $this->request(
            'GET',
            $endpoint
        );
    }

    // Send a POST request containing recipe information.
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

    // Build and send either a GET or POST cURL request.
    private function request(
        string $method,
        string $endpoint,
        ?array $payload = null
    ): array {
        // Combine the base URL and requested endpoint.
        $url = $this->baseUrl
            . '/'
            . ltrim($endpoint, '/');

        // Create a new cURL request.
        $curl = curl_init($url);

        // Return an error when cURL cannot be initialized.
        if ($curl === false) {
            return [
                'success' => false,
                'status_code' => 0,
                'message' =>
                    'Unable to initialize the API request.',
                'data' => null,
            ];
        }

        // Add the Bearer token required by the protected API endpoints.
        // Accept JSON as the expected API response format.
        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Accept: application/json',
        ];

        // Configure the main cURL request settings.
        $options = [
            // Return the server response instead of printing it directly.
            CURLOPT_RETURNTRANSFER => true,

            // Stop the request when it takes longer than 20 seconds.
            CURLOPT_TIMEOUT => 20,

            // Use the requested HTTP method such as GET or POST.
            CURLOPT_CUSTOMREQUEST =>
                strtoupper($method),

            // Attach the prepared HTTP headers.
            CURLOPT_HTTPHEADER => $headers,
        ];

        // Add a JSON request body when POST data is supplied.
        if ($payload !== null) {
            try {
                // Convert the PHP array into valid JSON.
                $json = json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR
                );
            } catch (JsonException) {
                // Return an error when the payload cannot be converted.
                return [
                    'success' => false,
                    'status_code' => 0,
                    'message' =>
                        'Unable to encode the request data.',
                    'data' => null,
                ];
            }

            // Inform the API that the request body contains JSON.
            $headers[] = 'Content-Type: application/json';

            // Update the request headers to include Content-Type.
            $options[CURLOPT_HTTPHEADER] = $headers;

            // Attach the encoded JSON as the request body.
            $options[CURLOPT_POSTFIELDS] = $json;
        }

        // Apply all prepared options to the cURL request.
        curl_setopt_array(
            $curl,
            $options
        );

        // Send the request and save the raw API response.
        $rawResponse = curl_exec($curl);

        // Read the HTTP status code returned by the API.
        $statusCode = (int) curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );

        // Read any cURL connection error.
        $error = curl_error($curl);

        // Close the cURL request and release its resources.
        curl_close($curl);

        // Return a connection error when the request failed.
        if (
            $rawResponse === false
            || $error !== ''
        ) {
            return [
                'success' => false,
                'status_code' => $statusCode,
                'message' =>
                    'Unable to connect to the Filipino Cookbook API.',
                'data' => null,
            ];
        }

        // Convert the JSON response into a PHP array.
        $decoded = json_decode(
            $rawResponse,
            true
        );

        // Reject responses that are not valid JSON objects or arrays.
        if (!is_array($decoded)) {
            return [
                'success' => false,
                'status_code' => $statusCode,
                'message' =>
                    'The API returned an invalid JSON response.',
                'data' => null,
            ];
        }

        // Return a standardized response to SecureApiClient.
        return [
            // Status codes from 200 to 299 represent successful requests.
            'success' =>
                $statusCode >= 200
                && $statusCode < 300,

            // Preserve the original HTTP status code.
            'status_code' => $statusCode,

            // Return the API message when one is available.
            'message' =>
                isset($decoded['message'])
                && is_string($decoded['message'])
                    ? $decoded['message']
                    : null,

            // Return the complete decoded response.
            'data' => $decoded,
        ];
    }
}