<?php

declare(strict_types=1);

// Load the private client configuration and the secured API wrapper.
$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/SecureApiClient.php';

// Return every response from this file as JSON and prevent browser caching.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Allow a maximum of 60 client API requests within a 60-second window.
$rateLimiter = new RateLimiter(
    __DIR__ . '/../storage/rate-limits',
    60,
    60
);

// Create the API client using the base URL, Bearer token, and rate limiter.
$api = new SecureApiClient(
    (string) $config['api_base_url'],
    (string) $config['api_token'],
    $rateLimiter
);

// Send a JSON response, apply the HTTP status code, and stop the script.
function respond(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

// Forward the standardized response returned by SecureApiClient to JavaScript.
function forwardResponse(array $response): never
{
    // Use 500 when the API response does not contain a valid status code.
    $statusCode = (int) ($response['status_code'] ?? 500);

    respond([
        'success' => (bool) ($response['success'] ?? false),
        'status_code' => $statusCode,
        'message' => $response['message'] ?? null,
        'data' => $response['data'] ?? null,
        'rate_limit' => $response['rate_limit'] ?? null,
    ], $statusCode > 0 ? $statusCode : 500);
}

// Validate that a supplied value is a positive integer.
function positiveInt(mixed $value): ?int
{
    $filtered = filter_var($value, FILTER_VALIDATE_INT);

    return is_int($filtered) && $filtered > 0
        ? $filtered
        : null;
}

// Normalize recipe names before comparison by trimming spaces and ignoring letter case.
function normalizedName(string $name): string
{
    // Replace repeated spaces with one space before comparing names.
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

    // Use multibyte lowercase conversion when the PHP extension is available.
    return function_exists('mb_strtolower')
        ? mb_strtolower($name, 'UTF-8')
        : strtolower($name);
}

// Read the action requested by app.js, such as foods, search, detail, or add.
$action = trim((string) ($_GET['action'] ?? ''));

// Route each client action to the matching endpoint from Abuan's API.
switch ($action) {
    case 'status':
        // Check the public welcome endpoint to confirm that the API is running.
        forwardResponse($api->get('/'));

    case 'foods':
        // Retrieve all recipes using GET /api/foods.
        forwardResponse($api->get('/api/foods'));

    case 'categories':
        // Retrieve all categories used by the category dropdowns.
        forwardResponse($api->get('/api/categories'));

    case 'category-summary':
        // Retrieve the number of recipes stored under each category.
        forwardResponse($api->get('/api/categories/summary'));

    case 'ingredients':
        // Retrieve all ingredients used by the Add Recipe form.
        forwardResponse($api->get('/api/ingredients'));

    case 'detail':
        // Validate the food ID before requesting one recipe.
        $id = positiveInt($_GET['id'] ?? null);

        if ($id === null) {
            respond([
                'success' => false,
                'message' => 'Invalid food ID.',
            ], 400);
        }

        // Retrieve complete details for the selected recipe.
        forwardResponse($api->get('/api/foods/' . $id));

    case 'search':
        // Read and validate the recipe name entered in the search field.
        $name = trim((string) ($_GET['name'] ?? ''));

        if ($name === '' || mb_strlen($name) > 100) {
            respond([
                'success' => false,
                'message' => 'Enter a valid recipe name.',
            ], 400);
        }

        // Encode the search text safely before placing it in the endpoint URL.
        forwardResponse(
            $api->get('/api/foods/search/' . rawurlencode($name))
        );

    case 'category-foods':
        // Validate the category ID selected by the user.
        $id = positiveInt($_GET['id'] ?? null);

        if ($id === null) {
            respond([
                'success' => false,
                'message' => 'Invalid category ID.',
            ], 400);
        }

        // Retrieve recipes belonging to the selected category.
        forwardResponse(
            $api->get('/api/categories/' . $id . '/foods')
        );

    case 'random':
        // Retrieve one randomly selected recipe from the API.
        forwardResponse($api->get('/api/foods/random'));

    case 'add':
        // Only allow POST requests for creating a new recipe.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            respond([
                'success' => false,
                'message' => 'POST request required.',
            ], 405);
        }

        // Read the JSON request body sent by app.js.
        $rawBody = file_get_contents('php://input');
        $body = json_decode($rawBody ?: '', true);

        // Reject the request when the submitted body is not valid JSON.
        if (!is_array($body)) {
            respond([
                'success' => false,
                'message' => 'Invalid JSON request body.',
            ], 400);
        }

        // Extract and sanitize the required recipe values.
        $foodName = trim((string) ($body['food_name'] ?? ''));
        $categoryId = positiveInt($body['category_id'] ?? null);
        $originId = positiveInt($body['origin_id'] ?? null);
        $instructions = trim((string) ($body['instructions'] ?? ''));
        $ingredientIds = $body['ingredient_ids'] ?? [];

        // Require a recipe name with a maximum length of 100 characters.
        if ($foodName === '' || mb_strlen($foodName) > 100) {
            respond([
                'success' => false,
                'message' => 'Recipe name is required and must be 100 characters or fewer.',
            ], 400);
        }

        // Require valid category and origin IDs.
        if ($categoryId === null || $originId === null) {
            respond([
                'success' => false,
                'message' => 'Select a valid category and origin.',
            ], 400);
        }

        // Require cooking instructions before submission.
        if ($instructions === '') {
            respond([
                'success' => false,
                'message' => 'Cooking instructions are required.',
            ], 400);
        }

        // Require at least one ingredient ID in an array.
        if (!is_array($ingredientIds) || $ingredientIds === []) {
            respond([
                'success' => false,
                'message' => 'Select at least one ingredient.',
            ], 400);
        }

        // Convert ingredient IDs to positive integers and remove duplicates.
        $ingredientIds = array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $id): ?int => positiveInt($id),
                $ingredientIds
            ),
            static fn (?int $id): bool => $id !== null
        )));

        // Reject the request when no valid ingredient IDs remain.
        if ($ingredientIds === []) {
            respond([
                'success' => false,
                'message' => 'Select valid ingredients.',
            ], 400);
        }

        // Search the API first because its POST endpoint does not prevent duplicate names.
        $duplicateCheck = $api->get(
            '/api/foods/search/' . rawurlencode($foodName)
        );

        // Stop the add operation when the duplicate-check request itself fails.
        if (!($duplicateCheck['success'] ?? false)) {
            forwardResponse($duplicateCheck);
        }

        // Extract the matching recipes from either a direct array or a data wrapper.
        $matches = $duplicateCheck['data'] ?? [];

        if (isset($matches['data']) && is_array($matches['data'])) {
            $matches = $matches['data'];
        }

        // Compare normalized names to detect an exact duplicate.
        if (is_array($matches)) {
            $target = normalizedName($foodName);

            foreach ($matches as $match) {
                if (!is_array($match)) {
                    continue;
                }

                $existingName = (string) (
                    $match['food_name']
                    ?? $match['name']
                    ?? ''
                );

                if (
                    $existingName !== ''
                    && normalizedName($existingName) === $target
                ) {
                    // Return 409 Conflict so app.js can show the duplicate warning.
                    respond([
                        'success' => false,
                        'message' => 'A recipe with the same name already exists.',
                        'data' => $match,
                    ], 409);
                }
            }
        }

        // Send the validated recipe to POST /api/foods when no duplicate exists.
        forwardResponse($api->post('/api/foods', [
            'food_name' => $foodName,
            'category_id' => $categoryId,
            'origin_id' => $originId,
            'instructions' => $instructions,
            'ingredient_ids' => $ingredientIds,
        ]));

    default:
        // Return 404 when app.js requests an unsupported client action.
        respond([
            'success' => false,
            'message' => 'Unknown client API action.',
        ], 404);
}