import API from '../api/client.js';
import { loadItems } from './components.js';

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

/**
 * Categories Module
 */
export async function loadCategories() {
    const list = document.getElementById('categories');
    if (!list) {
        return;
    }

    try {
        const response = await API.getCategories();
        const categories = sortByKey(response?.data || [], (category) => category?.name);

        list.innerHTML = '';

        categories.forEach((category) => {
            const li = document.createElement('li');
            li.textContent = category.name;

            li.onclick = async () => {
                try {
                    await loadItems(category.id);
                } catch (error) {
                    console.error('Failed to load items:', error);
                    showError('Failed to load items. Please try again.');
                }
            };

            list.appendChild(li);
        });
    } catch (error) {
        console.error('Failed to load categories:', error);
        showError('Failed to load categories. Please refresh and try again.');
    }
}

function showError(message) {
    const container = document.getElementById('categories');
    if (container) {
        container.innerHTML = `<div class="error-message">${message}</div>`;
    }
}