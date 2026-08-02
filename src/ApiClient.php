<?php

declare(strict_types=1);

class ApiClient
{
    private string $baseUrl;
    private string $token;

    public function __construct(string $baseUrl, string $token)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
    }

    public function get(string $endpoint): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        $curl = curl_init($url);

        if ($curl === false) {
            return [
                'success' => false,
                'status_code' => 0,
                'message' => 'Unable to initialize the API request.',
                'data' => null,
            ];
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        curl_close($curl);

        if ($response === false || $error !== '') {
            return [
                'success' => false,
                'status_code' => $statusCode,
                'message' => 'Unable to connect to the Filipino Cookbook API.',
                'data' => null,
            ];
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            return [
                'success' => false,
                'status_code' => $statusCode,
                'message' => 'The API returned an invalid response.',
                'data' => null,
            ];
        }

        return [
            'success' => $statusCode >= 200 && $statusCode < 300,
            'status_code' => $statusCode,
            'message' => $decoded['message'] ?? null,
            'data' => $decoded,
        ];
    }
}