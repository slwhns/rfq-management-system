// Modal state management
const modalState = {
    currentCategoryId: null,
    currentComponentId: null,
    currentComponentCategoryId: null,
    currentComponentAssignmentId: null,
    currentProjectId: null,
    componentModalStep: 1, // Track which step of component modal
    componentModalSelectedSupplierId: null, // Track selected company for component
};

let modalSuppliersCache = [];

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

function closeAllModals() {
    document.getElementById('modal-overlay')?.classList.remove('active');
    document.querySelectorAll('.modal-dialog.active').forEach((modal) => {
        modal.classList.remove('active');
    });
}

function openModal(modalId) {
    closeAllModals();
    const overlay = document.getElementById('modal-overlay');
    const modal = document.getElementById(modalId);
    if (overlay) overlay.classList.add('active');
    if (modal) modal.classList.add('active');
}

// Component modal step navigation
function componentModalGoToStep1() {
    const step1 = document.getElementById('component-modal-step1');
    const step2 = document.getElementById('component-modal-step2');
    const footer1 = document.getElementById('component-modal-step1-footer');
    const footer2 = document.getElementById('component-modal-step2-footer');

    if (step1) step1.style.display = '';
    if (step2) step2.style.display = 'none';
    if (footer1) footer1.style.display = '';
    if (footer2) footer2.style.display = 'none';

    modalState.componentModalStep = 1;
}

function componentModalGoToStep2() {
    const categoryId = Number(document.getElementById('modal-component-category-id').value || 0);
    const supplierId = Number(document.getElementById('modal-component-supplier-id').value || 0);

    if (!categoryId || !supplierId) {
        globalThis.show_popup_temp('error', 'Validation Error', ['Please select both category and company']);
        return;
    }

    modalState.componentModalSelectedSupplierId = supplierId;

    const step1 = document.getElementById('component-modal-step1');
    const step2 = document.getElementById('component-modal-step2');
    const footer1 = document.getElementById('component-modal-step1-footer');
    const footer2 = document.getElementById('component-modal-step2-footer');

    if (step1) step1.style.display = 'none';
    if (step2) step2.style.display = '';
    if (footer1) footer1.style.display = 'none';
    if (footer2) footer2.style.display = '';

    modalState.componentModalStep = 2;
}

function handleComponentStep2SecondaryAction() {
    if (modalState.currentComponentId) {
        closeAllModals();
        return;
    }

    componentModalGoToStep1();
}

function openAddProjectModal() {
    modalState.currentProjectId = null;

    const nameInput = document.getElementById('modal-project-name');
    const locationInput = document.getElementById('modal-project-location');
    const typeInput = document.getElementById('modal-project-type');
    const taxRateInput = document.getElementById('modal-project-tax-rate');
    const title = document.getElementById('project-modal-title');
    const submit = document.getElementById('project-modal-submit');

    if (!nameInput || !locationInput || !typeInput || !taxRateInput || !title || !submit) {
        return;
    }

    nameInput.value = '';
    locationInput.value = '';
    typeInput.value = 'new';
    taxRateInput.value = '10.00';
    title.textContent = 'Add Project';
    submit.textContent = 'Add';

    openModal('project-modal');
}

async function openEditProjectModal(projectId) {
    try {
        const response = await globalThis.api_request(`/api/projects/${projectId}`, 'GET');
        const project = response?.data;

        if (!project) {
            globalThis.show_popup_temp('error', 'Error', ['Project not found']);
            return;
        }

        const nameInput = document.getElementById('modal-project-name');
        const locationInput = document.getElementById('modal-project-location');
        const typeInput = document.getElementById('modal-project-type');
        const taxRateInput = document.getElementById('modal-project-tax-rate');
        const title = document.getElementById('project-modal-title');
        const submit = document.getElementById('project-modal-submit');

        if (!nameInput || !locationInput || !typeInput || !taxRateInput || !title || !submit) {
            return;
        }

        modalState.currentProjectId = projectId;
        nameInput.value = project.project_name || project.name || '';
        locationInput.value = project.location || '';
        typeInput.value = project.project_type || 'new';
        taxRateInput.value = Number(project.tax_rate ?? 10).toFixed(2);
        title.textContent = 'Edit Project';
        submit.textContent = 'Update';

        openModal('project-modal');
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', ['Failed to load project']);
    }
}

async function submitProjectModal() {
    try {
        const nameInput = document.getElementById('modal-project-name');
        const locationInput = document.getElementById('modal-project-location');
        const typeInput = document.getElementById('modal-project-type');
        const taxRateInput = document.getElementById('modal-project-tax-rate');

        if (!nameInput || !locationInput || !typeInput || !taxRateInput) {
            return;
        }

        const projectName = nameInput.value.trim();
        const location = locationInput.value.trim();
        const projectType = typeInput.value;
        const taxRate = Number(taxRateInput.value || 0);

        if (!projectName) {
            globalThis.show_popup_temp('error', 'Validation Error', ['Project name is required']);
            return;
        }

        if (!Number.isFinite(taxRate) || taxRate < 0 || taxRate > 100) {
            globalThis.show_popup_temp('error', 'Validation Error', ['Tax rate must be between 0 and 100']);
            return;
        }

        const payload = {
            project_name: projectName,
            location,
            project_type: projectType,
            tax_rate: taxRate,
        };

        if (modalState.currentProjectId) {
            await globalThis.api_request(`/api/projects/${modalState.currentProjectId}`, 'PATCH', payload);
            globalThis.show_popup_temp('success', 'Success', ['Project updated']);
        } else {
            await globalThis.api_request('/api/projects', 'POST', payload);
            globalThis.show_popup_temp('success', 'Success', ['Project created']);
        }

        closeAllModals();
        await globalThis.loadProjects?.();
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to save project']);
    }
}

function openDeleteProjectModal(projectId) {
    modalState.currentProjectId = projectId;

    const message = document.getElementById('delete-modal-message');
    const confirmButton = document.getElementById('delete-modal-confirm');

    if (!message || !confirmButton) {
        return;
    }

    message.textContent = 'Are you sure you want to delete this project?';
    confirmButton.onclick = () => confirmDeleteProject();
    openModal('delete-modal');
}

function openProjectManagerModal() {
    globalThis.renderProjectManagerList?.();
    openModal('project-manager-modal');
}

async function confirmDeleteProject() {
    try {
        const projectId = modalState.currentProjectId;
        if (!projectId) {
            return;
        }

        await globalThis.api_request(`/api/projects/${projectId}`, 'DELETE');

        closeAllModals();
        await globalThis.loadProjects?.();
        globalThis.show_popup_temp('success', 'Success', ['Project deleted']);
    } catch (error) {
        console.error(error);
        closeAllModals();
        globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to delete project']);
    }
}

function openAddCategoryModal() {
    modalState.currentCategoryId = null;
    document.getElementById('modal-category-name').value = '';
    document.getElementById('modal-category-icon').value = '';
    document.getElementById('modal-category-description').value = '';
    document.getElementById('category-modal-title').textContent = 'Add Category';
    document.getElementById('category-modal-submit').textContent = 'Add';
    openModal('category-modal');
}

async function openEditCategoryModal(categoryId) {
    try {
        const response = await globalThis.api_request('/api/categories', 'GET');
        const categories = response?.data || [];
        const category = categories.find((item) => item.id === categoryId);

        if (!category) {
            globalThis.show_popup_temp('error', 'Error', ['Category not found']);
            return;
        }

        modalState.currentCategoryId = categoryId;
        document.getElementById('modal-category-name').value = category.name || '';
        document.getElementById('modal-category-icon').value = category.icon || '';
        document.getElementById('modal-category-description').value = category.description || '';
        document.getElementById('category-modal-title').textContent = 'Edit Category';
        document.getElementById('category-modal-submit').textContent = 'Update';
        openModal('category-modal');
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', ['Failed to load category']);
    }
}

async function submitCategoryModal() {
    try {
        const name = document.getElementById('modal-category-name').value.trim();
        const icon = document.getElementById('modal-category-icon').value.trim() || '📦';
        const description = document.getElementById('modal-category-description').value.trim() || null;

        if (!name) {
            globalThis.show_popup_temp('error', 'Validation Error', ['Category name is required']);
            return;
        }

        if (modalState.currentCategoryId) {
            // Update
            await globalThis.api_request(`/api/items/categories/${modalState.currentCategoryId}`, 'PATCH', {
                name,
                icon,
                description,
            });
            globalThis.show_popup_temp('success', 'Success', ['Category updated']);
        } else {
            // Create
            await globalThis.api_request('/api/categories', 'POST', {
                name,
                icon,
                description,
            });
            globalThis.show_popup_temp('success', 'Success', ['Category created']);
        }

        closeAllModals();
        await globalThis.loadCategories?.();
        await globalThis.loadCategoryOptions?.();
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to save category']);
    }
}

function openDeleteCategoryModal(categoryId) {
    modalState.currentCategoryId = categoryId;
    document.getElementById('delete-modal-message').textContent = 'Are you sure you want to delete this category?';
    document.getElementById('delete-modal-confirm').onclick = () => confirmDeleteCategory();
    openModal('delete-modal');
}

function openDeleteComponentModal(componentId, categoryId) {
    modalState.currentComponentId = componentId;
    modalState.currentComponentCategoryId = categoryId;
    modalState.currentComponentAssignmentId = null;
    document.getElementById('delete-modal-message').textContent = 'Are you sure you want to delete this component?';
    document.getElementById('delete-modal-confirm').onclick = () => confirmDeleteComponent();
    openModal('delete-modal');
}

function openDeleteCompanyComponentModal(assignmentId, componentId, categoryId) {
    modalState.currentComponentAssignmentId = assignmentId;
    modalState.currentComponentId = componentId;
    modalState.currentComponentCategoryId = categoryId;
    document.getElementById('delete-modal-message').textContent = 'Are you sure you want to delete this company component?';
    document.getElementById('delete-modal-confirm').onclick = () => confirmDeleteComponent();
    openModal('delete-modal');
}

function openAddSupplierModal() {
    const nameInput = document.getElementById('supplier-modal-name');
    const addressInput = document.getElementById('supplier-modal-address');
    const phoneInput = document.getElementById('supplier-modal-phone');
    if (nameInput) nameInput.value = '';
    if (addressInput) addressInput.value = '';
    if (phoneInput) phoneInput.value = '';
    // Dim the component modal
    const componentModal = document.getElementById('component-modal');
    if (componentModal) componentModal.classList.add('dimmed');
    
    // Open supplier modal on top
    const overlay = document.getElementById('modal-overlay');
    const supplierModal = document.getElementById('supplier-modal');
    if (overlay) overlay.classList.add('active');
    if (supplierModal) supplierModal.classList.add('active');
}

function closeSupplierModal() {
    const supplierModal = document.getElementById('supplier-modal');
    const overlay = document.getElementById('modal-overlay');
    const componentModal = document.getElementById('component-modal');

    supplierModal?.classList.remove('active');
    // Keep overlay active if component modal is still open
    if (componentModal?.classList.contains('active')) {
        componentModal.classList.remove('dimmed');
    } else {
        overlay?.classList.remove('active');
    }
}


async function loadSupplierOptionsForModal() {
    const response = await globalThis.api_request('/api/suppliers', 'GET');
    modalSuppliersCache = sortByKey(response?.data || [], (supplier) => supplier?.name);

    const select = document.getElementById('modal-component-supplier-id');
    if (!select) {
        return;
    }

    const selectedSupplierId = select.value || '';
    select.innerHTML = `
        <option value="">Select company</option>
        ${modalSuppliersCache.map((supplier) => `<option value="${supplier.id}" ${String(selectedSupplierId) === String(supplier.id) ? 'selected' : ''}>${supplier.name}</option>`).join('')}
    `;
}
async function createSupplierFromComponentModal() {
    const nameInput = document.getElementById('supplier-modal-name');
    const addressInput = document.getElementById('supplier-modal-address');
    const phoneInput = document.getElementById('supplier-modal-phone');
    if (!nameInput) {
        return;
    }

    const name = nameInput.value.trim();
    const address = addressInput?.value?.trim() || null;
    const phone = phoneInput?.value?.trim() || null;
    if (!name) {
        globalThis.show_popup_temp('error', 'Validation Error', ['Company name is required']);
        return;
    }

    try {
        const response = await globalThis.api_request('/api/suppliers', 'POST', { name, address, phone });
        const supplierId = response?.data?.id || null;

        await loadSupplierOptionsForModal();

        if (supplierId) {
            const supplierSelect = document.getElementById('modal-component-supplier-id');
            if (supplierSelect) {
                supplierSelect.value = String(supplierId);
            }
        }

        closeSupplierModal();
        globalThis.show_popup_temp('success', 'Success', ['Company created']);
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to create company']);
    }
}

async function confirmDeleteCategory() {
    try {
        const categoryId = modalState.currentCategoryId;
        if (!categoryId) return;

        await globalThis.api_request(`/api/items/categories/${categoryId}`, 'DELETE');

        closeAllModals();
        await globalThis.loadCategories?.();
        await globalThis.loadCategoryOptions?.();
        globalThis.show_popup_temp('success', 'Success', ['Category deleted']);
    } catch (error) {
        console.error(error);
        closeAllModals();
        globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to delete category']);
    }
}

async function confirmDeleteComponent() {
    try {
        const assignmentId = modalState.currentComponentAssignmentId;
        const componentId = modalState.currentComponentId;
        const categoryId = modalState.currentComponentCategoryId;
        if (!componentId || !categoryId) return;

        if (assignmentId) {
            await globalThis.api_request(`/api/component-supplier/${assignmentId}`, 'DELETE');

            // Clean up component record if no company assignment remains
            const assignmentResponse = await globalThis.api_request(`/api/component-supplier/${componentId}`, 'GET');
            const remainingAssignments = assignmentResponse?.data || [];
            if (remainingAssignments.length === 0) {
                await globalThis.api_request(`/api/items/components/${componentId}`, 'DELETE');
            }
        } else {
            await globalThis.api_request(`/api/items/components/${componentId}`, 'DELETE');
        }

        modalState.currentComponentAssignmentId = null;
        closeAllModals();
        await globalThis.loadComponents?.(categoryId);
        globalThis.show_popup_temp('success', 'Success', [assignmentId ? 'Company component deleted' : 'Component deleted']);
    } catch (error) {
        console.error(error);
        closeAllModals();
        globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to delete component']);
    }
}

async function openAddComponentModal() {
    modalState.currentComponentId = null;
    modalState.currentComponentAssignmentId = null;
    document.getElementById('modal-component-category-id').value = '';
    document.getElementById('modal-component-name').value = '';
    document.getElementById('modal-component-code').value = '';
    document.getElementById('modal-component-price').value = '';
    document.getElementById('modal-component-unit').value = '';
    document.getElementById('modal-component-min-qty').value = '';
    document.getElementById('modal-component-max-qty').value = '';
    document.getElementById('modal-component-is-smart').checked = false;
    document.getElementById('modal-component-requires-license').checked = false;
    document.getElementById('modal-component-license-type').value = '';
    document.getElementById('modal-component-subscription-period').value = '';
    document.getElementById('modal-component-description').value = '';
    document.getElementById('component-modal-title').textContent = 'Add Component';
    document.getElementById('component-modal-submit').textContent = 'Add';
    document.getElementById('component-modal-step2-back').textContent = 'Back';

    // Load dropdown options into modal
    await loadCategoryOptionsForModal();
    await loadSupplierOptionsForModal();

    // Reset to step 1
    document.getElementById('modal-component-supplier-id').value = '';
    modalState.componentModalSelectedSupplierId = null;
    componentModalGoToStep1();

    openModal('component-modal');
}

async function loadCategoryOptionsForModal() {
    const select = document.getElementById('modal-component-category-id');
    if (!select) {
        return;
    }

    const response = await globalThis.api_request('/api/categories', 'GET');
    const categories = sortByKey(response?.data || [], (category) => category?.name);

    if (categories.length === 0) {
        select.innerHTML = '<option value="">No categories found</option>';
        return;
    }

    select.innerHTML = categories.map((category) => (
        `<option value="${category.id}">${category.name}</option>`
    )).join('');
}

async function openEditComponentModal(componentId, categoryId, preferredSupplierId = null) {
    try {
        const response = await globalThis.api_request(`/api/components/${categoryId}`, 'GET');
        const components = response?.data || [];
        const component = components.find((item) => item.id === componentId);

        if (!component) {
            globalThis.show_popup_temp('error', 'Error', ['Component not found']);
            return;
        }

        modalState.currentComponentId = componentId;
        modalState.currentComponentCategoryId = categoryId;
        
        // Load category/supplier options into modal
        await loadCategoryOptionsForModal();
        await loadSupplierOptionsForModal();

        let selectedAssignment = null;
        if (preferredSupplierId) {
            const assignmentResponse = await globalThis.api_request(`/api/component-supplier/${componentId}`, 'GET');
            const assignments = assignmentResponse?.data || [];
            selectedAssignment = assignments.find((assignment) => Number(assignment.supplier_id) === Number(preferredSupplierId)) || null;
            modalState.currentComponentAssignmentId = selectedAssignment?.id || null;
        }

        const source = selectedAssignment || component;

        document.getElementById('modal-component-category-id').value = source.category_id || component.category_id || '';
        document.getElementById('modal-component-name').value = source.component_name || component.component_name || '';
        document.getElementById('modal-component-code').value = source.component_code || component.component_code || '';
        document.getElementById('modal-component-price').value = source.price ?? source.base_price ?? component.base_price ?? '';
        document.getElementById('modal-component-unit').value = source.unit || component.unit || '';
        document.getElementById('modal-component-min-qty').value = source.min_quantity ?? component.min_quantity ?? '';
        document.getElementById('modal-component-max-qty').value = source.max_quantity ?? component.max_quantity ?? '';
        document.getElementById('modal-component-is-smart').checked = Boolean(source.is_smart_component ?? component.is_smart_component);
        document.getElementById('modal-component-requires-license').checked = Boolean(source.requires_license ?? component.requires_license);
        document.getElementById('modal-component-license-type').value = source.license_type || component.license_type || '';
        document.getElementById('modal-component-subscription-period').value = source.subscription_period || component.subscription_period || '';
        document.getElementById('modal-component-description').value = source.description || component.description || '';

        // For edit with preferred supplier (company-specific edit), pre-select that company
        if (preferredSupplierId) {
            document.getElementById('modal-component-supplier-id').value = String(preferredSupplierId);
            modalState.componentModalSelectedSupplierId = preferredSupplierId;
            componentModalGoToStep2();
        } else {
            // Otherwise start at step 1 (user selects company)
            document.getElementById('modal-component-supplier-id').value = '';
            modalState.componentModalSelectedSupplierId = null;
            componentModalGoToStep1();
        }


        document.getElementById('component-modal-title').textContent = 'Edit Component';
        document.getElementById('component-modal-submit').textContent = 'Update';
    document.getElementById('component-modal-step2-back').textContent = 'Cancel';
        openModal('component-modal');
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', ['Failed to load component']);
    }
}

function openEditCompanyComponentModal(componentId, categoryId, supplierId) {
    openEditComponentModal(componentId, categoryId, supplierId);
}

async function submitComponentModal() {
    try {
        const categoryId = Number(document.getElementById('modal-component-category-id').value || 0);
        const componentName = document.getElementById('modal-component-name').value.trim();
        const componentCode = document.getElementById('modal-component-code').value.trim();
        const basePrice = Number(document.getElementById('modal-component-price').value || 0);
        const unit = document.getElementById('modal-component-unit').value.trim() || 'unit';
        const minQuantity = Number(document.getElementById('modal-component-min-qty').value || 1);
        const maxQuantity = document.getElementById('modal-component-max-qty').value ? Number(document.getElementById('modal-component-max-qty').value) : null;
        const isSmartComponent = document.getElementById('modal-component-is-smart').checked;
        const requiresLicense = document.getElementById('modal-component-requires-license').checked;
        const licenseType = document.getElementById('modal-component-license-type').value.trim() || null;
        const subscriptionPeriod = document.getElementById('modal-component-subscription-period').value.trim() || null;
        const description = document.getElementById('modal-component-description').value.trim() || null;

        if (!categoryId || !componentName || !componentCode) {
            globalThis.show_popup_temp('error', 'Validation Error', ['Category, component name, and code are required']);
            return;
        }

        const supplierId = Number(modalState.componentModalSelectedSupplierId || 0);
        if (!supplierId) {
            globalThis.show_popup_temp('error', 'Validation Error', ['Company is required']);
            return;
        }

        if (basePrice < 0) {
            globalThis.show_popup_temp('error', 'Validation Error', ['Price must be 0 or greater']);
            return;
        }

        const payload = {
            category_id: categoryId,
            component_name: componentName,
            component_code: componentCode,
            base_price: basePrice,
            unit,
            min_quantity: minQuantity,
            max_quantity: maxQuantity,
            is_smart_component: isSmartComponent,
            requires_license: requiresLicense,
            license_type: licenseType,
            subscription_period: subscriptionPeriod,
            description,
            currency: 'RM',
        };

        let savedComponentId = null;
        const isCreatingNewComponent = !modalState.currentComponentId;

        if (modalState.currentComponentId) {
            // Update component details
            const updateResponse = await globalThis.api_request(`/api/items/components/${modalState.currentComponentId}`, 'PATCH', payload);
            savedComponentId = updateResponse?.data?.id || modalState.currentComponentId;
        } else {
            // Create new component
            const createResponse = await globalThis.api_request('/api/components', 'POST', payload);
            savedComponentId = createResponse?.data?.id || null;
        }

        // Create or update the supplier assignment for this one company
        if (savedComponentId) {
            await globalThis.api_request('/api/component-supplier', 'POST', {
                component_id: savedComponentId,
                supplier_id: supplierId,
                price: basePrice,
                category_id: categoryId,
                component_name: componentName,
                component_code: componentCode,
                description,
                unit,
                currency: 'RM',
                min_quantity: minQuantity,
                max_quantity: maxQuantity,
                is_smart_component: isSmartComponent,
                requires_license: requiresLicense,
                license_type: licenseType,
                subscription_period: subscriptionPeriod,
            });
        }

        globalThis.show_popup_temp('success', 'Success', [isCreatingNewComponent ? 'Component created' : 'Component updated']);

        // After success, show "Add Another" option or close modal
        closeAllModals();
        await globalThis.loadComponents?.(categoryId);
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to save component']);
    }
}

// Expose all functions globally
globalThis.closeAllModals = closeAllModals;
globalThis.openAddCategoryModal = openAddCategoryModal;
globalThis.openEditCategoryModal = openEditCategoryModal;
globalThis.openDeleteCategoryModal = openDeleteCategoryModal;
globalThis.openAddComponentModal = openAddComponentModal;
globalThis.openEditComponentModal = openEditComponentModal;
globalThis.openEditCompanyComponentModal = openEditCompanyComponentModal;
globalThis.openDeleteCompanyComponentModal = openDeleteCompanyComponentModal;
globalThis.openDeleteComponentModal = openDeleteComponentModal;
globalThis.submitCategoryModal = submitCategoryModal;
globalThis.submitComponentModal = submitComponentModal;
globalThis.confirmDeleteCategory = confirmDeleteCategory;
globalThis.confirmDeleteComponent = confirmDeleteComponent;
globalThis.openAddProjectModal = openAddProjectModal;
globalThis.openEditProjectModal = openEditProjectModal;
globalThis.openDeleteProjectModal = openDeleteProjectModal;
globalThis.openProjectManagerModal = openProjectManagerModal;
globalThis.submitProjectModal = submitProjectModal;
globalThis.confirmDeleteProject = confirmDeleteProject;
globalThis.openAddSupplierModal = openAddSupplierModal;
globalThis.closeSupplierModal = closeSupplierModal;
globalThis.createSupplierFromComponentModal = createSupplierFromComponentModal;
globalThis.componentModalGoToStep1 = componentModalGoToStep1;
globalThis.componentModalGoToStep2 = componentModalGoToStep2;
globalThis.handleComponentStep2SecondaryAction = handleComponentStep2SecondaryAction;
