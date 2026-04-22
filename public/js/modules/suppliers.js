function formatAmount(value) {
    const amount = Number(value || 0);
    if (!Number.isFinite(amount)) {
        return '0';
    }

    return amount.toLocaleString('en-MY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

let cachedSuppliers = [];
let cachedCategories = [];
let currentSelectedSupplierId = null;
let supplierFormMode = 'create';
let supplierFormId = null;
let supplierItemFormMode = 'create';
let supplierItemEditing = null;
let supplierCategoryMiniMode = 'create';
let supplierCategoryMiniEditId = null;
let cachedSupplierItemComponents = [];
let currentSupplierItems = [];
let currentItemsPage = 1;
const ITEMS_PER_PAGE = 6;
let selectedSupplierCategoryFilter = '';

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

function getElement(id) {
    return document.getElementById(id);
}

function normalizeFilterValue(value) {
    return String(value || '').trim().toLowerCase();
}

function getItemCategory(item) {
    const categoryId = Number(item?.category_id || item?.component?.category_id || 0) || null;
    const category = cachedCategories.find((row) => Number(row.id) === categoryId) || null;

    return {
        id: categoryId,
        name: category?.name || 'Uncategorized',
    };
}

function getCategoryGroupKey(item) {
    const category = getItemCategory(item);
    return normalizeFilterValue(category.name || 'uncategorized') || 'uncategorized';
}

function sortSupplierItemsByCategory(items) {
    return [...items].sort((left, right) => {
        const leftCategory = normalizeFilterValue(getItemCategory(left).name || 'uncategorized');
        const rightCategory = normalizeFilterValue(getItemCategory(right).name || 'uncategorized');

        if (leftCategory !== rightCategory) {
            return leftCategory.localeCompare(rightCategory, undefined, {
                sensitivity: 'base',
                numeric: true,
            });
        }

        const leftName = normalizeFilterValue(left?.component_name || left?.component?.component_name || '');
        const rightName = normalizeFilterValue(right?.component_name || right?.component?.component_name || '');

        return leftName.localeCompare(rightName, undefined, {
            sensitivity: 'base',
            numeric: true,
        });
    });
}

function ensureVisibleSelectedCategoryOption() {
    const select = getElement('supplier-item-filter-category');
    const selectedCategoryId = String(selectedSupplierCategoryFilter || '');

    if (!select) {
        return;
    }

    if (!selectedCategoryId) {
        select.value = '';
        return;
    }

    const hasOption = Array.from(select.options).some((option) => option.value === selectedCategoryId);
    if (!hasOption) {
        const selectedCategory = cachedCategories.find((category) => String(category.id) === selectedCategoryId);
        const selectedOption = document.createElement('option');
        selectedOption.value = selectedCategoryId;
        selectedOption.textContent = selectedCategory?.name || 'Selected Category';
        select.appendChild(selectedOption);
    }

    select.value = selectedCategoryId;
}

function getSelectedCategoryName() {
    const selectedCategoryId = String(selectedSupplierCategoryFilter || '');
    if (!selectedCategoryId) {
        return 'All Categories';
    }

    const category = cachedCategories.find((row) => String(row.id) === selectedCategoryId);
    if (category?.name) {
        return category.name;
    }

    const select = getElement('supplier-item-filter-category');
    const option = select?.querySelector(`option[value="${selectedCategoryId}"]`);
    return option?.textContent || 'Selected Category';
}

function updateActiveCategoryLabel() {
    const label = getElement('supplier-active-category');
    if (!label) {
        return;
    }

    label.textContent = `Category: ${getSelectedCategoryName()}`;
}

function updateSupplierItemCategoryFilter(items) {
    const select = getElement('supplier-item-filter-category');
    if (!select) {
        return;
    }

    const selectedValue = String(select.value || '');
    const categories = [];
    const categoryIds = new Set();

    items.forEach((item) => {
        const category = getItemCategory(item);
        const key = String(category.id || 'uncategorized');
        if (categoryIds.has(key)) {
            return;
        }

        categoryIds.add(key);
        categories.push(category);
    });

    const sortedCategories = sortByKey(categories, (category) => category.name);
    select.innerHTML = '<option value="">All Categories</option>';
    sortedCategories.forEach((category) => {
        const option = document.createElement('option');
        option.value = String(category.id || 'uncategorized');
        option.textContent = category.name;
        select.appendChild(option);
    });

    const preferredValue = selectedSupplierCategoryFilter || selectedValue;
    selectedSupplierCategoryFilter = preferredValue || '';
    ensureVisibleSelectedCategoryOption();

    updateActiveCategoryLabel();
}

function renderSupplierCategoryList() {
    const list = getElement('supplier-category-list');
    if (!list) {
        return;
    }

    list.innerHTML = '';

    if (cachedCategories.length === 0) {
        list.innerHTML = '<li class="pd-10 clr-grey1">No categories found.</li>';
        return;
    }

    const sortedCategories = sortByKey(cachedCategories, (category) => category?.name);

    sortedCategories.forEach((category) => {
        const li = document.createElement('li');
        const categoryKey = String(category.id || 'uncategorized');
        const isSelected = selectedSupplierCategoryFilter === categoryKey;

        li.className = `pd-10 bdr-bottom-22 d-flex ai-center jc-between cursor-pointer ${isSelected ? 'app-selected-item br-10' : ''}`;
        li.innerHTML = `
            <div>
                <div class="fw-bold">${category.icon || '📦'} ${category.name || 'Unnamed Category'}</div>
                <div class="fs-12 clr-grey1">${category.description || 'No description'}</div>
            </div>
            <div class="d-flex gap-5">
                <button type="button" class="btn-icon" title="Edit Category" data-edit-category-id="${category.id}"><i class="ri-edit-box-line"></i></button>
                <button type="button" class="btn-icon" title="Delete Category" data-delete-category-id="${category.id}"><i class="ri-delete-bin-5-line"></i></button>
            </div>
        `;

        li.addEventListener('click', (event) => {
            if (event.target.closest('[data-edit-category-id], [data-delete-category-id]')) {
                return;
            }

            selectedSupplierCategoryFilter = selectedSupplierCategoryFilter === categoryKey ? '' : categoryKey;
            ensureVisibleSelectedCategoryOption();

            currentItemsPage = 1;
            renderSupplierCategoryList();
            renderSupplierItems(currentSelectedSupplierId);
        });

        const editBtn = li.querySelector('[data-edit-category-id]');
        editBtn?.addEventListener('click', (event) => {
            event.stopPropagation();
            openSupplierCategoryMiniModal('edit', category);
        });

        const deleteBtn = li.querySelector('[data-delete-category-id]');
        deleteBtn?.addEventListener('click', async (event) => {
            event.stopPropagation();
            const confirmed = globalThis.confirm('Delete this category?');
            if (!confirmed) {
                return;
            }

            try {
                await globalThis.api_request(`/api/items/categories/${category.id}`, 'DELETE');
                const response = await globalThis.api_request('/api/categories', 'GET');
                cachedCategories = sortByKey(response?.data || [], (row) => row?.name);
                if (selectedSupplierCategoryFilter === categoryKey) {
                    selectedSupplierCategoryFilter = '';
                    ensureVisibleSelectedCategoryOption();
                }

                updateSupplierItemCategoryFilter(currentSupplierItems);
                renderSupplierCategoryList();
                renderSupplierItems(currentSelectedSupplierId);
                globalThis.show_popup_temp('success', 'Success', ['Category deleted']);
            } catch (error) {
                console.error(error);
                globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to delete category']);
            }
        });

        list.appendChild(li);
    });
}

function ensureSuppliersPage() {
    return Boolean(getElement('supplier-list') && getElement('supplier-items-container'));
}

function openSupplierFormModal(mode, supplier = null) {
    supplierFormMode = mode;
    supplierFormId = supplier?.id ?? null;

    const overlay = getElement('supplier-form-overlay');
    const modal = getElement('supplier-form-modal');
    const title = getElement('supplier-form-title');
    const submit = getElement('supplier-form-submit');
    const nameInput = getElement('supplier-form-name');
    const phoneInput = getElement('supplier-form-phone');
    const addressInput = getElement('supplier-form-address');

    if (!overlay || !modal || !title || !submit || !nameInput || !phoneInput || !addressInput) {
        return;
    }

    if (mode === 'edit' && supplier) {
        title.textContent = 'Edit Company';
        submit.textContent = 'Update';
        nameInput.value = supplier.name || '';
        phoneInput.value = supplier.phone || '';
        addressInput.value = supplier.address || '';
    } else {
        title.textContent = 'Add Company';
        submit.textContent = 'Save';
        nameInput.value = '';
        phoneInput.value = '';
        addressInput.value = '';
    }

    setSupplierItemModalDimmed(false);
    overlay.classList.add('active');
    modal.classList.add('active');
}

function closeSupplierFormModal() {
    getElement('supplier-form-overlay')?.classList.remove('active');
    getElement('supplier-form-modal')?.classList.remove('active');
}

function closeSupplierItemFormModal() {
    getElement('supplier-item-form-overlay')?.classList.remove('active');
    getElement('supplier-item-form-modal')?.classList.remove('active');
    getElement('supplier-item-form-modal')?.classList.remove('dimmed');
}

function setSupplierItemModalDimmed(isDimmed) {
    const modal = getElement('supplier-item-form-modal');
    if (!modal) {
        return;
    }

    if (isDimmed) {
        modal.classList.add('dimmed');
        return;
    }

    modal.classList.remove('dimmed');
}

function openSupplierCategoryMiniModal(mode = 'create', category = null) {
    const overlay = getElement('supplier-category-mini-overlay');
    const modal = getElement('supplier-category-mini-modal');
    const title = getElement('supplier-category-mini-title');
    const submit = getElement('supplier-category-mini-submit');
    const nameInput = getElement('supplier-category-mini-name');
    const descriptionInput = getElement('supplier-category-mini-description');
    const iconInput = getElement('supplier-category-mini-icon');

    if (!overlay || !modal || !title || !submit || !nameInput || !descriptionInput || !iconInput) {
        return;
    }

    supplierCategoryMiniMode = mode;
    supplierCategoryMiniEditId = category?.id ?? null;

    if (mode === 'edit' && category) {
        title.textContent = 'Edit Category';
        submit.textContent = 'Update Category';
        nameInput.value = category.name || '';
        descriptionInput.value = category.description || '';
        iconInput.value = category.icon || '📦';
    } else {
        title.textContent = 'Add New Category';
        submit.textContent = 'Save Category';
        nameInput.value = '';
        descriptionInput.value = '';
        iconInput.value = '';
    }

    overlay.classList.add('active');
    modal.classList.add('active');
    setSupplierItemModalDimmed(true);
    nameInput.focus();
}

function closeSupplierCategoryMiniModal() {
    getElement('supplier-category-mini-overlay')?.classList.remove('active');
    getElement('supplier-category-mini-modal')?.classList.remove('active');
    setSupplierItemModalDimmed(false);
}

function setSupplierItemEditControlsVisible(isVisible) {
    const categoryEditButton = getElement('supplier-item-edit-category-btn');
    const componentEditButton = getElement('supplier-item-edit-component-btn');

    if (categoryEditButton) {
        categoryEditButton.style.display = isVisible ? 'inline-flex' : 'none';
    }

    if (componentEditButton) {
        componentEditButton.style.display = isVisible ? 'inline-flex' : 'none';
    }
}

function applyComponentDetailsToSupplierItemForm(component) {
    const form = getSupplierItemFormElements();
    if (!component || !form.codeInput || !form.nameInput || !form.descriptionInput || !form.unitInput || !form.currencyInput || !form.minQtyInput || !form.maxQtyInput || !form.licenseTypeInput || !form.subscriptionInput || !form.isSmartInput || !form.requiresLicenseInput) {
        return;
    }

    form.codeInput.value = component.component_code || '';
    form.nameInput.value = component.component_name || '';
    form.descriptionInput.value = component.description || '';
    form.unitInput.value = component.unit || '';
    form.currencyInput.value = component.currency || 'RM';
    form.minQtyInput.value = component.min_quantity == null ? '' : String(component.min_quantity);
    form.maxQtyInput.value = component.max_quantity == null ? '' : String(component.max_quantity);
    form.licenseTypeInput.value = component.license_type || '';
    form.subscriptionInput.value = component.subscription_period || '';
    form.isSmartInput.checked = Boolean(component.is_smart_component);
    form.requiresLicenseInput.checked = Boolean(component.requires_license);
}

function getSelectedSupplierItemCategory() {
    const categoryId = Number(getElement('supplier-item-category-id')?.value || 0);
    return cachedCategories.find((category) => Number(category.id) === categoryId) || null;
}

function getSelectedSupplierItemComponent() {
    const componentId = Number(getElement('supplier-item-component-id')?.value || 0);
    return cachedSupplierItemComponents.find((component) => Number(component.id) === componentId) || null;
}

function getSupplierItemFormElements() {
    return {
        categorySelect: getElement('supplier-item-category-id'),
        componentSelect: getElement('supplier-item-component-id'),
        priceInput: getElement('supplier-item-price'),
        currencyInput: getElement('supplier-item-currency'),
        codeInput: getElement('supplier-item-code'),
        nameInput: getElement('supplier-item-name'),
        descriptionInput: getElement('supplier-item-description'),
        unitInput: getElement('supplier-item-unit'),
        minQtyInput: getElement('supplier-item-min-qty'),
        maxQtyInput: getElement('supplier-item-max-qty'),
        licenseTypeInput: getElement('supplier-item-license-type'),
        subscriptionInput: getElement('supplier-item-subscription'),
        isSmartInput: getElement('supplier-item-is-smart'),
        requiresLicenseInput: getElement('supplier-item-requires-license'),
    };
}

function getPreferredItemValue(item, assignmentKey, componentKey, fallback = '') {
    if (item?.[assignmentKey] !== null && item?.[assignmentKey] !== undefined && item?.[assignmentKey] !== '') {
        return item[assignmentKey];
    }

    if (item?.component?.[componentKey] !== null && item?.component?.[componentKey] !== undefined && item?.component?.[componentKey] !== '') {
        return item.component[componentKey];
    }

    return fallback;
}

function parseSupplierItemQuantities(form) {
    const minQtyRaw = form.minQtyInput.value;
    const maxQtyRaw = form.maxQtyInput.value;
    const minQty = minQtyRaw ? Number(minQtyRaw) : null;
    const maxQty = maxQtyRaw ? Number(maxQtyRaw) : null;

    const invalidQty = (value) => value !== null && (!Number.isInteger(value) || value < 1);
    if (invalidQty(minQty) || invalidQty(maxQty)) {
        return {
            ok: false,
            message: 'Min Qty and Max Qty must be whole numbers greater than 0',
        };
    }

    if (minQty !== null && maxQty !== null && maxQty < minQty) {
        return {
            ok: false,
            message: 'Max Qty must be greater than or equal to Min Qty',
        };
    }

    return {
        ok: true,
        minQty,
        maxQty,
    };
}

function resolveSupplierItemTarget(form) {
    if (supplierItemFormMode === 'edit' && supplierItemEditing) {
        return {
            componentId: Number(supplierItemEditing.component_id || supplierItemEditing.component?.id || 0),
            categoryId: supplierItemEditing.category_id || supplierItemEditing.component?.category_id || null,
        };
    }

    return {
        componentId: Number(form.componentSelect.value || 0),
        categoryId: Number(form.categorySelect.value || 0) || null,
    };
}

function buildSupplierItemPayload(form, target, price, quantities) {
    return {
        component_id: target.componentId,
        supplier_id: currentSelectedSupplierId,
        price,
        category_id: target.categoryId,
        component_code: form.codeInput.value.trim() || null,
        component_name: form.nameInput.value.trim() || null,
        description: form.descriptionInput.value.trim() || null,
        unit: form.unitInput.value.trim() || null,
        currency: form.currencyInput.value.trim() || 'RM',
        min_quantity: quantities.minQty,
        max_quantity: quantities.maxQty,
        is_smart_component: Boolean(form.isSmartInput.checked),
        requires_license: Boolean(form.requiresLicenseInput.checked),
        license_type: form.licenseTypeInput.value.trim() || null,
        subscription_period: form.subscriptionInput.value.trim() || null,
    };
}

async function loadSupplierItemCategoryOptions(preferredCategoryId = null, forceReload = false) {
    const categorySelect = getElement('supplier-item-category-id');
    if (!categorySelect) {
        return;
    }

    if (forceReload || cachedCategories.length === 0) {
        const response = await globalThis.api_request('/api/categories', 'GET');
        cachedCategories = sortByKey(response?.data || [], (category) => category?.name);
    }

    if (cachedCategories.length === 0) {
        categorySelect.innerHTML = '<option value="">No categories found</option>';
        return;
    }

    categorySelect.innerHTML = cachedCategories.map((category) => (
        `<option value="${category.id}">${category.name}</option>`
    )).join('');

    if (preferredCategoryId) {
        categorySelect.value = String(preferredCategoryId);
    }
}

async function loadSupplierItemComponentsByCategory(categoryId, preferredComponentId = null) {
    const componentSelect = getElement('supplier-item-component-id');
    if (!componentSelect) {
        return;
    }

    if (!categoryId) {
        componentSelect.innerHTML = '<option value="">No items found</option>';
        return;
    }

    const response = await globalThis.api_request(`/api/components/${categoryId}`, 'GET');
    const components = sortByKey(response?.data || [], (component) => component?.component_name);
    cachedSupplierItemComponents = components;

    if (components.length === 0) {
        componentSelect.innerHTML = '<option value="">No items found</option>';
        return;
    }

    componentSelect.innerHTML = components.map((component) => (
        `<option value="${component.id}">${component.component_name} (${component.component_code || '-'})</option>`
    )).join('');

    if (preferredComponentId) {
        componentSelect.value = String(preferredComponentId);
    }

    applyComponentDetailsToSupplierItemForm(getSelectedSupplierItemComponent());
}

async function createCategoryFromSupplierItemForm() {
    const nameInput = getElement('supplier-category-mini-name');
    const descriptionInput = getElement('supplier-category-mini-description');
    const iconInput = getElement('supplier-category-mini-icon');

    if (!nameInput || !descriptionInput || !iconInput) {
        return;
    }

    const trimmedName = nameInput.value.trim();
    if (!trimmedName) {
        globalThis.show_popup_temp('error', 'Validation Error', ['Category name is required']);
        return;
    }

    const descriptionValue = descriptionInput.value.trim();
    const iconValue = iconInput.value.trim() || '📦';

    try {
        const payload = {
            name: trimmedName,
            description: descriptionValue || null,
            icon: iconValue,
        };

        const response = supplierCategoryMiniMode === 'edit' && supplierCategoryMiniEditId
            ? await globalThis.api_request(`/api/items/categories/${supplierCategoryMiniEditId}`, 'PATCH', payload)
            : await globalThis.api_request('/api/categories', 'POST', payload);

        const newCategoryId = response?.data?.id || null;
        await loadSupplierItemCategoryOptions(newCategoryId, true);
        await loadSupplierItemComponentsByCategory(Number(newCategoryId || 0));
        renderSupplierCategoryList();
        closeSupplierCategoryMiniModal();
        globalThis.show_popup_temp('success', 'Success', [supplierCategoryMiniMode === 'edit' ? 'Category updated' : 'Category created']);
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to save category']);
    }
}

async function createComponentFromSupplierItemForm() {
    const form = getSupplierItemFormElements();
    if (!form.categorySelect || !form.codeInput || !form.nameInput || !form.descriptionInput || !form.unitInput || !form.currencyInput || !form.minQtyInput || !form.maxQtyInput || !form.licenseTypeInput || !form.subscriptionInput || !form.isSmartInput || !form.requiresLicenseInput) {
        return null;
    }

    const categoryId = Number(form.categorySelect.value || 0);
    if (!categoryId) {
        globalThis.show_popup_temp('error', 'Validation Error', ['Please select or create a category first']);
        return null;
    }

    const componentCode = form.codeInput.value.trim();
    const componentName = form.nameInput.value.trim();

    if (!componentCode || !componentName) {
        globalThis.show_popup_temp('error', 'Validation Error', ['SKU and item name are required to create a new item']);
        return null;
    }

    const qtyResult = parseSupplierItemQuantities(form);
    if (!qtyResult.ok) {
        globalThis.show_popup_temp('error', 'Validation Error', [qtyResult.message]);
        return null;
    }

    try {
        const response = await globalThis.api_request('/api/components', 'POST', {
            category_id: categoryId,
            component_code: componentCode,
            component_name: componentName,
            description: form.descriptionInput.value.trim() || null,
            unit: form.unitInput.value.trim() || null,
            currency: form.currencyInput.value.trim() || 'RM',
            min_quantity: qtyResult.minQty,
            max_quantity: qtyResult.maxQty,
            is_smart_component: Boolean(form.isSmartInput.checked),
            requires_license: Boolean(form.requiresLicenseInput.checked),
            license_type: form.licenseTypeInput.value.trim() || null,
            subscription_period: form.subscriptionInput.value.trim() || null,
        });

        const newComponent = response?.data || null;
        const newComponentId = Number(newComponent?.id || 0);
        await loadSupplierItemComponentsByCategory(categoryId, newComponentId || null);
        globalThis.show_popup_temp('success', 'Success', ['Item created']);
        return newComponent;
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to create item']);
        return null;
    }
}

async function editComponentFromSupplierItemForm() {
    const selectedComponent = getSelectedSupplierItemComponent();
    if (!selectedComponent) {
        globalThis.show_popup_temp('error', 'Validation Error', ['Please select an item to edit']);
        return;
    }

    const form = getSupplierItemFormElements();
    if (!form.codeInput || !form.nameInput || !form.descriptionInput || !form.unitInput || !form.currencyInput || !form.minQtyInput || !form.maxQtyInput || !form.licenseTypeInput || !form.subscriptionInput || !form.isSmartInput || !form.requiresLicenseInput) {
        return;
    }

    const componentCode = form.codeInput.value.trim();
    const componentName = form.nameInput.value.trim();
    if (!componentCode || !componentName) {
        globalThis.show_popup_temp('error', 'Validation Error', ['SKU and item name are required']);
        return;
    }

    const qtyResult = parseSupplierItemQuantities(form);
    if (!qtyResult.ok) {
        globalThis.show_popup_temp('error', 'Validation Error', [qtyResult.message]);
        return;
    }

    try {
        await globalThis.api_request(`/api/items/components/${selectedComponent.id}`, 'PATCH', {
            component_code: componentCode,
            component_name: componentName,
            description: form.descriptionInput.value.trim() || null,
            unit: form.unitInput.value.trim() || null,
            currency: form.currencyInput.value.trim() || 'RM',
            min_quantity: qtyResult.minQty,
            max_quantity: qtyResult.maxQty,
            is_smart_component: Boolean(form.isSmartInput.checked),
            requires_license: Boolean(form.requiresLicenseInput.checked),
            license_type: form.licenseTypeInput.value.trim() || null,
            subscription_period: form.subscriptionInput.value.trim() || null,
        });

        const categoryId = Number(getElement('supplier-item-category-id')?.value || 0);
        await loadSupplierItemComponentsByCategory(categoryId, selectedComponent.id);
        globalThis.show_popup_temp('success', 'Success', ['Item updated']);
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to update item']);
    }
}

async function ensureSupplierItemComponentSelected(form, target) {
    if (supplierItemFormMode === 'edit' || target.componentId) {
        return target.componentId;
    }

    const createdComponent = await createComponentFromSupplierItemForm();
    return Number(createdComponent?.id || 0);
}

async function openSupplierItemFormModal(mode, item = null) {
    if (!currentSelectedSupplierId) {
        globalThis.show_popup_temp('error', 'Validation Error', ['Please select a company first']);
        return;
    }

    supplierItemFormMode = mode;
    supplierItemEditing = item;

    const overlay = getElement('supplier-item-form-overlay');
    const modal = getElement('supplier-item-form-modal');
    const title = getElement('supplier-item-form-title');
    const submit = getElement('supplier-item-form-submit');
    const readonlyInfo = getElement('supplier-item-readonly');
    const categoryGroup = getElement('supplier-item-category-group');
    const componentGroup = getElement('supplier-item-component-group');
    const form = getSupplierItemFormElements();

    if (!overlay || !modal || !title || !submit || !readonlyInfo || !categoryGroup || !componentGroup) {
        return;
    }

    if (!form.categorySelect || !form.componentSelect || !form.priceInput || !form.currencyInput || !form.codeInput || !form.nameInput || !form.descriptionInput || !form.unitInput || !form.minQtyInput || !form.maxQtyInput || !form.licenseTypeInput || !form.subscriptionInput || !form.isSmartInput || !form.requiresLicenseInput) {
        return;
    }

    if (mode === 'edit' && item) {
        setSupplierItemEditControlsVisible(true);
        title.textContent = 'Edit Item';
        submit.textContent = 'Update';

        const preferredCategoryId = Number(item.category_id || item.component?.category_id || 0);
        const preferredComponentId = Number(item.component_id || item.component?.id || 0);

        categoryGroup.style.display = 'block';
        componentGroup.style.display = 'block';
        readonlyInfo.style.display = 'none';
        readonlyInfo.innerHTML = '';

        await loadSupplierItemCategoryOptions(preferredCategoryId || null, true);
        await loadSupplierItemComponentsByCategory(preferredCategoryId, preferredComponentId || null);

        form.priceInput.value = String(item.price ?? 0);
        form.currencyInput.value = String(getPreferredItemValue(item, 'currency', 'currency', 'RM'));
        form.codeInput.value = String(getPreferredItemValue(item, 'component_code', 'component_code', ''));
        form.nameInput.value = String(getPreferredItemValue(item, 'component_name', 'component_name', ''));
        form.descriptionInput.value = String(getPreferredItemValue(item, 'description', 'description', ''));
        form.unitInput.value = String(getPreferredItemValue(item, 'unit', 'unit', ''));
        form.minQtyInput.value = String(getPreferredItemValue(item, 'min_quantity', 'min_quantity', ''));
        form.maxQtyInput.value = String(getPreferredItemValue(item, 'max_quantity', 'max_quantity', ''));
        form.licenseTypeInput.value = String(getPreferredItemValue(item, 'license_type', 'license_type', ''));
        form.subscriptionInput.value = String(getPreferredItemValue(item, 'subscription_period', 'subscription_period', ''));
        form.isSmartInput.checked = Boolean(getPreferredItemValue(item, 'is_smart_component', 'is_smart_component', false));
        form.requiresLicenseInput.checked = Boolean(getPreferredItemValue(item, 'requires_license', 'requires_license', false));
    } else {
        setSupplierItemEditControlsVisible(false);
        title.textContent = 'Add Item';
        submit.textContent = 'Save';
        categoryGroup.style.display = 'block';
        componentGroup.style.display = 'block';
        readonlyInfo.style.display = 'none';
        readonlyInfo.innerHTML = '';

        await loadSupplierItemCategoryOptions();
        await loadSupplierItemComponentsByCategory(Number(form.categorySelect.value || 0));
        form.priceInput.value = '';
        form.currencyInput.value = 'RM';
        form.codeInput.value = '';
        form.nameInput.value = '';
        form.descriptionInput.value = '';
        form.unitInput.value = '';
        form.minQtyInput.value = '';
        form.maxQtyInput.value = '';
        form.licenseTypeInput.value = '';
        form.subscriptionInput.value = '';
        form.isSmartInput.checked = false;
        form.requiresLicenseInput.checked = false;
    }

    overlay.classList.add('active');
    modal.classList.add('active');
}

async function submitSupplierItemForm() {
    if (!currentSelectedSupplierId) {
        globalThis.show_popup_temp('error', 'Validation Error', ['Please select a company first']);
        return;
    }

    const form = getSupplierItemFormElements();

    if (!form.categorySelect || !form.componentSelect || !form.priceInput || !form.currencyInput || !form.codeInput || !form.nameInput || !form.descriptionInput || !form.unitInput || !form.minQtyInput || !form.maxQtyInput || !form.licenseTypeInput || !form.subscriptionInput || !form.isSmartInput || !form.requiresLicenseInput) {
        return;
    }

    const price = Number(form.priceInput.value || 0);
    if (!Number.isFinite(price) || price < 0) {
        globalThis.show_popup_temp('error', 'Validation Error', ['Price must be 0 or greater']);
        return;
    }

    const qtyResult = parseSupplierItemQuantities(form);
    if (!qtyResult.ok) {
        globalThis.show_popup_temp('error', 'Validation Error', [qtyResult.message]);
        return;
    }

    const target = resolveSupplierItemTarget(form);
    if (supplierItemFormMode !== 'edit' && (!target.categoryId || !target.componentId)) {
        if (!target.categoryId) {
            globalThis.show_popup_temp('error', 'Validation Error', ['Category is required']);
            return;
        }
    }

    const ensuredComponentId = await ensureSupplierItemComponentSelected(form, target);
    if (!ensuredComponentId) {
        globalThis.show_popup_temp('error', 'Validation Error', ['Item is required']);
        return;
    }

    const payloadTarget = {
        ...target,
        componentId: ensuredComponentId,
    };

    try {
        const payload = buildSupplierItemPayload(form, payloadTarget, price, qtyResult);
        await globalThis.api_request('/api/component-supplier', 'POST', payload);

        closeSupplierItemFormModal();
        await loadSuppliers();
        await loadSupplierItems(currentSelectedSupplierId);
        globalThis.show_popup_temp('success', 'Success', [supplierItemFormMode === 'edit' ? 'Item updated' : 'Item added']);
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to save item']);
    }
}

async function submitSupplierForm() {
    const nameInput = getElement('supplier-form-name');
    const phoneInput = getElement('supplier-form-phone');
    const addressInput = getElement('supplier-form-address');

    if (!nameInput || !phoneInput || !addressInput) {
        return;
    }

    const name = nameInput.value.trim();
    const phone = phoneInput.value.trim();
    const address = addressInput.value.trim();

    if (!name) {
        globalThis.show_popup_temp('error', 'Validation Error', ['Supplier name is required']);
        return;
    }

    const payload = {
        name,
        phone: phone || null,
        address: address || null,
    };

    try {
        if (supplierFormMode === 'edit' && supplierFormId) {
            await globalThis.api_request(`/api/suppliers/${supplierFormId}`, 'PATCH', payload);
            globalThis.show_popup_temp('success', 'Success', ['Supplier updated']);
        } else {
            await globalThis.api_request('/api/suppliers', 'POST', payload);
            globalThis.show_popup_temp('success', 'Success', ['Supplier created']);
        }

        closeSupplierFormModal();
        await loadSuppliers();
        if (currentSelectedSupplierId) {
            await loadSupplierItems(currentSelectedSupplierId);
        }
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to save supplier']);
    }
}

function bindSupplierFormModal() {
    getElement('supplier-form-overlay')?.addEventListener('click', closeSupplierFormModal);
    getElement('supplier-form-close')?.addEventListener('click', closeSupplierFormModal);
    getElement('supplier-form-cancel')?.addEventListener('click', closeSupplierFormModal);
    getElement('supplier-form-submit')?.addEventListener('click', submitSupplierForm);
}

function renderSupplierList(suppliers) {
    const list = getElement('supplier-list');
    if (!list) {
        return;
    }

    list.innerHTML = '';

    if (suppliers.length === 0) {
        list.innerHTML = '<li class="pd-10 clr-grey1">No suppliers found.</li>';
        return;
    }

    suppliers.forEach((supplier) => {
        const li = document.createElement('li');
        li.dataset.supplierId = supplier.id;
        const isSelected = currentSelectedSupplierId === supplier.id;
        li.className = `pd-10 cursor-pointer bdr-bottom-22 d-flex ai-center jc-between ${isSelected ? 'app-selected-item bg-blue clr-white br-10' : 'hover-bg'}`;
        
        li.innerHTML = `
            <div class="d-flex ai-center gap-10" style="flex: 1;">
                <i class="ri-building-2-line fs-20"></i>
                <div>
                    <div class="fw-bold">${supplier.name}</div>
                    <div class="fs-12 ${isSelected ? 'clr-white' : 'clr-grey1'}">${supplier.phone || '-'} | ${supplier.address || '-'}</div>
                    <div class="fs-12 ${isSelected ? 'clr-white' : 'clr-grey1'}">${supplier.component_suppliers?.length || 0} item(s)</div>
                </div>
            </div>
            <div class="d-flex gap-5">
                <button type="button" class="btn-icon" title="Edit Supplier" data-edit-id="${supplier.id}"><i class="ri-edit-box-line"></i></button>
                <button type="button" class="btn-icon" title="Delete Supplier" data-delete-id="${supplier.id}"><i class="ri-delete-bin-5-line"></i></button>
            </div>
        `;

        // Click on supplier name to select it
        const nameDiv = li.querySelector('div[style*="flex: 1"]');
        nameDiv?.addEventListener('click', async (e) => {
            e.stopPropagation();
            currentSelectedSupplierId = supplier.id;
            await loadSuppliers();
            await loadSupplierItems(supplier.id);
        });

        const editBtn = li.querySelector('[data-edit-id]');
        editBtn?.addEventListener('click', async (e) => {
            e.stopPropagation();
            openSupplierFormModal('edit', supplier);
        });

        const deleteBtn = li.querySelector('[data-delete-id]');
        deleteBtn?.addEventListener('click', async (e) => {
            e.stopPropagation();
            const confirmed = globalThis.confirm('Delete this supplier?');
            if (!confirmed) {
                return;
            }

            try {
                await globalThis.api_request(`/api/suppliers/${supplier.id}`, 'DELETE');
                if (currentSelectedSupplierId === supplier.id) {
                    currentSelectedSupplierId = null;
                }
                await loadSuppliers();
                if (currentSelectedSupplierId === null) {
                    loadSupplierItems(null);
                }
                globalThis.show_popup_temp('success', 'Success', ['Supplier deleted']);
            } catch (error) {
                console.error(error);
                globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to delete supplier']);
            }
        });

        list.appendChild(li);
    });
}

async function loadSuppliers() {
    const response = await globalThis.api_request('/api/suppliers', 'GET');
    const suppliers = sortByKey(response?.data || [], (supplier) => supplier?.name);
    cachedSuppliers = suppliers;

    // Auto-select the first supplier on initial load or recover if selection is invalid.
    if (suppliers.length > 0) {
        const hasCurrentSelection = suppliers.some((supplier) => supplier.id === currentSelectedSupplierId);
        if (!currentSelectedSupplierId || !hasCurrentSelection) {
            currentSelectedSupplierId = suppliers[0].id;
        }
    } else {
        currentSelectedSupplierId = null;
    }

    renderSupplierList(suppliers);
    renderSupplierOptions();
}

async function loadSupplierItems(supplierId) {
    const container = getElement('supplier-items-container');
    const description = getElement('supplier-items-description');
    
    if (!container) {
        return;
    }

    if (!supplierId) {
        if (description) description.textContent = 'Select a company to view all assigned items.';
        updateSupplierItemCategoryFilter([]);
        currentSupplierItems = [];
        currentItemsPage = 1;
        const emptyMsg = document.createElement('div');
        emptyMsg.className = 'pd-10 clr-grey1 br-5';
        emptyMsg.textContent = 'Select a supplier from the list to view available items.';
        container.innerHTML = '';
        container.appendChild(emptyMsg);
        renderSupplierItemsPagination(0, 0);
        return;
    }

    const supplier = cachedSuppliers.find(s => s.id === supplierId);
    if (!supplier) {
        if (description) description.textContent = 'Supplier not found';
        updateSupplierItemCategoryFilter([]);
        currentSupplierItems = [];
        currentItemsPage = 1;
        const emptyMsg = document.createElement('div');
        emptyMsg.className = 'pd-10 clr-grey1 br-5';
        emptyMsg.textContent = 'Supplier not found.';
        container.innerHTML = '';
        container.appendChild(emptyMsg);
        renderSupplierItemsPagination(0, 0);
        return;
    }

    if (description) description.textContent = `Showing items for: ${supplier.name}`;

    try {
        // Get all component-supplier relationships for this supplier
        const response = await globalThis.api_request('/api/suppliers', 'GET');
        const suppliers = sortByKey(response?.data || [], (listedSupplier) => listedSupplier?.name);
        const currentSupplier = suppliers.find(s => s.id === supplierId);
        
        if (!currentSupplier?.component_suppliers?.length) {
            updateSupplierItemCategoryFilter([]);
            currentSupplierItems = [];
            currentItemsPage = 1;
            const emptyMsg = document.createElement('div');
            emptyMsg.className = 'pd-10 clr-grey1 br-5';
            emptyMsg.textContent = 'No items available for this supplier.';
            container.innerHTML = '';
            container.appendChild(emptyMsg);
            renderSupplierItemsPagination(0, 0);
            return;
        }

        currentSupplierItems = sortSupplierItemsByCategory(currentSupplier.component_suppliers || []);
        updateSupplierItemCategoryFilter(currentSupplierItems);
        currentItemsPage = 1;
        renderSupplierItems(supplierId);
    } catch (error) {
        console.error(error);
        updateSupplierItemCategoryFilter([]);
        currentSupplierItems = [];
        currentItemsPage = 1;
        const emptyMsg = document.createElement('div');
        emptyMsg.className = 'pd-10 clr-grey1 br-5';
        emptyMsg.textContent = 'Error loading supplier items.';
        container.innerHTML = '';
        container.appendChild(emptyMsg);
        renderSupplierItemsPagination(0, 0);
    }
}

function getFilteredSupplierItems() {
    const searchValue = normalizeFilterValue(getElement('supplier-item-search')?.value || '');
    const categoryFilterValue = String(selectedSupplierCategoryFilter || getElement('supplier-item-filter-category')?.value || '');

    return currentSupplierItems.filter((item) => {
        const category = getItemCategory(item);
        const categoryKey = String(category.id || 'uncategorized');
        const componentName = String(item?.component_name || item?.component?.component_name || '');
        const componentCode = String(item?.component_code || item?.component?.component_code || '');

        const searchText = normalizeFilterValue([
            componentName,
            componentCode,
        ].join(' '));

        const matchCategory = !categoryFilterValue || categoryFilterValue === categoryKey;
        const matchSearch = !searchValue || searchText.includes(searchValue);

        return matchCategory && matchSearch;
    });
}

function renderSupplierItems(supplierId) {
    const container = getElement('supplier-items-container');
    const description = getElement('supplier-items-description');
    if (!container) {
        return;
    }

    const filteredItems = getFilteredSupplierItems();
    updateActiveCategoryLabel();
    if (description) {
        description.textContent = `Showing ${filteredItems.length} item(s)`;
    }

    if (filteredItems.length === 0) {
        const hasCategoryFilter = Boolean(selectedSupplierCategoryFilter || getElement('supplier-item-filter-category')?.value);
        const hasSearchFilter = Boolean(normalizeFilterValue(getElement('supplier-item-search')?.value || ''));

        let emptyMessage = 'No items match this filter.';
        if (hasCategoryFilter && !hasSearchFilter) {
            emptyMessage = 'No items available in this category.';
        }

        if (hasCategoryFilter && hasSearchFilter) {
            emptyMessage = 'No items found in this category for your search.';
        }

        container.innerHTML = `<div class="pd-10 clr-grey1 br-5">${emptyMessage}</div>`;
        renderSupplierItemsPagination(0, 0);
        return;
    }

    const totalPages = Math.max(1, Math.ceil(filteredItems.length / ITEMS_PER_PAGE));
    if (currentItemsPage > totalPages) {
        currentItemsPage = totalPages;
    }
    if (currentItemsPage < 1) {
        currentItemsPage = 1;
    }

    const start = (currentItemsPage - 1) * ITEMS_PER_PAGE;
    const end = start + ITEMS_PER_PAGE;
    const paginatedItems = filteredItems.slice(start, end);

    const grouped = paginatedItems.reduce((acc, item) => {
        const category = getItemCategory(item);
        const categoryKey = getCategoryGroupKey(item);

        if (!acc[categoryKey]) {
            acc[categoryKey] = {
                categoryName: category.name,
                items: [],
            };
        }

        acc[categoryKey].items.push(item);
        return acc;
    }, {});

    const groupEntries = Object.values(grouped);
    const sortedGroups = sortByKey(groupEntries, (group) => group.categoryName);

    container.innerHTML = '';

    sortedGroups.forEach((group) => {
        const section = document.createElement('div');
        section.className = 'mg-b-15';

        const title = document.createElement('div');
        title.className = 'pd-10 fw-bold bdr-all-22 br-5 bg-white3 mg-b-5';
        title.textContent = `${group.categoryName} (${group.items.length})`;
        section.appendChild(title);

        const ul = document.createElement('ul');
        ul.className = 'list-style-none pd-0 mg-0 br-5 of-hidden';

        group.items.forEach((item) => {
            const li = document.createElement('li');
            li.className = 'pd-15 bdr-bottom-22 cursor-pointer hover-bg';
            const componentMinQty = item.min_quantity ?? item.component?.min_quantity ?? '-';
            const componentMaxQty = item.max_quantity ?? item.component?.max_quantity ?? '-';
            const componentSubscription = item.subscription_period || item.component?.subscription_period || '-';
            const componentName = item.component_name || item.component?.component_name || 'Unknown Item';
            const componentCode = item.component_code || item.component?.component_code || '-';
            const componentCurrency = item.currency || item.component?.currency || 'RM';

            li.innerHTML = `
                <div class="d-flex jc-between ai-start mg-b-10">
                    <div>
                        <div class="fw-bold">${componentName}</div>
                        <div class="fs-12 clr-grey1">${componentCode}</div>
                    </div>
                    <div class="d-flex gap-5">
                        <button type="button" class="btn-icon" title="Edit Item" data-edit-item-id="${item.id}"><i class="ri-edit-box-line"></i></button>
                        <button type="button" class="btn-icon" title="Delete Item" data-delete-item-id="${item.id}"><i class="ri-delete-bin-5-line"></i></button>
                    </div>
                </div>
                <div class="d-grid gap-15" style="grid-template-columns: repeat(2, 1fr);">
                    <div class="fs-12">
                        <span class="clr-grey1">Price:</span> <span class="fw-bold">${componentCurrency} ${formatAmount(item.price)}</span>
                    </div>
                    <div class="fs-12">
                        <span class="clr-grey1">Min Qty:</span> <span class="fw-bold">${componentMinQty}</span>
                    </div>
                    <div class="fs-12">
                        <span class="clr-grey1">Max Qty:</span> <span class="fw-bold">${componentMaxQty}</span>
                    </div>
                    <div class="fs-12">
                        <span class="clr-grey1">Subscription:</span> <span class="fw-bold">${componentSubscription}</span>
                    </div>
                </div>
            `;

            const editItemBtn = li.querySelector('[data-edit-item-id]');
            editItemBtn?.addEventListener('click', async (e) => {
                e.stopPropagation();
                await openSupplierItemFormModal('edit', item);
            });

            const deleteItemBtn = li.querySelector('[data-delete-item-id]');
            deleteItemBtn?.addEventListener('click', async (e) => {
                e.stopPropagation();
                const confirmed = globalThis.confirm('Delete this item from the company?');
                if (!confirmed) {
                    return;
                }

                try {
                    await globalThis.api_request(`/api/component-supplier/${item.id}`, 'DELETE');
                    await loadSuppliers();
                    await loadSupplierItems(supplierId);
                    globalThis.show_popup_temp('success', 'Success', ['Item deleted']);
                } catch (error) {
                    console.error(error);
                    globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to delete item']);
                }
            });

            ul.appendChild(li);
        });

        section.appendChild(ul);
        container.appendChild(section);
    });

    renderSupplierItemsPagination(filteredItems.length, totalPages);
}

function renderSupplierItemsPagination(totalItems, totalPages) {
    const pagination = getElement('supplier-items-pagination');
    if (!pagination) {
        return;
    }

    pagination.innerHTML = '';

    if (totalItems <= ITEMS_PER_PAGE || totalPages <= 1) {
        return;
    }

    const info = document.createElement('div');
    info.className = 'fs-12 clr-grey1';
    info.textContent = `Page ${currentItemsPage} of ${totalPages}`;

    const prevButton = document.createElement('button');
    prevButton.type = 'button';
    prevButton.className = 'bg-white3 clr-black1 pd-8 br-5 cursor-pointer fs-12';
    prevButton.style.border = '1px solid #d8d8d8';
    prevButton.textContent = 'Previous';
    prevButton.disabled = currentItemsPage <= 1;
    prevButton.addEventListener('click', () => {
        if (currentItemsPage <= 1) {
            return;
        }

        currentItemsPage -= 1;
        renderSupplierItems(currentSelectedSupplierId);
    });

    const nextButton = document.createElement('button');
    nextButton.type = 'button';
    nextButton.className = 'bg-white3 clr-black1 pd-8 br-5 cursor-pointer fs-12';
    nextButton.style.border = '1px solid #d8d8d8';
    nextButton.textContent = 'Next';
    nextButton.disabled = currentItemsPage >= totalPages;
    nextButton.addEventListener('click', () => {
        if (currentItemsPage >= totalPages) {
            return;
        }

        currentItemsPage += 1;
        renderSupplierItems(currentSelectedSupplierId);
    });

    pagination.appendChild(info);
    pagination.appendChild(prevButton);
    pagination.appendChild(nextButton);
}

function bindSupplierItemFormModal() {
    getElement('supplier-item-form-overlay')?.addEventListener('click', closeSupplierItemFormModal);
    getElement('supplier-item-form-close')?.addEventListener('click', closeSupplierItemFormModal);
    getElement('supplier-item-form-cancel')?.addEventListener('click', closeSupplierItemFormModal);
    getElement('supplier-item-form-submit')?.addEventListener('click', submitSupplierItemForm);

    getElement('supplier-item-category-id')?.addEventListener('change', async (event) => {
        const categoryId = Number(event.target?.value || 0);
        await loadSupplierItemComponentsByCategory(categoryId);
    });

    getElement('supplier-item-component-id')?.addEventListener('change', () => {
        applyComponentDetailsToSupplierItemForm(getSelectedSupplierItemComponent());
    });

    getElement('supplier-item-create-category-btn')?.addEventListener('click', async () => {
        openSupplierCategoryMiniModal('create');
    });

    getElement('supplier-item-edit-category-btn')?.addEventListener('click', () => {
        const selectedCategory = getSelectedSupplierItemCategory();
        if (!selectedCategory) {
            globalThis.show_popup_temp('error', 'Validation Error', ['Please select a category to edit']);
            return;
        }

        openSupplierCategoryMiniModal('edit', selectedCategory);
    });

    getElement('supplier-item-create-component-btn')?.addEventListener('click', async () => {
        await createComponentFromSupplierItemForm();
    });

    getElement('supplier-item-edit-component-btn')?.addEventListener('click', async () => {
        await editComponentFromSupplierItemForm();
    });

    getElement('supplier-category-mini-overlay')?.addEventListener('click', closeSupplierCategoryMiniModal);
    getElement('supplier-category-mini-close')?.addEventListener('click', closeSupplierCategoryMiniModal);
    getElement('supplier-category-mini-cancel')?.addEventListener('click', closeSupplierCategoryMiniModal);
    getElement('supplier-category-mini-submit')?.addEventListener('click', async () => {
        await createCategoryFromSupplierItemForm();
    });
}

function bindSupplierItemCreate() {
    getElement('create-supplier-item-btn')?.addEventListener('click', async () => {
        await openSupplierItemFormModal('create');
    });

    getElement('create-supplier-category-btn')?.addEventListener('click', () => {
        openSupplierCategoryMiniModal('create');
    });

    getElement('supplier-item-search')?.addEventListener('input', () => {
        currentItemsPage = 1;
        renderSupplierItems(currentSelectedSupplierId);
    });

    getElement('supplier-item-filter-category')?.addEventListener('change', (event) => {
        selectedSupplierCategoryFilter = String(event.target?.value || '');
        ensureVisibleSelectedCategoryOption();
        currentItemsPage = 1;
        renderSupplierCategoryList();
        renderSupplierItems(currentSelectedSupplierId);
    });

    getElement('supplier-item-clear-filters')?.addEventListener('click', () => {
        const searchInput = getElement('supplier-item-search');
        const categorySelect = getElement('supplier-item-filter-category');

        if (searchInput) {
            searchInput.value = '';
        }

        selectedSupplierCategoryFilter = '';
        if (categorySelect) {
            categorySelect.value = '';
        }
        ensureVisibleSelectedCategoryOption();

        currentItemsPage = 1;
        renderSupplierCategoryList();
        renderSupplierItems(currentSelectedSupplierId);
    });
}

function renderSupplierOptions() {
    const select = getElement('supplier-id');
    if (!select) {
        return;
    }

    if (cachedSuppliers.length === 0) {
        select.innerHTML = '<option value="">No suppliers found</option>';
        return;
    }

    select.innerHTML = cachedSuppliers.map((supplier) => (
        `<option value="${supplier.id}">${supplier.name}</option>`
    )).join('');
}

async function loadCategoriesForSupplierPage() {
    const categorySelect = getElement('supplier-category-id');
    if (!categorySelect) {
        return;
    }

    const response = await globalThis.api_request('/api/categories', 'GET');
    const categories = sortByKey(response?.data || [], (category) => category?.name);
    cachedCategories = categories;

    if (categories.length === 0) {
        categorySelect.innerHTML = '<option value="">No categories found</option>';
        return;
    }

    categorySelect.innerHTML = categories.map((category) => (
        `<option value="${category.id}">${category.name}</option>`
    )).join('');

    await loadComponentsByCategory(Number(categorySelect.value || 0));
}

async function loadComponentsByCategory(categoryId) {
    const componentSelect = getElement('supplier-component-id');
    if (!componentSelect) {
        return;
    }

    if (!categoryId) {
        componentSelect.innerHTML = '<option value="">No items found</option>';
        return;
    }

    const response = await globalThis.api_request(`/api/components/${categoryId}`, 'GET');
    const components = sortByKey(response?.data || [], (component) => component?.component_name);

    if (components.length === 0) {
        componentSelect.innerHTML = '<option value="">No items found</option>';
        await loadOffersByComponent(null);
        return;
    }

    componentSelect.innerHTML = components.map((component) => (
        `<option value="${component.id}">${component.component_name} (${component.component_code})</option>`
    )).join('');

    await loadOffersByComponent(Number(componentSelect.value || 0));
}

async function loadOffersByComponent(componentId) {
    const container = getElement('supplier-offers');
    if (!container) {
        return;
    }

    if (!componentId) {
        container.innerHTML = '<div class="pd-10 clr-grey1">No supplier offers found.</div>';
        return;
    }

    const response = await globalThis.api_request(`/api/component-supplier/${componentId}`, 'GET');
    const offers = sortByKey(response?.data || [], (offer) => offer?.supplier?.name);

    if (offers.length === 0) {
        container.innerHTML = '<div class="pd-10 clr-grey1">No supplier offers found for this item.</div>';
        return;
    }

    container.innerHTML = '';

    offers.forEach((offer) => {
        const row = document.createElement('div');
        row.className = 'pd-10 bdr-bottom-22';
        const minQty = offer.component?.min_quantity ?? '-';
        const maxQty = offer.component?.max_quantity ?? '-';
        const subscription = offer.component?.subscription_period || '-';

        row.innerHTML = `
            <div class="d-flex ai-center jc-between mg-b-5">
                <div class="fw-bold">${offer?.supplier?.name || 'Unknown Supplier'}</div>
                <button type="button" class="btn-icon" title="Delete Offer" data-delete-offer-id="${offer.id}"><i class="ri-delete-bin-5-line"></i></button>
            </div>
            <div class="d-grid gap-10 fs-12" style="grid-template-columns: repeat(4, minmax(120px, 1fr));">
                <div><span class="clr-grey1">Price:</span> RM ${formatAmount(offer.price)}</div>
                <div><span class="clr-grey1">Min Qty:</span> ${minQty}</div>
                <div><span class="clr-grey1">Max Qty:</span> ${maxQty}</div>
                <div><span class="clr-grey1">Subscription:</span> ${subscription}</div>
            </div>
        `;

        const deleteOfferBtn = row.querySelector('[data-delete-offer-id]');
        deleteOfferBtn?.addEventListener('click', async () => {
            const confirmed = globalThis.confirm('Delete this supplier offer?');
            if (!confirmed) {
                return;
            }

            try {
                await globalThis.api_request(`/api/component-supplier/${offer.id}`, 'DELETE');
                await loadOffersByComponent(componentId);
                globalThis.show_popup_temp('success', 'Success', ['Supplier offer deleted']);
            } catch (error) {
                console.error(error);
                globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to delete supplier offer']);
            }
        });

        container.appendChild(row);
    });
}

function bindSupplierCreate() {
    const button = getElement('create-supplier-btn');
    button?.addEventListener('click', () => {
        openSupplierFormModal('create');
    });
}

function bindCategoryAndComponentChange() {
    const categorySelect = getElement('supplier-category-id');
    const componentSelect = getElement('supplier-component-id');

    categorySelect?.addEventListener('change', async () => {
        await loadComponentsByCategory(Number(categorySelect.value || 0));
    });

    componentSelect?.addEventListener('change', async () => {
        await loadOffersByComponent(Number(componentSelect.value || 0));
    });
}

function bindAssignmentSave() {
    const saveButton = getElement('assign-supplier-btn');

    saveButton?.addEventListener('click', async () => {
        const componentId = Number(getElement('supplier-component-id')?.value || 0);
        const supplierId = Number(getElement('supplier-id')?.value || 0);
        const price = Number(getElement('supplier-price')?.value || 0);

        if (!componentId || !supplierId) {
            globalThis.show_popup_temp('error', 'Validation Error', ['Item and supplier are required']);
            return;
        }

        if (price < 0) {
            globalThis.show_popup_temp('error', 'Validation Error', ['Price must be 0 or greater']);
            return;
        }

        try {
            await globalThis.api_request('/api/component-supplier', 'POST', {
                component_id: componentId,
                supplier_id: supplierId,
                price,
            });

            await loadOffersByComponent(componentId);
            globalThis.show_popup_temp('success', 'Success', ['Supplier offer saved']);
        } catch (error) {
            console.error(error);
            globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to save supplier offer']);
        }
    });
}

export async function initSuppliersPage() {
    if (!ensureSuppliersPage()) {
        return;
    }

    try {
        const response = await globalThis.api_request('/api/categories', 'GET');
        cachedCategories = sortByKey(response?.data || [], (category) => category?.name);
        renderSupplierCategoryList();
        updateActiveCategoryLabel();
    } catch (error) {
        console.error(error);
        cachedCategories = [];
        renderSupplierCategoryList();
        updateActiveCategoryLabel();
    }

    bindSupplierFormModal();
    bindSupplierCreate();
    bindSupplierItemFormModal();
    bindSupplierItemCreate();

    await loadSuppliers();
    await loadSupplierItems(currentSelectedSupplierId);
}
