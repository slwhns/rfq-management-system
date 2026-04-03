let currentCategoryId = null;

function normalizeSortValue(value) {
    return String(value || '').trim();
}

function sortByKey(items, keySelector) {
    return [...items].sort((left, right) => (
        normalizeSortValue(keySelector(left)).localeCompare(normalizeSortValue(keySelector(right)), undefined, {
            sensitivity: 'base',
            numeric: true,
        })
    ));
}

export function getCurrentCategoryId() {
    return currentCategoryId;
}

function renderSelectOptions(select, categories, selectedCategoryId) {
    select.innerHTML = [
        '<option value="all">All Categories</option>',
        ...categories.map((category) => `<option value="${category.id}">${category.name}</option>`),
    ].join('');

    select.value = String(selectedCategoryId);
}

export async function initPricingCategories(onCategoryChange, preferredCategoryId = null) {
    const categoryInput = document.getElementById('component-filter');
    if (!categoryInput) {
        return;
    }

    const response = await globalThis.api_request('/api/categories', 'GET');
    const categories = sortByKey(response?.data || [], (category) => category?.name);

    if (categories.length === 0) {
        currentCategoryId = null;

        if (categoryInput.tagName === 'SELECT') {
            categoryInput.innerHTML = '<option value="">No categories found</option>';
        } else {
            categoryInput.innerHTML = '';
        }

        await onCategoryChange?.(null);
        return;
    }

    const currentSelectedValue = categoryInput.value || '';
    let selectedCategoryId = preferredCategoryId ?? currentSelectedValue ?? '';

    if (selectedCategoryId !== 'all') {
        selectedCategoryId = Number(selectedCategoryId || 0) || null;
    }

    if (selectedCategoryId !== 'all' && (!selectedCategoryId || !categories.some((category) => category.id === selectedCategoryId))) {
        selectedCategoryId = 'all';
    }

    if (categoryInput.tagName === 'SELECT') {
        renderSelectOptions(categoryInput, categories, selectedCategoryId);
        categoryInput.onchange = async () => {
            currentCategoryId = categoryInput.value === 'all'
                ? 'all'
                : (Number(categoryInput.value || 0) || null);
            await onCategoryChange?.(currentCategoryId);
        };
    }

    currentCategoryId = selectedCategoryId;
    await onCategoryChange?.(selectedCategoryId);
}
