export const projectState = {
    currentProjectId: null,
};

let cachedProjects = [];

const projectComponentModalState = {
    projectId: null,
    projectComponentId: null,
    currentPrice: null,
    discountPercent: 0,
};

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

function formatAmount(value) {
    const amount = Number(value || 0);
    if (!Number.isFinite(amount)) {
        return '0';
    }

    const hasDecimal = Math.abs(amount % 1) > 0;
    return amount.toLocaleString('en-MY', {
        minimumFractionDigits: hasDecimal ? 2 : 0,
        maximumFractionDigits: 2,
    });
}

function formatCurrency(value, currency = 'RM') {
    return `${currency}${formatAmount(value)}`;
}

function parseProjectComponentNotes(projectComponent) {
    if (!projectComponent?.notes) {
        return {};
    }

    try {
        return JSON.parse(projectComponent.notes);
    } catch (error) {
        console.error('Failed to parse project component notes:', error);
        return {};
    }
}

function updateProjectSummaryUI(summary = {}) {
    const subtotalEl = document.getElementById('project-summary-subtotal');
    const discountEl = document.getElementById('project-summary-discount');
    const afterDiscountEl = document.getElementById('project-summary-after-discount');
    const taxEl = document.getElementById('project-summary-tax');
    const totalEl = document.getElementById('project-summary-total');

    if (subtotalEl) subtotalEl.textContent = formatCurrency(summary.after_discount ?? summary.subtotal ?? 0, 'RM');
    if (discountEl) discountEl.textContent = formatCurrency(summary.total_discount ?? 0, 'RM');
    if (afterDiscountEl) afterDiscountEl.textContent = formatCurrency(summary.after_discount ?? 0, 'RM');
    if (taxEl) taxEl.textContent = formatCurrency(summary.tax_amount ?? 0, 'RM');
    if (totalEl) totalEl.textContent = formatCurrency(summary.total ?? 0, 'RM');
}

export async function calculateProjectPricingSummary(projectId = null) {
    const targetProjectId = Number(projectId || projectState.currentProjectId || 0);
    if (!targetProjectId) {
        updateProjectSummaryUI({});
        return;
    }

    try {
        const response = await api_request(`/api/calculate/${targetProjectId}`, 'GET');
        const summary = response?.data || {};
        updateProjectSummaryUI(summary);
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to calculate project pricing']);
    }
}

export async function generateProjectQuoteFromSelection() {
    const targetProjectId = Number(projectState.currentProjectId || 0);
    if (!targetProjectId) {
        globalThis.show_popup_temp('error', 'Error', ['Please select a project first']);
        return;
    }

    try {
        const response = await api_request('/quotes/generate', 'POST', {
            project_id: targetProjectId,
        });

        const quoteNumbers = response?.data?.quote_numbers;
        if (Array.isArray(quoteNumbers) && quoteNumbers.length > 0) {
            globalThis.show_popup_temp('success', 'Purchase Request Created', quoteNumbers);
            setTimeout(() => {
                globalThis.location.href = '/quotes';
            }, 250);
            return;
        }

        const quoteNumber = response?.data?.quote_number;
        if (quoteNumber) {
            globalThis.show_popup_temp('success', 'Purchase Request Created', [quoteNumber]);
            setTimeout(() => {
                globalThis.location.href = '/quotes';
            }, 250);
            return;
        }

        globalThis.show_popup_temp('success', 'Success', ['Purchase Request generated']);
        setTimeout(() => {
            globalThis.location.href = '/quotes';
        }, 250);
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to generate Purchase Request']);
    }
}

function openEditProjectComponentModal(projectComponent, projectId) {
    const component = projectComponent?.component || {};
    const quantityInput = document.getElementById('project-component-modal-quantity');
    const discountSelect = document.getElementById('project-component-modal-discount');
    const nameLabel = document.getElementById('project-component-modal-name');
    const codeLabel = document.getElementById('project-component-modal-code');
    const overlay = document.getElementById('modal-overlay');
    const modal = document.getElementById('project-component-modal');

    if (!quantityInput || !nameLabel || !codeLabel || !overlay || !modal) {
        return;
    }

    const notes = parseProjectComponentNotes(projectComponent);
    const discountPercent = Number(notes?.discount_percent || 0);

    projectComponentModalState.projectId = projectId;
    projectComponentModalState.projectComponentId = projectComponent.id;
    projectComponentModalState.currentPrice = Number(projectComponent.custom_price ?? component.base_price ?? 0);
    projectComponentModalState.discountPercent = [0, 5, 10, 15].includes(discountPercent) ? discountPercent : 0;

    nameLabel.textContent = component.component_name || '-';
    codeLabel.textContent = component.component_code || '-';
    quantityInput.value = String(Number(projectComponent.quantity || 1));
    if (discountSelect) {
        discountSelect.value = String(projectComponentModalState.discountPercent);
    }

    if (typeof globalThis.closeAllModals === 'function') {
        globalThis.closeAllModals();
    }

    overlay.classList.add('active');
    modal.classList.add('active');
}

async function submitProjectComponentModal() {
    const quantityInput = document.getElementById('project-component-modal-quantity');
    const discountSelect = document.getElementById('project-component-modal-discount');
    const projectComponentId = projectComponentModalState.projectComponentId;
    const projectId = projectComponentModalState.projectId;
    const currentPrice = projectComponentModalState.currentPrice;

    if (!quantityInput || !projectComponentId || !projectId) {
        return;
    }

    const quantity = Number(quantityInput.value || 0);
    if (!Number.isInteger(quantity) || quantity < 1) {
        globalThis.show_popup_temp('error', 'Validation Error', ['Quantity must be a whole number greater than 0']);
        return;
    }

    const discountPercent = discountSelect
        ? Number(discountSelect.value || 0)
        : Number(projectComponentModalState.discountPercent || 0);

    if (discountSelect && ![0, 5, 10, 15].includes(discountPercent)) {
        globalThis.show_popup_temp('error', 'Validation Error', ['Discount level must be 0%, 5%, 10%, or 15%']);
        return;
    }

    try {
        await api_request(`/api/project-components/${projectComponentId}`, 'PATCH', {
            quantity,
            custom_price: currentPrice,
            discount_percent: discountPercent,
        });

        globalThis.show_popup_temp('success', 'Success', ['Project item updated']);
        if (typeof globalThis.closeAllModals === 'function') {
            globalThis.closeAllModals();
        }

        await loadProjects();
        await calculateProjectPricingSummary(projectId);
        await globalThis.refreshPricingSelectedComponents?.(projectId);
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to update project item']);
    }
}

async function deleteProjectComponent(projectComponentId, projectId) {
    const confirmed = globalThis.confirm('Delete this item from the project?');
    if (!confirmed) {
        return;
    }

    try {
        await api_request(`/api/project-components/${projectComponentId}`, 'DELETE');
        globalThis.show_popup_temp('success', 'Success', ['Project item deleted']);
        await loadProjects();
        await globalThis.refreshPricingSelectedComponents?.(projectId);
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to delete project item']);
    }
}

function renderSelectedProjectComponents(project) {
    const container = document.getElementById('selected-project-components');
    const nameBadge = document.getElementById('selected-project-name');

    if (!container) {
        return;
    }

    if (!project) {
        if (nameBadge) {
            nameBadge.textContent = 'No project selected';
        }

        container.innerHTML = '<div class="pd-10 clr-grey1">Select a project to view items.</div>';
        return;
    }

    if (nameBadge) {
        nameBadge.textContent = projectDisplayName(project) || 'Selected project';
    }

    const components = sortByKey(Array.isArray(project.components) ? project.components : [], (projectComponent) => projectComponent?.component?.component_name);

    if (components.length === 0) {
        container.innerHTML = '<div class="pd-10 clr-grey1">No items added for this project.</div>';
        return;
    }

    container.innerHTML = '';

    components.forEach((projectComponent) => {
        const component = projectComponent.component || {};
        const quantity = Number(projectComponent.quantity || 0);
        const unitPrice = Number(projectComponent.custom_price ?? component.base_price ?? 0);
        const lineTotal = quantity * unitPrice;
        const currency = component.currency || 'RM';

        const row = document.createElement('div');
        row.className = 'pd-10 bdr-bottom-22';
        row.innerHTML = `
            <div class="d-flex jc-between ai-start mg-b-2">
                <div>
                    <div class="fw-bold">${component.component_name || '-'}</div>
                    <div class="fs-12 clr-grey1">SKU: ${component.component_code || '-'}</div>
                </div>
                <div class="d-flex fd-column" style="row-gap: 2px;">
                    <button type="button" class="btn-icon" title="Edit Item" data-edit-project-component-id="${projectComponent.id}"><i class="ri-edit-box-line"></i></button>
                    <button type="button" class="btn-icon" title="Delete Item" data-delete-project-component-id="${projectComponent.id}"><i class="ri-delete-bin-5-line"></i></button>
                </div>
            </div>
            <div class="d-grid gap-10 fs-12" style="grid-template-columns: repeat(3, minmax(120px, 1fr));">
                <div><span class="clr-grey1">Qty:</span> ${quantity}</div>
                <div><span class="clr-grey1">Unit Price:</span> ${formatCurrency(unitPrice, currency)}</div>
                <div><span class="clr-grey1">Total:</span> ${formatCurrency(lineTotal, currency)}</div>
            </div>
        `;

        const editButton = row.querySelector('[data-edit-project-component-id]');
        editButton?.addEventListener('click', async () => {
            openEditProjectComponentModal(projectComponent, project.id);
        });

        const deleteButton = row.querySelector('[data-delete-project-component-id]');
        deleteButton?.addEventListener('click', async () => {
            await deleteProjectComponent(projectComponent.id, project.id);
        });

        container.appendChild(row);
    });
}

async function loadSelectedProjectComponents(projectId) {
    if (!projectId) {
        renderSelectedProjectComponents(null);
        return;
    }

    const response = await api_request(`/api/projects/${projectId}`, 'GET');
    const project = response?.data || null;
    renderSelectedProjectComponents(project);
}

function parseProjects(response) {
    if (Array.isArray(response?.data)) {
        return response.data;
    }

    if (Array.isArray(response?.data?.data)) {
        return response.data.data;
    }

    return [];
}

function projectDisplayName(project) {
    const name = String(project?.project_name || project?.name || '').trim();
    const title = String(project?.project_title || '').trim();

    if (name && title && name.toLowerCase() !== title.toLowerCase()) {
        return `${name} - ${title}`;
    }

    return name || title || 'Untitled Project';
}

function renderProjectsEmptyState(list, select) {
    if (list) {
        list.innerHTML = '<li class="pd-10 clr-grey1">No projects found.</li>';
    }

    if (select) {
        select.innerHTML = '<option value="">No projects found</option>';
    }
}

function renderProjectManagerList() {
    const container = document.getElementById('project-manager-list');
    if (!container) {
        return;
    }

    if (cachedProjects.length === 0) {
        container.innerHTML = '<div class="pd-10 clr-grey1">No projects found.</div>';
        return;
    }

    container.innerHTML = '';

    cachedProjects.forEach((project) => {
        const row = document.createElement('div');
        row.className = 'pd-12 bdr-bottom-22';

        const projectName = projectDisplayName(project);
        const location = project.location || '-';
        const projectType = project.project_type || '-';
        const taxRate = Number(project.tax_rate ?? 10).toFixed(2);
        const componentCount = project.components_count ?? 0;
        const isSelected = projectState.currentProjectId === project.id;
        const badgeMarkup = isSelected
            ? '<span class="fs-12 clr-blue">Current Project</span>'
            : '';

        row.innerHTML = `
            <div class="d-flex jc-between ai-start gap-10">
                <div>
                    <div class="d-flex ai-center gap-10 mg-b-5">
                        <div class="fw-bold">${projectName}</div>
                        ${badgeMarkup}
                    </div>
                    <div class="fs-12 clr-grey1">${location} • ${projectType} • Tax ${taxRate}%</div>
                    <div class="fs-12 clr-grey1 mg-t-5">${componentCount} components</div>
                </div>
                <div class="d-flex gap-5">
                    <button type="button" class="btn-icon" title="Edit Project" data-project-manager-edit="${project.id}"><i class="ri-edit-box-line"></i></button>
                    <button type="button" class="btn-icon" title="Delete Project" data-project-manager-delete="${project.id}"><i class="ri-delete-bin-5-line"></i></button>
                </div>
            </div>
        `;

        row.querySelector('[data-project-manager-edit]')?.addEventListener('click', () => {
            globalThis.openEditProjectModal?.(project.id);
        });

        row.querySelector('[data-project-manager-delete]')?.addEventListener('click', () => {
            globalThis.openDeleteProjectModal?.(project.id);
        });

        container.appendChild(row);
    });
}

function ensureCurrentProject(projects) {
    if (!projectState.currentProjectId && projects.length > 0) {
        projectState.currentProjectId = projects[0].id;
    }

    const hasCurrent = projects.some((project) => project.id === projectState.currentProjectId);
    if (!hasCurrent && projects.length > 0) {
        projectState.currentProjectId = projects[0].id;
    }
}

function renderProjectSelect(select, projects) {
    if (!select) {
        return;
    }

    select.innerHTML = projects.map((project) => {
        const projectName = projectDisplayName(project);
        const location = project.location || '-';
        const projectType = project.project_type || '-';

        return `<option value="${project.id}">${projectName} • ${location} • ${projectType}</option>`;
    }).join('');

    if (projectState.currentProjectId) {
        select.value = String(projectState.currentProjectId);
    }

    select.onchange = () => {
        const selectedId = Number(select.value || 0);
        if (!selectedId) {
            return;
        }

        projectState.currentProjectId = selectedId;
        loadProjects();
    };
}

function renderProjectList(list, projects) {
    if (!list) {
        return;
    }

    projects.forEach((project) => {
        const li = document.createElement('li');
        const isSelected = projectState.currentProjectId === project.id;

        li.className = `pd-10 cursor-pointer bdr-bottom-22 ${isSelected ? 'app-selected-item clr-black1 br-10' : ''}`;

        const projectName = projectDisplayName(project);
        const location = project.location || '-';
        const projectType = project.project_type || '-';
        const componentCount = project.components_count ?? 0;
        const subtitleClass = isSelected ? 'clr-black1' : 'clr-grey1';
        const actions = `
            <div class="d-flex fd-column gap-5 mg-l-10">
                <button type="button" class="btn-icon" title="Edit" onclick="event.stopPropagation(); openEditProjectModal(${project.id})"><i class="ri-edit-box-line"></i></button>
                <button type="button" class="btn-icon" title="Delete" onclick="event.stopPropagation(); openDeleteProjectModal(${project.id})"><i class="ri-delete-bin-5-line"></i></button>
            </div>
        `;

        li.innerHTML = `
            <div class="d-flex ai-center jc-between">
                <div class="d-flex ai-center">
                    <div class="fs-20 mg-r-10">📁</div>
                    <div>
                        <div class="fw-bold">${projectName}</div>
                        <div class="fs-12 ${subtitleClass}">${location} • ${projectType}</div>
                    </div>
                </div>
                <div class="d-flex ai-center">
                    <div class="fs-12 ${subtitleClass}">${componentCount} items</div>
                    ${actions}
                </div>
            </div>
        `;

        li.onclick = () => {
            projectState.currentProjectId = project.id;
            updateSelectedProjectLabel(projectName);
            loadProjects();
        };

        list.appendChild(li);
    });
}

// load projects
export async function loadProjects() {
    const response = await api_request('/api/projects', 'GET');
    const projects = sortByKey(parseProjects(response), (project) => projectDisplayName(project));
    cachedProjects = projects;

    const list = document.getElementById('project-list');
    const select = document.getElementById('project-select');

    if (!list && !select) {
        return;
    }

    if (list) {
        list.innerHTML = '';
    }

    if (select) {
        select.innerHTML = '';
    }

    if (projects.length === 0) {
        renderProjectsEmptyState(list, select);
        renderProjectManagerList();

        updateSelectedProjectLabel(null);
        await loadSelectedProjectComponents(null);
        updateProjectSummaryUI({});
        return;
    }

    ensureCurrentProject(projects);
    renderProjectSelect(select, projects);
    renderProjectList(list, projects);
    renderProjectManagerList();

    const selectedProject = projects.find((p) => p.id === projectState.currentProjectId);
    updateSelectedProjectLabel(selectedProject ? projectDisplayName(selectedProject) : null);
    await loadSelectedProjectComponents(selectedProject?.id || null);
    await calculateProjectPricingSummary(selectedProject?.id || null);
    await globalThis.refreshPricingSelectedComponents?.(selectedProject?.id || null);
}

// create project
export async function createProject() {
    const nameInput = document.getElementById('project-name');
    const locationInput = document.getElementById('project-location');
    const typeInput = document.getElementById('project-type');

    const projectName = nameInput ? nameInput.value.trim() : '';
    const location = locationInput ? locationInput.value.trim() : '';
    const projectType = typeInput ? typeInput.value : 'new';

    if (!projectName) {
        globalThis.show_popup_temp('error', 'Validation Error', ['Project name is required']);
        return;
    }

    await api_request('/api/projects', 'POST', {
        project_name: projectName,
        location,
        project_type: projectType,
    });

    if (nameInput) nameInput.value = '';
    if (locationInput) locationInput.value = '';
    if (typeInput) typeInput.value = 'new';

    globalThis.show_popup_temp('success', 'Success', ['Project created successfully']);

    await loadProjects();
}

function updateSelectedProjectLabel(projectName) {
    const badge = document.getElementById('current-project-badge');

    if (!badge) {
        return;
    }

    badge.textContent = projectName ? `Selected: ${projectName}` : 'No project selected';
}

const projectComponentModalSubmit = document.getElementById('project-component-modal-submit');
projectComponentModalSubmit?.addEventListener('click', async () => {
    await submitProjectComponentModal();
});

globalThis.calculateProjectPrice = () => calculateProjectPricingSummary(projectState.currentProjectId);
globalThis.generateProjectQuote = () => generateProjectQuoteFromSelection();
globalThis.openProjectComponentEditor = openEditProjectComponentModal;
globalThis.deleteProjectComponentAssignment = deleteProjectComponent;
globalThis.renderProjectManagerList = renderProjectManagerList;
