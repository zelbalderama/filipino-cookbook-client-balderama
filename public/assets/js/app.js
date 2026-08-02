'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const dataElement = document.getElementById('foods-data');
    const rows = Array.from(document.querySelectorAll('.recipe-row'));
    const searchInput = document.getElementById('recipe-search');
    const categoryFilter = document.getElementById('category-filter');
    const randomButton = document.getElementById('random-recipe');
    const resetButton = document.getElementById('reset-filters');
    const resultCount = document.getElementById('result-count');
    const noResults = document.getElementById('no-results');
    const dialog = document.getElementById('recipe-dialog');
    const closeDialogButton = document.getElementById('close-dialog');

    if (!dataElement) {
        return;
    }

    let foods = [];

    try {
        const parsed = JSON.parse(dataElement.textContent || '[]');
        foods = Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        console.error('Unable to read recipe data.', error);
    }

    const foodsById = new Map(
        foods.map((food) => [String(food.food_id ?? food.id ?? ''), food])
    );

    function visibleRows() {
        return rows.filter((row) => !row.hidden);
    }

    function updateCount(count) {
        if (!resultCount) {
            return;
        }

        resultCount.textContent = `${count} ${count === 1 ? 'recipe' : 'recipes'}`;
    }

    function applyFilters() {
        const query = searchInput instanceof HTMLInputElement
            ? searchInput.value.trim().toLowerCase()
            : '';

        const selectedCategory = categoryFilter instanceof HTMLSelectElement
            ? categoryFilter.value
            : '';

        let count = 0;

        rows.forEach((row) => {
            const name = (row.dataset.name || '').toLowerCase();
            const category = row.dataset.category || '';
            const matchesSearch = query === '' || name.includes(query);
            const matchesCategory = selectedCategory === '' || category === selectedCategory;
            const show = matchesSearch && matchesCategory;

            row.hidden = !show;
            if (show) {
                count += 1;
            }
        });

        updateCount(count);

        if (noResults) {
            noResults.hidden = count !== 0;
        }
    }

    function ingredientName(ingredient) {
        if (typeof ingredient === 'string') {
            return ingredient;
        }

        if (ingredient && typeof ingredient === 'object') {
            return ingredient.ingredient_name ?? ingredient.name ?? 'Ingredient';
        }

        return 'Ingredient';
    }

    function openRecipe(foodId) {
        if (!(dialog instanceof HTMLDialogElement)) {
            return;
        }

        const food = foodsById.get(String(foodId));
        if (!food) {
            return;
        }

        const title = document.getElementById('dialog-title');
        const meta = document.getElementById('dialog-meta');
        const ingredientsList = document.getElementById('dialog-ingredients');
        const instructions = document.getElementById('dialog-instructions');

        const foodName = food.food_name ?? food.name ?? 'Recipe';
        const category = food.category_name ?? food.category ?? 'Uncategorized';
        const origin = food.origin_name ?? food.origin ?? 'Not specified';
        const ingredients = Array.isArray(food.ingredients) ? food.ingredients : [];

        if (title) {
            title.textContent = String(foodName);
        }

        if (meta) {
            meta.textContent = `${category} • ${origin}`;
        }

        if (ingredientsList) {
            ingredientsList.replaceChildren();

            if (ingredients.length === 0) {
                const item = document.createElement('li');
                item.textContent = 'No ingredients listed.';
                ingredientsList.appendChild(item);
            } else {
                ingredients.forEach((ingredient) => {
                    const item = document.createElement('li');
                    item.textContent = String(ingredientName(ingredient));
                    ingredientsList.appendChild(item);
                });
            }
        }

        if (instructions) {
            instructions.textContent = String(
                food.instructions
                ?? food.cooking_instructions
                ?? 'No cooking instructions available.'
            );
        }

        dialog.showModal();
    }

    searchInput?.addEventListener('input', applyFilters);
    categoryFilter?.addEventListener('change', applyFilters);

    resetButton?.addEventListener('click', () => {
        if (searchInput instanceof HTMLInputElement) {
            searchInput.value = '';
        }

        if (categoryFilter instanceof HTMLSelectElement) {
            categoryFilter.value = '';
        }

        applyFilters();
        searchInput?.focus();
    });

    document.querySelectorAll('.view-recipe').forEach((button) => {
        button.addEventListener('click', () => {
            openRecipe(button.dataset.foodId || '');
        });
    });

    randomButton?.addEventListener('click', () => {
        const candidates = visibleRows();

        if (candidates.length === 0) {
            return;
        }

        const selected = candidates[Math.floor(Math.random() * candidates.length)];
        openRecipe(selected.dataset.foodId || '');
    });

    closeDialogButton?.addEventListener('click', () => {
        if (dialog instanceof HTMLDialogElement) {
            dialog.close();
        }
    });

    dialog?.addEventListener('click', (event) => {
        if (!(dialog instanceof HTMLDialogElement)) {
            return;
        }

        const bounds = dialog.getBoundingClientRect();
        const clickedOutside =
            event.clientX < bounds.left
            || event.clientX > bounds.right
            || event.clientY < bounds.top
            || event.clientY > bounds.bottom;

        if (clickedOutside) {
            dialog.close();
        }
    });
});
