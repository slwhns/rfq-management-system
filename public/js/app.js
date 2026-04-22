import { loadProjects, createProject } from './modules/projects.js';
import { initPricingProjects, getCurrentProjectId } from './modules/pricing-projects.js';
import { initPricingCategories } from './modules/pricing-categories.js';
import { loadPricingComponents, refreshSelectedProjectComponents as refreshPricingPanel } from './modules/pricing-components.js';
import { calculatePricing, generatePricingQuote } from './modules/pricing-summary.js';
import { initSuppliersPage } from './modules/suppliers.js';
import { initPurchaseRequestProjects } from './modules/purchase-request-projects.js';
import { initPurchaseOrders } from './modules/purchase-orders.js';
import { initAdminStaffIndex } from './modules/admin-staff-index.js';
import { initProfileRail } from './modules/profile-rail.js';
import { initPrintTriggers } from './modules/print-trigger.js';
import { initQuotationPage } from './modules/quotation-page.js';

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

function isPricingPage() {
    return Boolean(
        document.getElementById('project-select')
        && document.getElementById('component-filter')
        && document.getElementById('components')
        && document.getElementById('tax')
        && document.getElementById('total')
    );
}

async function initPricingPageModules() {
    await initPricingProjects(async (projectId) => {
        await refreshPricingPanel(projectId);
    });

    await initPricingCategories(async (categoryId) => {
        await loadPricingComponents(categoryId, getCurrentProjectId);
    });
}

function initPageModules() {
    globalThis.initProfilePage?.();

    const hasPricingLayout = isPricingPage();
    if (hasPricingLayout) {
        initPricingPageModules();
    }

    if (document.getElementById('supplier-list') && document.getElementById('supplier-items-container')) {
        initSuppliersPage();
        return;
    }

    if (document.getElementById('quotation-project-select') && document.getElementById('quotation-project-list')) {
        initPurchaseRequestProjects();
        return;
    }

    if (document.getElementById('purchase-orders-page')) {
        initPurchaseOrders();
        return;
    }

    if (document.getElementById('quotation-page')) {
        initQuotationPage();
        return;
    }

    if (document.getElementById('staff-page') && document.getElementById('staff-modal')) {
        initAdminStaffIndex();
        return;
    }

    if (document.getElementById('categories')) {
        loadCategories();
    }

    if (document.getElementById('component-category-id')) {
        loadCategoryOptions();
    }

    if (document.getElementById('project-list') || document.getElementById('project-select')) {
        loadProjects();
    }
}

function isItemsAdminMode() {
    return Boolean(document.getElementById('category-modal'));
}

document.addEventListener('DOMContentLoaded', () => {
    initProfileRail();
    initPrintTriggers();
    initPageModules();
});

async function editCategory(categoryId) {
    try {
        const response = await globalThis.api_request('/api/categories', 'GET');
        const categories = response?.data || [];
        const category = categories.find((item) => item.id === categoryId);

        if (!category) {
            globalThis.show_popup_temp('error', 'Error', ['Category not found']);
            return;
        }

        const name = globalThis.prompt('Category name', category.name || '');
        if (name === null) return;

        const description = globalThis.prompt('Description (optional)', category.description || '');
        if (description === null) return;

        const icon = globalThis.prompt('Icon (optional)', category.icon || '📦');
        if (icon === null) return;

        await globalThis.api_request(`/api/items/categories/${categoryId}`, 'PATCH', {
            name: name.trim(),
            description: description.trim() || null,
            icon: icon.trim() || '📦',
        });

        await loadCategories();
        await loadCategoryOptions();
        globalThis.show_popup_temp('success', 'Success', ['Category updated']);
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', ['Failed to update category']);
    }
}

async function deleteCategory(categoryId) {
    try {
        const confirmed = globalThis.confirm('Delete this category?');
        if (!confirmed) return;

        await globalThis.api_request(`/api/items/categories/${categoryId}`, 'DELETE');

        await loadCategories();
        await loadCategoryOptions();
        globalThis.show_popup_temp('success', 'Success', ['Category deleted']);
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', [error.message || 'Failed to delete category']);
    }
}

async function loadCategories(preferredCategoryId = null) {
    try {
        const response = await globalThis.api_request('/api/categories', 'GET');
        const categories = sortByKey(response?.data || [], (category) => category?.name);
        const list = document.getElementById('categories');

        if (!list) {
            return;
        }

        list.innerHTML = '';

        const currentSelectedId = Number(list.querySelector('li.app-selected-item')?.dataset?.categoryId || 0) || null;
        let selectedCategoryId = preferredCategoryId || currentSelectedId;

        if (!selectedCategoryId || !categories.some((category) => category.id === selectedCategoryId)) {
            selectedCategoryId = categories.length > 0 ? categories[0].id : null;
        }

        categories.forEach((category) => {
            const li = document.createElement('li');
            li.dataset.categoryId = String(category.id);
            const isSelected = selectedCategoryId === category.id;
            const icon = category.icon || '📦';
            const description = category.description || 'No description';
            const descriptionClass = isSelected ? 'clr-black1' : 'clr-grey1';
            const adminActions = isItemsAdminMode()
                ? `
                    <div class="d-flex fd-column gap-5 mg-l-10">
                        <button type="button" class="btn-icon" title="Edit" onclick="event.stopPropagation(); openEditCategoryModal(${category.id})"><i class="ri-edit-box-line"></i></button>
                        <button type="button" class="btn-icon" title="Delete" onclick="event.stopPropagation(); openDeleteCategoryModal(${category.id})"><i class="ri-delete-bin-5-line"></i></button>
                    </div>
                `
                : '';

            li.className = `pd-10 cursor-pointer bdr-bottom-22 ${isSelected ? 'app-selected-item clr-black1 br-10' : ''}`;
            li.innerHTML = `
                <div class="d-flex ai-center jc-between">
                    <div class="d-flex ai-center">
                        <div class="fs-20 mg-r-10">${icon}</div>
                        <div>
                            <div class="fw-bold">${category.name}</div>
                            <div class="fs-12 ${descriptionClass}">${description}</div>
                        </div>
                    </div>
                    ${adminActions}
                </div>
            `;
            li.addEventListener('click', () => {
                loadComponents(category.id);

                const categoryItems = list.querySelectorAll('li');
                categoryItems.forEach((item) => {
                    item.classList.remove('bg-plt1', 'bg-yellow', 'app-selected-item', 'clr-white', 'clr-black1', 'br-10');

                    const desc = item.querySelector('.fs-12');
                    if (desc) {
                        desc.classList.remove('clr-white', 'clr-black1');
                        desc.classList.add('clr-grey1');
                    }
                });

                li.classList.add('app-selected-item', 'clr-black1', 'br-10');

                const selectedDesc = li.querySelector('.fs-12');
                if (selectedDesc) {
                    selectedDesc.classList.remove('clr-grey1');
                    selectedDesc.classList.add('clr-black1');
                }
            });
            list.appendChild(li);
        });

        if (selectedCategoryId) {
            await loadComponents(selectedCategoryId);
        }
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', ['Failed to load categories']);
    }
}

async function loadCategoryOptions() {
    const select = document.getElementById('component-category-id');
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

async function createCategory() {
    openAddCategoryModal();
}

function getComponentFormElements() {
    return {
        categoryInput: document.getElementById('component-category-id'),
        nameInput: document.getElementById('component-name'),
        codeInput: document.getElementById('component-code'),
        priceInput: document.getElementById('component-price'),
        minQtyInput: document.getElementById('component-min-qty'),
        maxQtyInput: document.getElementById('component-max-qty'),
        smartInput: document.getElementById('component-is-smart'),
        requiresLicenseInput: document.getElementById('component-requires-license'),
        licenseTypeInput: document.getElementById('component-license-type'),
        descriptionInput: document.getElementById('component-description'),
    };
}

function getComponentFormData(elements) {
    return {
        categoryId: Number(elements.categoryInput?.value || 0),
        componentName: elements.nameInput?.value.trim() || '',
        componentCode: elements.codeInput?.value.trim() || '',
        basePrice: Number(elements.priceInput?.value || 0),
        minQuantity: Number(elements.minQtyInput?.value || 1),
        maxQuantity: elements.maxQtyInput?.value ? Number(elements.maxQtyInput.value) : null,
        isSmartComponent: Boolean(elements.smartInput?.checked),
        requiresLicense: Boolean(elements.requiresLicenseInput?.checked),
        licenseType: elements.licenseTypeInput?.value.trim() || '',
        description: elements.descriptionInput?.value.trim() || '',
    };
}

function validateComponentFormData(data) {
    if (!data.categoryId) {
        globalThis.show_popup_temp('error', 'Validation Error', ['Category is required']);
        return false;
    }

    if (!data.componentName || !data.componentCode) {
        globalThis.show_popup_temp('error', 'Validation Error', ['Component name and code are required']);
        return false;
    }

    return true;
}

function resetComponentForm(elements) {
    if (elements.nameInput) elements.nameInput.value = '';
    if (elements.codeInput) elements.codeInput.value = '';
    if (elements.priceInput) elements.priceInput.value = '';
    if (elements.minQtyInput) elements.minQtyInput.value = '';
    if (elements.maxQtyInput) elements.maxQtyInput.value = '';
    if (elements.smartInput) elements.smartInput.checked = false;
    if (elements.requiresLicenseInput) elements.requiresLicenseInput.checked = false;
    if (elements.licenseTypeInput) elements.licenseTypeInput.value = '';
    if (elements.descriptionInput) elements.descriptionInput.value = '';
}

async function createComponent() {
    openAddComponentModal();
}

async function loadComponents(categoryId) {
    try {
        const response = await globalThis.api_request(`/api/components/${categoryId}`, 'GET');
        const components = sortByKey(response?.data || [], (component) => component?.component_name);
        const list = document.getElementById('components');

        if (!list) {
            return;
        }

        list.innerHTML = '';
        const mode = list.dataset.mode || 'pricing';

        components.forEach((component) => {
            const li = document.createElement('li');
            const componentCode = component.component_code || '-';
            const componentName = component.component_name || '-';
            const description = component.description || '-';
            const unit = component.unit || '-';
            const currency = component.currency || 'RM';
            const basePrice = component.base_price ?? 0;
            const minQuantity = component.min_quantity ?? '-';
            const maxQuantity = component.max_quantity ?? '-';
            const smartComponent = component.is_smart_component ? 'Yes' : 'No';
            const requiresLicense = component.requires_license ? 'Yes' : 'No';
            const licenseType = component.license_type || '-';
            const subscriptionPeriod = component.subscription_period || '-';
            let supplierOffers = [];
            if (Array.isArray(component.supplier_prices)) {
                supplierOffers = component.supplier_prices;
            } else if (Array.isArray(component.supplierPrices)) {
                supplierOffers = component.supplierPrices;
            }

            supplierOffers = sortByKey(supplierOffers, (offer) => offer?.supplier?.name);

            const companyDetailsHtml = supplierOffers.length > 0
                ? supplierOffers.map((offer) => {
                    const companyName = offer?.supplier?.name || 'Unknown company';
                    const companyCode = offer?.component_code || componentCode;
                    const companyDescription = offer?.description || description;
                    const offerPrice = offer?.price ?? null;
                    const companyUnit = offer?.unit || unit;
                    const companyMinQuantity = offer?.min_quantity ?? minQuantity;
                    const companyMaxQuantity = offer?.max_quantity ?? maxQuantity;
                    const companySmart = (offer?.is_smart_component ?? component.is_smart_component) ? 'Yes' : 'No';
                    const companyRequiresLicense = (offer?.requires_license ?? component.requires_license) ? 'Yes' : 'No';
                    const companyLicenseType = offer?.license_type || licenseType;
                    const companySubscription = offer?.subscription_period || subscriptionPeriod;
                    const companyActions = (mode === 'items' && isItemsAdminMode())
                        ? `
                            <div class="d-flex gap-5">
                                <button type="button" class="btn-icon" title="Edit Company Component" onclick="openEditCompanyComponentModal(${component.id}, ${categoryId}, ${offer.supplier_id})"><i class="ri-edit-box-line"></i></button>
                                <button type="button" class="btn-icon" title="Delete Company Component" onclick="openDeleteCompanyComponentModal(${offer.id}, ${component.id}, ${categoryId})"><i class="ri-delete-bin-5-line"></i></button>
                            </div>
                        `
                        : '';

                    return `
                        <div class="pd-14 br-10 bdr-all-22 bg-white5 pd-10">
                            <div class="d-flex jc-between ai-center mg-b-10">
                                <div class="fw-bold clr-black1">${companyName}</div>
                                ${companyActions}
                            </div>
                            <div class="fs-12 clr-grey1 mg-b-8">Code: ${companyCode}</div>
                            <div class="fs-12 clr-grey1 mg-b-10">${companyDescription}</div>

                            <div class="d-grid gap-10 fs-12" style="grid-template-columns: repeat(3, minmax(140px, 1fr));">
                                <div><span class="clr-grey1">Price:</span> <span class="fw-bold clr-black1">${offerPrice === null ? '-' : formatCurrency(offerPrice, currency)}</span></div>
                                <div><span class="clr-grey1">Unit:</span> ${companyUnit}</div>
                                <div><span class="clr-grey1">Min Qty:</span> ${companyMinQuantity}</div>
                                <div><span class="clr-grey1">Max Qty:</span> ${companyMaxQuantity}</div>
                                <div><span class="clr-grey1">Smart:</span> ${companySmart}</div>
                                <div><span class="clr-grey1">License Required:</span> ${companyRequiresLicense}</div>
                                <div><span class="clr-grey1">License Type:</span> ${companyLicenseType}</div>
                                <div><span class="clr-grey1">Subscription:</span> ${companySubscription}</div>
                            </div>
                        </div>
                    `;
                }).join('')
                : '<div class="pd-10 fs-12 clr-grey1 bdr-all-22 br-5">No company offer assigned yet.</div>';

            if (mode === 'items') {
                li.innerHTML = `
                    <div class="pd-20 bdr-bottom-22">
                        <div class="d-flex jc-between ai-center mg-b-5">
                            <div class="fw-bold">${componentName}</div>
                            <div></div>
                        </div>

                        <div class="mg-t-12">
                            <div class="d-grid gap-8 pd-10">
                                ${companyDetailsHtml}
                            </div>
                        </div>
                    </div>
                `;
            } else {
                li.innerHTML = `
                    <div class="d-grid ai-start pd-10 bdr-bottom-22" style="grid-template-columns: 1fr 170px; gap: 10px;">
                        <div>
                            <div class="d-flex jc-between ai-center mg-b-5">
                                <div class="fw-bold">${componentName}</div>
                                <div class="fs-12 clr-grey1">Code: ${componentCode}</div>
                            </div>

                            <div class="fs-12 clr-grey1 mg-b-10">${description}</div>

                            <div class="d-grid gap-10 fs-12" style="grid-template-columns: repeat(3, minmax(120px, 1fr));">
                                <div><span class="clr-grey1">Price:</span> ${formatCurrency(basePrice, currency)}</div>
                                <div><span class="clr-grey1">Unit:</span> ${unit}</div>
                                <div><span class="clr-grey1">Min Qty:</span> ${minQuantity}</div>
                                <div><span class="clr-grey1">Max Qty:</span> ${maxQuantity}</div>
                                <div><span class="clr-grey1">Smart:</span> ${smartComponent}</div>
                                <div><span class="clr-grey1">License Required:</span> ${requiresLicense}</div>
                                <div><span class="clr-grey1">License Type:</span> ${licenseType}</div>
                                <div><span class="clr-grey1">Subscription:</span> ${subscriptionPeriod}</div>
                            </div>
                        </div>

                        <div class="d-flex ai-center jc-end">
                            <input class="w-60 pd-5 bdr-all-22 br-5" type="number" id="qty-${component.id}" value="1" min="1">
                            <div class="bg-blue clr-white pd-5 br-5 mg-l-10 cursor-pointer" onclick="globalThis.addComponent?.(${component.id})">Add</div>
                        </div>
                    </div>
                `;
            }

            list.appendChild(li);
        });
    } catch (error) {
        console.error(error);
        globalThis.show_popup_temp('error', 'Error', ['Failed to load components']);
    }
}

globalThis.createProject = createProject;
globalThis.loadProjects = loadProjects;
globalThis.initPageModules = initPageModules;
globalThis.createCategory = createCategory;
globalThis.createComponent = createComponent;
globalThis.loadCategories = loadCategories;
globalThis.loadCategoryOptions = loadCategoryOptions;
globalThis.loadComponents = loadComponents;

globalThis.calculatePrice = () => calculatePricing(getCurrentProjectId);
globalThis.generateQuote = () => generatePricingQuote(getCurrentProjectId);
globalThis.refreshPricingSelectedComponents = refreshPricingPanel;
