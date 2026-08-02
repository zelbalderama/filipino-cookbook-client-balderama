<?php

declare(strict_types=1);

// Load the fixed origin IDs because the selected API requires origin_id
// but does not provide a GET /api/origins endpoint.
$origins = require __DIR__ . '/../config/origins.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Kusinang Pinoy</title>

    <?php // Load the main stylesheet used by the entire client interface. ?>
    <link rel="stylesheet" href="assets/css/style.css">

    <?php // Load app.js after parsing the page so it can safely access the HTML elements. ?>
    <script src="assets/js/app.js" defer></script>
</head>

<body>
    <?php // Display the website title, API connection status, and recipe navigation link. ?>
    <header class="site-header">
        <div class="header-inner">
            <a href="index.php" class="brand">
                Kusinang Pinoy
            </a>

            <div class="header-actions">
                <?php // app.js changes this text after checking the public API endpoint. ?>
                <span id="api-status" class="status-badge">
                    Checking API…
                </span>

                <a href="#recipes" class="header-link">
                    Recipes
                </a>
            </div>
        </div>
    </header>

    <main class="page-shell">
        <?php // Introduce the client application and acknowledge the selected API developer. ?>
        <section class="intro" aria-labelledby="page-title">
            <p class="label">
                Filipino Cookbook Client
            </p>

            <h1 id="page-title">
                Filipino Recipes
            </h1>

            <p>
                Browse and manage recipes using the endpoints of
                <strong>
                    Audrey Lynn C. Abuan's
                </strong>
                Filipino Cookbook API.
            </p>

            <?php // app.js places the recipe count for every category inside this container. ?>
            <div
                id="category-summary"
                class="summary-list"
                aria-live="polite"
            ></div>
        </section>

        <?php // Display general success or error messages returned by API actions. ?>
        <section
            id="global-notice"
            class="notice"
            role="status"
            hidden
        ></section>

        <?php // Provide search, category filtering, random selection, add, and reset controls. ?>
        <section
            class="controls"
            aria-label="Recipe controls"
        >
            <?php // Submit recipe searches through GET /api/foods/search/{name}. ?>
            <form
                id="search-form"
                class="field field-search"
            >
                <label for="recipe-search">
                    Search recipe
                </label>

                <div class="input-action">
                    <input
                        id="recipe-search"
                        type="search"
                        placeholder="Example: Adobo"
                        autocomplete="off"
                    >

                    <button
                        type="submit"
                        class="button button-primary"
                    >
                        Search
                    </button>
                </div>
            </form>

            <?php // app.js fills this dropdown using GET /api/categories. ?>
            <div class="field">
                <label for="category-filter">
                    Category
                </label>

                <select id="category-filter">
                    <option value="">
                        All categories
                    </option>
                </select>
            </div>

            <?php // These buttons call the random, add, and reset functions in app.js. ?>
            <div class="control-actions">
                <button
                    type="button"
                    id="random-recipe"
                    class="button button-highlight"
                >
                    Random Recipe
                </button>

                <button
                    type="button"
                    id="add-recipe"
                    class="button button-primary"
                >
                    Add Recipe
                </button>

                <button
                    type="button"
                    id="reset-filters"
                    class="button button-secondary"
                >
                    Reset
                </button>
            </div>
        </section>

        <?php // Display recipe cards returned by GET /api/foods and other GET endpoints. ?>
        <section
            id="recipes"
            class="recipes-section"
            aria-labelledby="recipes-title"
        >
            <div class="section-heading">
                <div>
                    <h2 id="recipes-title">
                        Available Recipes
                    </h2>

                    <?php // app.js updates this text with the current number of displayed recipes. ?>
                    <p
                        id="result-count"
                        aria-live="polite"
                    >
                        Loading recipes…
                    </p>
                </div>
            </div>

            <?php // app.js creates the recipe cards inside this container. ?>
            <div
                id="recipe-list"
                class="recipe-list"
                aria-live="polite"
            ></div>

            <?php // Show this message when a search or category request returns no recipes. ?>
            <div
                id="no-results"
                class="notice"
                hidden
            >
                <h3>
                    No recipes found
                </h3>

                <p>
                    Try another search term or category.
                </p>
            </div>
        </section>

        <?php // Identify the original developer of the API used by this client. ?>
        <section
            class="api-source"
            aria-labelledby="api-source-title"
        >
            <h2 id="api-source-title">
                API Source
            </h2>

            <p>
                API developed by
                <strong>
                    Audrey Lynn C. Abuan
                </strong>.
                Used for educational purposes.
            </p>
        </section>
    </main>

    <?php // Show complete details for one recipe returned by GET /api/foods/{id}. ?>
    <dialog
        id="recipe-dialog"
        class="recipe-dialog"
        aria-labelledby="dialog-title"
    >
        <div class="dialog-header">
            <div>
                <?php // app.js displays the recipe category and origin here. ?>
                <p
                    id="dialog-meta"
                    class="dialog-meta"
                ></p>

                <?php // app.js replaces this heading with the selected recipe name. ?>
                <h2 id="dialog-title">
                    Recipe
                </h2>
            </div>

            <button
                type="button"
                class="icon-button close-dialog"
                aria-label="Close recipe details"
            >
                &times;
            </button>
        </div>

        <div class="dialog-content">
            <?php // app.js creates one list item for every ingredient returned by the API. ?>
            <section>
                <h3>
                    Ingredients
                </h3>

                <ul
                    id="dialog-ingredients"
                    class="ingredient-list"
                ></ul>
            </section>

            <?php // app.js displays the selected recipe instructions in this section. ?>
            <section>
                <h3>
                    Cooking Instructions
                </h3>

                <p
                    id="dialog-instructions"
                    class="instructions"
                ></p>
            </section>
        </div>
    </dialog>

    <?php // Provide the form that sends new recipe data to POST /api/foods. ?>
    <dialog
        id="add-dialog"
        class="recipe-dialog add-dialog"
        aria-labelledby="add-dialog-title"
    >
        <div class="dialog-header">
            <div>
                <?php // Indicate the API endpoint used when this form is submitted. ?>
                <p class="dialog-meta">
                    POST /api/foods
                </p>

                <h2 id="add-dialog-title">
                    Add New Recipe
                </h2>
            </div>

            <button
                type="button"
                class="icon-button close-dialog"
                aria-label="Close add recipe form"
            >
                &times;
            </button>
        </div>

        <form
            id="add-recipe-form"
            class="add-form"
        >
            <div class="form-grid">
                <?php // Collect the recipe name and limit it to 100 characters. ?>
                <div class="field field-wide">
                    <label for="food-name">
                        Recipe name
                    </label>

                    <input
                        id="food-name"
                        name="food_name"
                        type="text"
                        maxlength="100"
                        aria-describedby="food-name-feedback"
                        required
                    >

                    <?php // This area can display recipe-name validation feedback. ?>
                    <small
                        id="food-name-feedback"
                        class="field-feedback"
                        aria-live="polite"
                    ></small>
                </div>

                <?php // app.js fills this dropdown using category data from the API. ?>
                <div class="field">
                    <label for="food-category">
                        Category
                    </label>

                    <select
                        id="food-category"
                        name="category_id"
                        required
                    >
                        <option value="">
                            Select category
                        </option>
                    </select>
                </div>

                <?php // Use the fixed origin IDs loaded from config/origins.php. ?>
                <div class="field">
                    <label for="food-origin">
                        Origin
                    </label>

                    <select
                        id="food-origin"
                        name="origin_id"
                        required
                    >
                        <option value="">
                            Select origin
                        </option>

                        <?php // Create one option for every configured origin ID and name. ?>
                        <?php foreach ($origins as $originId => $originName): ?>
                            <option value="<?= (int) $originId ?>">
                                <?= htmlspecialchars($originName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <small>
                        The selected API requires an origin ID but
                        has no GET origins endpoint.
                    </small>
                </div>

                <?php // Collect the cooking instructions required by the POST endpoint. ?>
                <div class="field field-wide">
                    <label for="food-instructions">
                        Cooking instructions
                    </label>

                    <textarea
                        id="food-instructions"
                        name="instructions"
                        rows="5"
                        required
                    ></textarea>
                </div>

                <?php // app.js creates ingredient checkboxes using GET /api/ingredients. ?>
                <fieldset class="ingredient-field field-wide">
                    <legend>
                        Ingredients
                    </legend>

                    <p>
                        Select at least one ingredient.
                    </p>

                    <div
                        id="ingredient-options"
                        class="ingredient-options"
                    >
                        <span class="muted-text">
                            Loading ingredients…
                        </span>
                    </div>
                </fieldset>
            </div>

            <?php // Keep validation and submission feedback visible at the bottom of the dialog. ?>
            <div class="form-footer">
                <div
                    id="form-message"
                    class="form-message"
                    role="alert"
                    aria-live="assertive"
                    hidden
                ></div>

                <div class="form-actions">
                    <?php // Close the Add Recipe dialog without submitting the form. ?>
                    <button
                        type="button"
                        class="button button-secondary close-dialog"
                    >
                        Cancel
                    </button>

                    <?php // Submit the form data through app.js to client-api.php. ?>
                    <button
                        type="submit"
                        id="submit-recipe"
                        class="button button-primary"
                    >
                        Add Recipe
                    </button>
                </div>
            </div>
        </form>
    </dialog>

    <?php // Display the current year automatically using PHP. ?>
    <footer class="site-footer">
        <p>
            Kusinang Pinoy &copy; <?= date('Y') ?>
        </p>
    </footer>
</body>
</html>