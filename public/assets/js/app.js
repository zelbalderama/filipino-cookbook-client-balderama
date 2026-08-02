'use strict';

// Wait until all HTML elements are available before running the script.
document.addEventListener('DOMContentLoaded', () => {
    // This PHP file acts as the client-side gateway to Abuan's API.
    const apiUrl = 'client-api.php';

    // Header and general notification elements.
    const apiStatus = document.getElementById('api-status');
    const globalNotice = document.getElementById('global-notice');
    const categorySummary = document.getElementById('category-summary');

    // Main recipe controls.
    const searchForm = document.getElementById('search-form');
    const searchInput = document.getElementById('recipe-search');
    const categoryFilter = document.getElementById('category-filter');
    const randomButton = document.getElementById('random-recipe');
    const addRecipeButton = document.getElementById('add-recipe');
    const resetButton = document.getElementById('reset-filters');

    // Recipe list elements.
    const recipeList = document.getElementById('recipe-list');
    const resultCount = document.getElementById('result-count');
    const noResults = document.getElementById('no-results');

    // Recipe details dialog elements.
    const recipeDialog = document.getElementById('recipe-dialog');
    const dialogTitle = document.getElementById('dialog-title');
    const dialogMeta = document.getElementById('dialog-meta');
    const dialogIngredients = document.getElementById(
        'dialog-ingredients'
    );
    const dialogInstructions = document.getElementById(
        'dialog-instructions'
    );

    // Add Recipe dialog and form elements.
    const addDialog = document.getElementById('add-dialog');
    const addForm = document.getElementById('add-recipe-form');
    const foodNameInput = document.getElementById('food-name');
    const categorySelect = document.getElementById('food-category');
    const ingredientOptions = document.getElementById(
        'ingredient-options'
    );
    const formMessage = document.getElementById('form-message');
    const submitRecipeButton = document.getElementById(
        'submit-recipe'
    );

    // Sends a request to client-api.php.
    // The action determines which endpoint of Abuan's API will be called.
    async function apiRequest(action, options = {}) {
        // Add the requested action to the URL.
        const parameters = new URLSearchParams({
            action
        });

        // Add optional query parameters such as food ID or search name.
        Object.entries(options.params || {}).forEach(
            ([key, value]) => {
                parameters.set(key, String(value));
            }
        );

        // Prepare the default fetch settings.
        const fetchOptions = {
            method: options.method || 'GET',
            headers: {
                Accept: 'application/json'
            }
        };

        // Include a JSON request body when sending POST data.
        if (options.body) {
            fetchOptions.headers['Content-Type'] =
                'application/json';

            fetchOptions.body = JSON.stringify(options.body);
        }

        // Send the request to the local PHP client gateway.
        const response = await fetch(
            `${apiUrl}?${parameters.toString()}`,
            fetchOptions
        );

        let result;

        // Convert the returned JSON text into a JavaScript object.
        try {
            result = await response.json();
        } catch {
            throw new Error(
                'The client received an invalid JSON response.'
            );
        }

        // Throw an error when the HTTP request or API action failed.
        if (!response.ok || result.success === false) {
            const error = new Error(
                result.message || 'The API request failed.'
            );

            // Save the status code so duplicate errors can be detected.
            error.statusCode =
                result.status_code || response.status;

            // Save returned data for possible error details.
            error.responseData = result.data;

            throw error;
        }

        // Return only the useful API data.
        return result.data;
    }

    // Extracts a list from either a direct array or a data property.
    function getList(data) {
        if (Array.isArray(data)) {
            return data;
        }

        if (data && Array.isArray(data.data)) {
            return data.data;
        }

        return [];
    }

    // Retrieves a value using the possible property names returned by the API.
    function getValue(
        object,
        primaryKey,
        fallbackKey,
        fallback = ''
    ) {
        return String(
            object?.[primaryKey]
            ?? object?.[fallbackKey]
            ?? fallback
        );
    }

    // Displays a general success or error message above the recipe area.
    function showGlobalNotice(message, type = 'error') {
        if (!globalNotice) {
            return;
        }

        globalNotice.hidden = false;
        globalNotice.textContent = message;

        globalNotice.className =
            type === 'success'
                ? 'notice notice-success'
                : 'notice notice-error';
    }

    // Hides the general page notification.
    function hideGlobalNotice() {
        if (!globalNotice) {
            return;
        }

        globalNotice.hidden = true;
        globalNotice.textContent = '';
    }

    // Displays feedback at the bottom of the Add Recipe dialog.
    function showFormMessage(message, type = 'error') {
        if (!formMessage) {
            return;
        }

        formMessage.hidden = false;
        formMessage.textContent = message;

        formMessage.className =
            type === 'success'
                ? 'form-message form-message-success'
                : 'form-message form-message-error';

        // Scroll inside the dialog until the bottom feedback is visible.
        formMessage.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    }

    // Clears the feedback shown in the Add Recipe dialog.
    function hideFormMessage() {
        if (!formMessage) {
            return;
        }

        formMessage.hidden = true;
        formMessage.textContent = '';
        formMessage.className = 'form-message';
    }

    // Displays a loading message while waiting for API data.
    function setLoading(message = 'Loading recipes...') {
        if (resultCount) {
            resultCount.textContent = message;
        }

        if (recipeList) {
            recipeList.innerHTML =
                '<div class="loading-card">' +
                'Loading data from the API...' +
                '</div>';
        }

        if (noResults) {
            noResults.hidden = true;
        }
    }

    // Creates and displays recipe cards using food data from the API.
    function renderFoods(data) {
        const foods = getList(data);

        if (!recipeList) {
            return;
        }

        // Remove the previous recipe cards.
        recipeList.replaceChildren();

        // Update the displayed number of recipes.
        if (resultCount) {
            resultCount.textContent =
                `${foods.length} ${
                    foods.length === 1
                        ? 'recipe'
                        : 'recipes'
                }`;
        }

        // Show the empty-state message when no recipes were returned.
        if (foods.length === 0) {
            if (noResults) {
                noResults.hidden = false;
            }

            return;
        }

        if (noResults) {
            noResults.hidden = true;
        }

        // Create one card for every recipe returned by the API.
        foods.forEach((food) => {
            const foodId = getValue(
                food,
                'food_id',
                'id'
            );

            const foodName = getValue(
                food,
                'food_name',
                'name',
                'Untitled Recipe'
            );

            const categoryName = getValue(
                food,
                'category_name',
                'category',
                'Uncategorized'
            );

            const originName = getValue(
                food,
                'origin_name',
                'origin',
                'Not specified'
            );

            // Create the main recipe card.
            const card = document.createElement('article');
            card.className = 'recipe-row';

            // Create the text section of the card.
            const summary = document.createElement('div');
            summary.className = 'recipe-summary';

            const title = document.createElement('h3');
            title.textContent = foodName;

            const metadata = document.createElement('p');

            const category = document.createElement('span');
            category.textContent = categoryName;

            const separator = document.createElement('span');
            separator.textContent = '•';
            separator.setAttribute('aria-hidden', 'true');

            const origin = document.createElement('span');
            origin.textContent = originName;

            metadata.append(
                category,
                separator,
                origin
            );

            summary.append(
                title,
                metadata
            );

            // Create the button that calls GET /api/foods/{id}.
            const viewButton =
                document.createElement('button');

            viewButton.type = 'button';
            viewButton.className =
                'button button-primary view-recipe';

            viewButton.textContent = 'View Recipe';

            viewButton.addEventListener('click', () => {
                openRecipe(foodId);
            });

            card.append(
                summary,
                viewButton
            );

            recipeList.appendChild(card);
        });
    }

    // Loads all recipes through GET /api/foods.
    async function loadFoods() {
        hideGlobalNotice();
        setLoading();

        try {
            const foods = await apiRequest('foods');
            renderFoods(foods);
        } catch (error) {
            renderFoods([]);
            showGlobalNotice(error.message);
        }
    }

    // Checks whether the selected API is currently available.
    async function loadApiStatus() {
        try {
            await apiRequest('status');

            if (apiStatus) {
                apiStatus.textContent = 'API connected';
                apiStatus.className =
                    'status-badge status-online';
            }
        } catch {
            if (apiStatus) {
                apiStatus.textContent = 'API unavailable';
                apiStatus.className =
                    'status-badge status-offline';
            }
        }
    }

    // Loads categories through GET /api/categories.
    // The categories are placed in both category dropdowns.
    async function loadCategories() {
        try {
            const categories = getList(
                await apiRequest('categories')
            );

            [
                categoryFilter,
                categorySelect
            ].forEach((select) => {
                if (
                    !(select instanceof HTMLSelectElement)
                ) {
                    return;
                }

                // Keep only the first default option.
                while (select.options.length > 1) {
                    select.remove(1);
                }

                // Create one option for every API category.
                categories.forEach((category) => {
                    const id = getValue(
                        category,
                        'category_id',
                        'id'
                    );

                    const name = getValue(
                        category,
                        'category_name',
                        'name'
                    );

                    if (!id || !name) {
                        return;
                    }

                    const option =
                        document.createElement('option');

                    option.value = id;
                    option.textContent = name;

                    select.appendChild(option);
                });
            });
        } catch (error) {
            showGlobalNotice(
                `Unable to load categories: ${error.message}`
            );
        }
    }

    // Loads ingredients through GET /api/ingredients.
    async function loadIngredients() {
        if (!ingredientOptions) {
            return;
        }

        try {
            const ingredients = getList(
                await apiRequest('ingredients')
            );

            ingredientOptions.replaceChildren();

            // Create a checkbox for every available ingredient.
            ingredients.forEach((ingredient) => {
                const id = getValue(
                    ingredient,
                    'ingredient_id',
                    'id'
                );

                const name = getValue(
                    ingredient,
                    'ingredient_name',
                    'name'
                );

                if (!id || !name) {
                    return;
                }

                const label =
                    document.createElement('label');

                label.className = 'ingredient-option';

                const checkbox =
                    document.createElement('input');

                checkbox.type = 'checkbox';
                checkbox.name = 'ingredient_ids[]';
                checkbox.value = id;

                const text =
                    document.createElement('span');

                text.textContent = name;

                label.append(
                    checkbox,
                    text
                );

                ingredientOptions.appendChild(label);
            });
        } catch (error) {
            ingredientOptions.textContent =
                `Unable to load ingredients: ${error.message}`;
        }
    }

    // Loads the number of recipes under each category.
    async function loadCategorySummary() {
        if (!categorySummary) {
            return;
        }

        try {
            const summary = getList(
                await apiRequest('category-summary')
            );

            categorySummary.replaceChildren();

            summary.forEach((item) => {
                const categoryName = getValue(
                    item,
                    'category_name',
                    'name',
                    'Category'
                );

                const total = Number(
                    item.total_foods
                    ?? item.total
                    ?? 0
                );

                const badge =
                    document.createElement('span');

                badge.className = 'summary-badge';
                badge.textContent =
                    `${categoryName}: ${total}`;

                categorySummary.appendChild(badge);
            });
        } catch {
            // The summary is optional, so the rest of the page can continue.
            categorySummary.replaceChildren();
        }
    }

    // Loads one recipe through GET /api/foods/{id}.
    async function openRecipe(foodId) {
        if (
            !(recipeDialog instanceof HTMLDialogElement)
        ) {
            return;
        }

        // Show the dialog immediately with a loading state.
        if (dialogTitle) {
            dialogTitle.textContent =
                'Loading recipe...';
        }

        if (dialogMeta) {
            dialogMeta.textContent =
                'GET /api/foods/{id}';
        }

        if (dialogIngredients) {
            dialogIngredients.replaceChildren();
        }

        if (dialogInstructions) {
            dialogInstructions.textContent = '';
        }

        recipeDialog.showModal();

        try {
            const food = await apiRequest('detail', {
                params: {
                    id: foodId
                }
            });

            const foodName = getValue(
                food,
                'food_name',
                'name',
                'Recipe'
            );

            const category = getValue(
                food,
                'category_name',
                'category',
                'Uncategorized'
            );

            const origin = getValue(
                food,
                'origin_name',
                'origin',
                'Not specified'
            );

            const ingredients =
                Array.isArray(food.ingredients)
                    ? food.ingredients
                    : [];

            if (dialogTitle) {
                dialogTitle.textContent = foodName;
            }

            if (dialogMeta) {
                dialogMeta.textContent =
                    `${category} • ${origin}`;
            }

            if (dialogInstructions) {
                dialogInstructions.textContent =
                    food.instructions
                    ?? food.cooking_instructions
                    ?? 'No cooking instructions available.';
            }

            if (dialogIngredients) {
                dialogIngredients.replaceChildren();

                if (ingredients.length === 0) {
                    const item =
                        document.createElement('li');

                    item.textContent =
                        'No ingredients listed.';

                    dialogIngredients.appendChild(item);
                } else {
                    ingredients.forEach((ingredient) => {
                        const item =
                            document.createElement('li');

                        item.textContent =
                            typeof ingredient === 'string'
                                ? ingredient
                                : getValue(
                                    ingredient,
                                    'ingredient_name',
                                    'name',
                                    'Ingredient'
                                );

                        dialogIngredients.appendChild(item);
                    });
                }
            }
        } catch (error) {
            if (dialogTitle) {
                dialogTitle.textContent =
                    'Unable to load recipe';
            }

            if (dialogInstructions) {
                dialogInstructions.textContent =
                    error.message;
            }
        }
    }

    // Searches recipes using GET /api/foods/search/{name}.
    searchForm?.addEventListener(
        'submit',
        async (event) => {
            event.preventDefault();

            const searchName =
                searchInput instanceof HTMLInputElement
                    ? searchInput.value.trim()
                    : '';

            // Load all foods when the search field is empty.
            if (!searchName) {
                await loadFoods();
                return;
            }

            // Clear the category filter before searching by name.
            if (
                categoryFilter instanceof
                HTMLSelectElement
            ) {
                categoryFilter.value = '';
            }

            setLoading('Searching API...');

            try {
                const foods = await apiRequest('search', {
                    params: {
                        name: searchName
                    }
                });

                renderFoods(foods);
            } catch (error) {
                renderFoods([]);
                showGlobalNotice(error.message);
            }
        }
    );

    // Filters recipes using GET /api/categories/{id}/foods.
    categoryFilter?.addEventListener(
        'change',
        async () => {
            if (
                !(categoryFilter instanceof
                    HTMLSelectElement)
            ) {
                return;
            }

            // Clear the search field when a category is selected.
            if (
                searchInput instanceof HTMLInputElement
            ) {
                searchInput.value = '';
            }

            // Load all recipes when "All categories" is selected.
            if (!categoryFilter.value) {
                await loadFoods();
                return;
            }

            setLoading('Loading category...');

            try {
                const foods = await apiRequest(
                    'category-foods',
                    {
                        params: {
                            id: categoryFilter.value
                        }
                    }
                );

                renderFoods(foods);
            } catch (error) {
                renderFoods([]);
                showGlobalNotice(error.message);
            }
        }
    );

    // Retrieves a random recipe through GET /api/foods/random.
    randomButton?.addEventListener(
        'click',
        async () => {
            randomButton.disabled = true;
            randomButton.textContent = 'Choosing...';

            try {
                const food = await apiRequest('random');

                const foodId = getValue(
                    food,
                    'food_id',
                    'id'
                );

                await openRecipe(foodId);
            } catch (error) {
                showGlobalNotice(error.message);
            } finally {
                randomButton.disabled = false;
                randomButton.textContent =
                    'Random Recipe';
            }
        }
    );

    // Clears the active search and category filter.
    resetButton?.addEventListener(
        'click',
        async () => {
            if (
                searchInput instanceof HTMLInputElement
            ) {
                searchInput.value = '';
            }

            if (
                categoryFilter instanceof
                HTMLSelectElement
            ) {
                categoryFilter.value = '';
            }

            await loadFoods();
        }
    );

    // Opens the Add Recipe dialog.
    addRecipeButton?.addEventListener(
        'click',
        () => {
            if (
                !(addDialog instanceof HTMLDialogElement)
            ) {
                return;
            }

            hideFormMessage();
            addDialog.showModal();

            // Move the cursor to the Recipe name input.
            window.setTimeout(() => {
                foodNameInput?.focus();
            }, 100);
        }
    );

    // Removes the previous error after the user changes the recipe name.
    foodNameInput?.addEventListener(
        'input',
        () => {
            if (
                formMessage?.classList.contains(
                    'form-message-error'
                )
            ) {
                hideFormMessage();
            }
        }
    );

    // Validates and submits a new recipe through POST /api/foods.
    addForm?.addEventListener(
        'submit',
        async (event) => {
            event.preventDefault();
            hideFormMessage();

            if (
                !(addForm instanceof HTMLFormElement)
            ) {
                return;
            }

            // Read all normal form controls.
            const formData = new FormData(addForm);

            // Collect the IDs of all selected ingredients.
            const ingredientIds = Array.from(
                addForm.querySelectorAll(
                    'input[name="ingredient_ids[]"]:checked'
                )
            ).map((checkbox) => Number(checkbox.value));

            // Create the exact JSON structure expected by Abuan's API.
            const recipe = {
                food_name: String(
                    formData.get('food_name') || ''
                ).trim(),

                category_id: Number(
                    formData.get('category_id')
                ),

                origin_id: Number(
                    formData.get('origin_id')
                ),

                instructions: String(
                    formData.get('instructions') || ''
                ).trim(),

                ingredient_ids: ingredientIds
            };

            // Validate the recipe name.
            if (!recipe.food_name) {
                showFormMessage(
                    'Enter a recipe name.'
                );

                return;
            }

            // Validate the selected category.
            if (!recipe.category_id) {
                showFormMessage(
                    'Select a category.'
                );

                return;
            }

            // Validate the selected origin.
            if (!recipe.origin_id) {
                showFormMessage(
                    'Select an origin.'
                );

                return;
            }

            // Validate the cooking instructions.
            if (!recipe.instructions) {
                showFormMessage(
                    'Enter the cooking instructions.'
                );

                return;
            }

            // Require at least one selected ingredient.
            if (recipe.ingredient_ids.length === 0) {
                showFormMessage(
                    'Select at least one ingredient.'
                );

                return;
            }

            // Disable the button to prevent repeated submissions.
            if (
                submitRecipeButton instanceof
                HTMLButtonElement
            ) {
                submitRecipeButton.disabled = true;
                submitRecipeButton.textContent =
                    'Adding...';
            }

            try {
                // client-api.php checks for duplicate names first.
                // When no duplicate exists, it calls POST /api/foods.
                const response = await apiRequest('add', {
                    method: 'POST',
                    body: recipe
                });

                // Display the success message in the sticky dialog footer.
                showFormMessage(
                    response?.message
                    || 'Recipe added successfully.',
                    'success'
                );

                // Clear the form after a successful request.
                addForm.reset();

                // Refresh the recipe list and category totals.
                await Promise.all([
                    loadFoods(),
                    loadCategorySummary()
                ]);

                // Close the dialog after the user sees the success message.
                window.setTimeout(() => {
                    if (
                        addDialog instanceof
                        HTMLDialogElement
                        && addDialog.open
                    ) {
                        addDialog.close();
                    }
                }, 900);
            } catch (error) {
                // A 409 status means that the recipe name already exists.
                if (Number(error.statusCode) === 409) {
                    showFormMessage(
                        `A recipe named "${recipe.food_name}" already exists. Please add other food item.`
                    );
                } else {
                    // Display other errors returned by the API.
                    showFormMessage(error.message);
                }
            } finally {
                // Re-enable the submit button after the request finishes.
                if (
                    submitRecipeButton instanceof
                    HTMLButtonElement
                ) {
                    submitRecipeButton.disabled = false;
                    submitRecipeButton.textContent =
                        'Add Recipe';
                }
            }
        }
    );

    // Closes a dialog when one of its close buttons is clicked.
    document
        .querySelectorAll('.close-dialog')
        .forEach((button) => {
            button.addEventListener('click', () => {
                const dialog = button.closest('dialog');

                if (
                    dialog instanceof HTMLDialogElement
                ) {
                    dialog.close();
                }
            });
        });

    // Closes a dialog when the user clicks outside its visible panel.
    document
        .querySelectorAll('dialog')
        .forEach((dialog) => {
            dialog.addEventListener(
                'click',
                (event) => {
                    if (
                        !(dialog instanceof
                            HTMLDialogElement)
                    ) {
                        return;
                    }

                    const bounds =
                        dialog.getBoundingClientRect();

                    const clickedOutside =
                        event.clientX < bounds.left
                        || event.clientX > bounds.right
                        || event.clientY < bounds.top
                        || event.clientY > bounds.bottom;

                    if (clickedOutside) {
                        dialog.close();
                    }
                }
            );
        });

    // Load the initial information when the page first opens.
    Promise.allSettled([
        loadApiStatus(),
        loadCategories(),
        loadIngredients(),
        loadCategorySummary(),
        loadFoods()
    ]);
});