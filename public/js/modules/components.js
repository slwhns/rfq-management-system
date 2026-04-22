import API from '../api/client.js';
import { projectState } from './projects.js';

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
 * Items Module
 */
export async function loadItems(categoryId) {
    const { data } = await globalThis.api_request(`/api/components/${categoryId}`, 'GET');
    const components = sortByKey(data || [], (component) => component?.component_name);

    const container = document.getElementById('components');
    container.innerHTML = '';

    components.forEach(comp => {
        const row = document.createElement('div');

        row.className = "d-grid gtc-3 pd-10 bdr-bottom-22 ai-center";

        row.innerHTML = `
            <div class="fw-bold">
                ${comp.component_name}
            </div>

            <div class="clr-grey1">
                RM ${comp.base_price}
            </div>

            <div class="d-flex jc-end ai-center">
                <input class="w-60 pd-5 bdr-all-22 br-5"
                    type="number"
                    id="qty-${comp.id}"
                    value="1">

                <div class="bg-blue clr-white pd-5 br-5 mg-l-10 cursor-pointer"
                    onclick="addComponent(${comp.id})">
                    Add
                </div>
            </div>
        `;
        
        container.appendChild(row);
    });
}

function createComponentCard(component, projectId) {
    const div = document.createElement('div');
    div.className = `component-card ${component.is_smart_component ? 'smart' : ''}`;
    
    div.innerHTML = `
        <div class="component-header">
            <h3 class="component-name">${component.component_name}</h3>
            ${component.is_smart_component ? '<span class="smart-badge">Smart</span>' : ''}
        </div>
        <div class="component-code">${component.component_code}</div>
        <div class="component-description">${component.description || ''}</div>
        <div class="component-price">
            $${formatNumber(component.base_price)} / ${component.unit}
        </div>
        <div class="component-actions">
            <input type="number" 
                   id="qty-${component.id}" 
                   class="quantity-input" 
                   value="1" 
                   min="1">
            <button class="btn-add" data-id="${component.id}">Add to Project</button>
        </div>
    `;

    div.querySelector('.btn-add').addEventListener('click', async () => {
        const qty = document.getElementById(`qty-${component.id}`).value;
        const activeProjectId = projectId || projectState.currentProjectId;
        await addToProject(component.id, qty, activeProjectId);
    });

    return div;
}

async function addToProject(componentId, quantity, projectId) {
    try {
        if (!projectId) {
            showNotification('Please select a project first', 'error');
            return;
        }

        const response = await API.addComponent(projectId, componentId, quantity);
        
        if (response.success) {
            showNotification('Item added successfully!', 'success');
            // Trigger price recalculation
            document.dispatchEvent(new CustomEvent('component-added'));
        }
    } catch (error) {
        console.error(error);
        showNotification('Failed to add item', 'error');
    }
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(num);
}

function showNotification(message, type) {
    // Will be implemented in app.js
    const event = new CustomEvent('show-notification', { 
        detail: { message, type } 
    });
    document.dispatchEvent(event);
}

function showError(message) {
    const container = document.getElementById('components');
    if (container) {
        container.innerHTML = `<div class="error-message">${message}</div>`;
    }
}