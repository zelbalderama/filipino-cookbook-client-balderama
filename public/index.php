<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/SecureApiClient.php';

$rateLimiter = new RateLimiter(
    __DIR__ . '/../storage/rate-limits',
    30, // maximum requests
    60  // within 60 seconds
);

$api = new SecureApiClient(
    $config['api_base_url'],
    $config['api_token'],
    $rateLimiter
);

$foodsResponse = $api->get('/api/foods');
$categoriesResponse = $api->get('/api/categories');

/**
 * Return a list whether the API response is a direct JSON array or
 * wrapped inside a "data" property.
 */
function responseList(array $response): array
{
    if (!($response['success'] ?? false)) {
        return [];
    }

    $payload = $response['data'] ?? [];

    if (isset($payload['data']) && is_array($payload['data'])) {
        $payload = $payload['data'];
    }

    return is_array($payload) && array_is_list($payload) ? $payload : [];
}

$foods = responseList($foodsResponse);
$categories = responseList($categoriesResponse);

// Use categories from the food records as a fallback.
if ($categories === [] && $foods !== []) {
    $categoryNames = [];

    foreach ($foods as $food) {
        $name = trim((string) ($food['category_name'] ?? $food['category'] ?? ''));
        if ($name !== '') {
            $categoryNames[$name] = true;
        }
    }

    $categories = array_map(
        static fn (string $name): array => ['category_name' => $name],
        array_keys($categoryNames)
    );
}

$foodsJson = json_encode(
    $foods,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);

if ($foodsJson === false) {
    $foodsJson = '[]';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kusinang Pinoy</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/app.js" defer></script>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="index.php" class="brand">Kusinang Pinoy</a>
            <a href="#recipes" class="header-link">Recipes</a>
        </div>
    </header>

    <main class="page-shell">
        <section class="intro" aria-labelledby="page-title">
            <p class="label">Filipino Cookbook Client</p>
            <h1 id="page-title">Filipino Recipes</h1>
            <p>
                Search, filter, and view recipe information retrieved from
                <strong>Audrey Lynn C. Abuan's</strong> Filipino Cookbook API.
            </p>
        </section>

        <?php if (!$foodsResponse['success']): ?>
            <section class="notice notice-error" role="alert">
                <h2>Unable to load recipes</h2>
                <p>
                    Make sure the API is running at
                    <strong><?= htmlspecialchars((string) $config['api_base_url']) ?></strong>.
                </p>
                <?php if (!empty($foodsResponse['message'])): ?>
                    <p><?= htmlspecialchars((string) $foodsResponse['message']) ?></p>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="controls" aria-label="Recipe controls">
                <div class="field field-search">
                    <label for="recipe-search">Search recipe</label>
                    <input
                        id="recipe-search"
                        type="search"
                        placeholder="Example: Adobo"
                        autocomplete="off"
                    >
                </div>

                <div class="field">
                    <label for="category-filter">Category</label>
                    <select id="category-filter">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $category): ?>
                            <?php
                            $categoryName = trim((string) (
                                $category['category_name']
                                ?? $category['name']
                                ?? ''
                            ));
                            ?>
                            <?php if ($categoryName !== ''): ?>
                                <option value="<?= htmlspecialchars($categoryName) ?>">
                                    <?= htmlspecialchars($categoryName) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="control-actions">
                    <button type="button" id="random-recipe" class="button button-primary">
                        Random Recipe
                    </button>
                    <button type="button" id="reset-filters" class="button button-secondary">
                        Reset
                    </button>
                </div>
            </section>

            <section id="recipes" class="recipes-section" aria-labelledby="recipes-title">
                <div class="section-heading">
                    <div>
                        <h2 id="recipes-title">Available Recipes</h2>
                        <p id="result-count" aria-live="polite">
                            <?= count($foods) ?> <?= count($foods) === 1 ? 'recipe' : 'recipes' ?>
                        </p>
                    </div>
                </div>

                <?php if ($foods === []): ?>
                    <div class="notice">
                        <h3>No recipes available</h3>
                        <p>The API returned an empty recipe list.</p>
                    </div>
                <?php else: ?>
                    <div class="recipe-list" id="recipe-list">
                        <?php foreach ($foods as $food): ?>
                            <?php
                            $foodId = (string) ($food['food_id'] ?? $food['id'] ?? '');
                            $foodName = (string) ($food['food_name'] ?? $food['name'] ?? 'Untitled Recipe');
                            $categoryName = (string) ($food['category_name'] ?? $food['category'] ?? 'Uncategorized');
                            $originName = (string) ($food['origin_name'] ?? $food['origin'] ?? 'Not specified');
                            ?>
                            <article
                                class="recipe-row"
                                data-food-id="<?= htmlspecialchars($foodId) ?>"
                                data-name="<?= htmlspecialchars(mb_strtolower($foodName)) ?>"
                                data-category="<?= htmlspecialchars($categoryName) ?>"
                            >
                                <div class="recipe-summary">
                                    <h3><?= htmlspecialchars($foodName) ?></h3>
                                    <p>
                                        <span><?= htmlspecialchars($categoryName) ?></span>
                                        <span aria-hidden="true">•</span>
                                        <span><?= htmlspecialchars($originName) ?></span>
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="button button-primary view-recipe"
                                    data-food-id="<?= htmlspecialchars($foodId) ?>"
                                >
                                    View Recipe
                                </button>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div id="no-results" class="notice" hidden>
                        <h3>No matching recipes</h3>
                        <p>Try another search term or category.</p>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="api-source" aria-labelledby="api-source-title">
            <h2 id="api-source-title">API Source</h2>
            <p>
                API developed by <strong>Audrey Lynn C. Abuan</strong>. Used for educational purposes.
            </p>
        </section>
    </main>

    <dialog id="recipe-dialog" class="recipe-dialog" aria-labelledby="dialog-title">
        <div class="dialog-header">
            <div>
                <p id="dialog-meta" class="dialog-meta"></p>
                <h2 id="dialog-title">Recipe</h2>
            </div>
            <button type="button" id="close-dialog" class="icon-button" aria-label="Close recipe details">
                &times;
            </button>
        </div>

        <div class="dialog-content">
            <section>
                <h3>Ingredients</h3>
                <ul id="dialog-ingredients" class="ingredient-list"></ul>
            </section>

            <section>
                <h3>Cooking Instructions</h3>
                <p id="dialog-instructions" class="instructions"></p>
            </section>
        </div>
    </dialog>

    <script id="foods-data" type="application/json"><?= $foodsJson ?></script>

    <footer class="site-footer">
        <p>Kusinang Pinoy &copy; <?= date('Y') ?></p>
    </footer>
</body>
</html>
