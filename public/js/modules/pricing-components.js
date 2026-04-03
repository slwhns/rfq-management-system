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

let pricingComponentViewMode = 'expanded';
let activePricingCategoryId = null;
let pricingProjectResolver = null;
const pricingComponentState = {
    allComponents: [],
    currentPage: 1,
    perPage: 5,
    searchTerm: '',
    listenersAttached: false,
};

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

function getSupplierOffers(component) {
    if (Array.isArray(component?.supplier_prices)) {
        return component.supplier_prices;
    }

    if (Array.isArray(component?.supplierPrices)) {
        return component.supplierPrices;
    }

    return [];
}

function matchesComponentSearch(component, searchTerm) {
    if (!searchTerm) {
        return true;
    }

    const supplierNames = getSupplierOffers(component)
        .map((offer) => offer?.supplier?.name || '')
        .join(' ');

    const haystack = [
        component?.component_name,
        component?.component_code,
        component?.description,
        component?.license_type,
        component?.subscription_period,
        supplierNames,
    ].join(' ').toLowerCase();

    return haystack.includes(searchTerm);
}

function getVisiblePricingComponents() {
    const searchInput = document.getElementById('component-search');
    const liveSearchTerm = searchInput?.value ?? pricingComponentState.searchTerm;
    pricingComponentState.searchTerm = liveSearchTerm;
    const searchTerm = liveSearchTerm.trim().toLowerCase();

    return pricingComponentState.allComponents.filter((component) => matchesComponentSearch(component, searchTerm));
}

function getVisiblePricingEntries() {
    return getVisiblePricingComponents().flatMap((component) => {
        const supplierOffers = sortByKey(getSupplierOffers(component), (offerItem) => offerItem?.supplier?.name);

        if (supplierOffers.length === 0) {
            return [{
                component,
                offer: null,
                offerIndex: 0,
            }];
        }

        return supplierOffers.map((offer, offerIndex) => ({
            component,
            offer,
            offerIndex,
        }));
    });
}

function updatePaginationSummary(totalItems, totalPages) {
    const pagination = document.getElementById('components-pagination');
    if (!pagination) {
        return;
    }

    if (totalItems === 0) {
        pagination.innerHTML = '<div>No matching components found.</div>';
        return;
    }

    const startItem = ((pricingComponentState.currentPage - 1) * pricingComponentState.perPage) + 1;
    const endItem = Math.min(startItem + pricingComponentState.perPage - 1, totalItems);
    const prevDisabled = pricingComponentState.currentPage <= 1 ? 'disabled' : '';
    const nextDisabled = pricingComponentState.currentPage >= totalPages ? 'disabled' : '';

    pagination.innerHTML = `
        <div>Showing ${startItem}-${endItem} of ${totalItems}</div>
        <div class="d-flex ai-center">
            <button type="button" class="pd-5 br-5 cursor-pointer" style="border: 1px solid #d9d9d9; background: #fff;" data-components-page="prev" ${prevDisabled}>Prev</button>
            <div class="mg-l-10 mg-r-10">Page ${pricingComponentState.currentPage} of ${totalPages}</div>
            <button type="button" class="pd-5 br-5 cursor-pointer" style="border: 1px solid #d9d9d9; background: #fff;" data-components-page="next" ${nextDisabled}>Next</button>
        </div>
    `;

    pagination.querySelector('[data-components-page="prev"]')?.addEventListener('click', () => {
        if (pricingComponentState.currentPage > 1) {
            pricingComponentState.currentPage -= 1;
            renderPricingComponentsList();
        }
    });

    pagination.querySelector('[data-components-page="next"]')?.addEventListener('click', () => {
        if (pricingComponentState.currentPage < totalPages) {
            pricingComponentState.currentPage += 1;
            renderPricingComponentsList();
        }
    });
}

function attachPricingComponentControls() {
    if (pricingComponentState.listenersAttached) {
        return;
    }

    const searchInput = document.getElementById('component-search');
    const clearButton = document.getElementById('component-clear');

    pricingComponentState.searchTerm = searchInput?.value || '';

    searchInput?.addEventListener('input', () => {
        pricingComponentState.searchTerm = searchInput.value || '';
        pricingComponentState.currentPage = 1;
        renderPricingComponentsList();
    });

    clearButton?.addEventListener('click', () => {
        pricingComponentState.searchTerm = '';
        pricingComponentState.currentPage = 1;

        if (searchInput) {
            searchInput.value = '';
        }

        renderPricingComponentsList();
    });

    pricingComponentState.listenersAttached = true;
}

function applyQuantityConstraints(input, minQty, maxQty) {
    if (!input) {
        return;
    }

    input.min = String(minQty);

    if (maxQty) {
        input.max = String(maxQty);
    } else {
        input.removeAttribute('max');
    }

    const currentQty = Number(input.value || minQty);
    if (currentQty < minQty) {
        input.value = String(minQty);
    }

    if (maxQty && currentQty > maxQty) {
        input.value = String(maxQty);
    }
}

function buildOfferMeta(component, offer, fallbackIndex = 0) {
    const componentCode = component.component_code || '-';
    const componentName = component.component_name || '-';
    const description = component.description || '-';
    const unit = component.unit || '-';
    const currency = component.currency || 'RM';
    const basePrice = component.base_price ?? 0;
    const componentMinQty = Number(component.min_quantity ?? 1) || 1;
    const componentMaxQty = component.max_quantity == null ? null : Number(component.max_quantity);
    const licenseType = component.license_type || '-';
    const subscriptionPeriod = component.subscription_period || '-';

    const supplierId = Number(offer?.supplier_id || 0);
    const supplierName = offer?.supplier?.name || 'Unknown Supplier';
    const offerCode = offer?.component_code || componentCode;
    const offerDescription = offer?.description || description;
    const offerCurrency = offer?.currency || currency;
    const offerPrice = offer?.price ?? basePrice;
    const offerUnit = offer?.unit || unit;
    const offerMinQty = Number(offer?.min_quantity ?? componentMinQty) || componentMinQty;
    const rawOfferMaxQty = offer?.max_quantity ?? componentMaxQty;
    const offerMaxQty = rawOfferMaxQty == null ? null : Number(rawOfferMaxQty);
    const offerMaxAttr = offerMaxQty ? `max="${offerMaxQty}"` : '';
    const offerSmart = (offer?.is_smart_component ?? component.is_smart_component) ? 'Yes' : 'No';
    const offerRequiresLicense = (offer?.requires_license ?? component.requires_license) ? 'Yes' : 'No';
    const offerLicenseType = offer?.license_type || licenseType;
    const offerSubscription = offer?.subscription_period || subscriptionPeriod;
    const qtyInputId = `qty-${component.id}-${supplierId || fallbackIndex}`;

    return {
        supplierId,
        supplierName,
        offerCode,
        offerDescription,
        offerCurrency,
        offerPrice,
        offerUnit,
        offerMinQty,
        offerMaxQty,
        offerMaxAttr,
        offerSmart,
        offerRequiresLicense,
        offerLicenseType,
        offerSubscription,
        qtyInputId,
        componentName,
    };
}

function renderPricingViewToggle(container) {
    const isExpanded = pricingComponentViewMode === 'expanded';
    const isCompact = pricingComponentViewMode === 'compact';
    const controls = document.createElement('div');
    controls.className = 'd-flex jc-end ai-center pd-10';
    controls.innerHTML = `
        <span class="fs-12 clr-grey1 mg-r-10">View:</span>
        <button type="button" class="pd-5 br-5 cursor-pointer ${isExpanded ? 'bg-blue clr-white' : 'bg-white5 clr-grey1'}" style="border: 1px solid #d9d9d9;" data-pricing-view="expanded">Expanded</button>
        <button type="button" class="pd-5 br-5 mg-l-5 cursor-pointer ${isCompact ? 'bg-blue clr-white' : 'bg-white5 clr-grey1'}" style="border: 1px solid #d9d9d9;" data-pricing-view="compact">Compact</button>
    `;

    controls.querySelectorAll('[data-pricing-view]').forEach((button) => {
        button.addEventListener('click', () => {
            const selectedMode = button.dataset.pricingView || 'expanded';
            pricingComponentViewMode = selectedMode;
            renderPricingComponentsList();
        });
    });

    container.appendChild(controls);
}

export async function refreshSelectedProjectComponents(projectId) {
    const container = document.getElementById('pricing-selected-components');
    const projectNameEl = document.getElementById('pricing-selected-project-name');

    if (!container) {
        return;
    }

    if (!projectId) {
        if (projectNameEl) {
            projectNameEl.textContent = 'No project selected';
        }

        container.innerHTML = '<div class="pd-10 clr-grey1">Select a project to view selected components.</div>';
        return;
    }

    const response = await globalThis.api_request(`/api/projects/${projectId}`, 'GET');
    const project = response?.data || null;
    const projectName = project?.project_name || project?.name || 'Selected project';

    if (projectNameEl) {
        projectNameEl.textContent = projectName;
    }

    const selectedComponents = sortByKey(Array.isArray(project?.components) ? project.components : [], (projectComponent) => projectComponent?.component?.component_name);

    if (selectedComponents.length === 0) {
        container.innerHTML = '<div class="pd-10 clr-grey1">No components selected for this project yet.</div>';
        return;
    }

    container.innerHTML = '';

    selectedComponents.forEach((projectComponent) => {
        const component = projectComponent.component || {};
        const notes = parseProjectComponentNotes(projectComponent);
        const quantity = Number(projectComponent.quantity || 0);
        const unitPrice = Number(projectComponent.custom_price ?? component.base_price ?? 0);
        const discountPercent = Number(notes?.discount_percent || 0);
        const lineSubtotal = quantity * unitPrice;
        const discountAmount = lineSubtotal * (discountPercent / 100);
        const lineTotal = lineSubtotal - discountAmount;
        const currency = component.currency || 'RM';
        const supplierName = notes?.supplier_name || '-';
        const supplierSubscription = notes?.subscription_period || '-';

        const row = document.createElement('div');
        row.className = 'pd-10 bdr-bottom-22';
        row.innerHTML = `
            <div class="d-flex jc-between ai-start mg-b-8">
                <div>
                    <div class="fw-bold">${component.component_name || '-'}</div>
                    <div class="fs-12 clr-grey1">Code: ${component.component_code || '-'}</div>
                </div>
                <div class="d-flex fd-column" style="row-gap: 2px;">
                    <button type="button" class="btn-icon" title="Edit Component" data-edit-project-component-id="${projectComponent.id}"><i class="ri-edit-box-line"></i></button>
                    <button type="button" class="btn-icon" title="Delete Component" data-delete-project-component-id="${projectComponent.id}"><i class="ri-delete-bin-5-line"></i></button>
                </div>
            </div>
            <div class="d-grid gap-10 fs-12" style="grid-template-columns: repeat(3, minmax(120px, 1fr));">
                <div><span class="clr-grey1">Qty:</span> ${quantity}</div>
                <div><span class="clr-grey1">Unit Price:</span> ${formatCurrency(unitPrice, currency)}</div>
                <div><span class="clr-grey1">Supplier:</span> ${supplierName}</div>
                <div><span class="clr-grey1">Discount:</span> ${discountPercent}% (${formatCurrency(discountAmount, currency)})</div>
                <div><span class="clr-grey1">Subscription:</span> ${supplierSubscription}</div>
                <div><span class="clr-grey1">Total:</span> ${formatCurrency(lineTotal, currency)}</div>
            </div>
        `;

        const editButton = row.querySelector('[data-edit-project-component-id]');
        editButton?.addEventListener('click', async () => {
            globalThis.openProjectComponentEditor?.(projectComponent, project.id);
        });

        const deleteButton = row.querySelector('[data-delete-project-component-id]');
        deleteButton?.addEventListener('click', async () => {
            await globalThis.deleteProjectComponentAssignment?.(projectComponent.id, project.id);
        });

        container.appendChild(row);
    });
}

function buildPricingComponentMarkup(component, offer = null, offerIndex = 0) {
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
    const componentMinQty = Number(component.min_quantity ?? 1) || 1;
    const componentMaxQty = component.max_quantity == null ? null : Number(component.max_quantity);
    const baseMaxAttr = componentMaxQty ? `max="${componentMaxQty}"` : '';
    let supplierCardsMarkup = '';

    if (offer) {
        const meta = buildOfferMeta(component, offer, offerIndex);

        supplierCardsMarkup = pricingComponentViewMode === 'compact'
            ? `
                <div class="pd-20 br-8 bdr-all-22 bg-white5">
                    <div class="d-flex jc-between ai-center fs-12 mg-b-6">
                        <div class="fw-bold">${meta.supplierName}</div>
                        <div class="d-flex ai-center">
                            <input class="w-60 pd-5 bdr-all-22 br-5" type="number" id="${meta.qtyInputId}" value="${meta.offerMinQty}" min="${meta.offerMinQty}" ${meta.offerMaxAttr}>
                            <button type="button" class="bg-blue clr-white pd-5 br-5 mg-l-10 cursor-pointer" style="border: 0;" data-add-component-id="${component.id}" data-supplier-id="${meta.supplierId}" data-qty-id="${meta.qtyInputId}">Add</button>
                        </div>
                    </div>
                    <div class="fs-12 clr-grey1">${formatCurrency(meta.offerPrice, meta.offerCurrency)} | Min ${meta.offerMinQty} | Max ${meta.offerMaxQty ?? '-'}</div>
                </div>
            `
            : `
                <div class="pd-20 br-8 bdr-all-22 bg-white5">
                    <div class="d-flex jc-between ai-center mg-b-8">
                        <div class="fw-bold">${meta.supplierName}</div>
                        <div class="d-flex ai-center">
                            <input class="w-60 pd-5 bdr-all-22 br-5" type="number" id="${meta.qtyInputId}" value="${meta.offerMinQty}" min="${meta.offerMinQty}" ${meta.offerMaxAttr}>
                            <button type="button" class="bg-blue clr-white pd-5 br-5 mg-l-10 cursor-pointer" style="border: 0;" data-add-component-id="${component.id}" data-supplier-id="${meta.supplierId}" data-qty-id="${meta.qtyInputId}">Add</button>
                        </div>
                    </div>
                    <div class="fs-12 clr-grey1 mg-b-8">Code: ${meta.offerCode}</div>
                    <div class="fs-12 clr-grey1 mg-b-10">${meta.offerDescription}</div>
                    <div class="d-grid gap-10 fs-12" style="grid-template-columns: repeat(3, minmax(120px, 1fr));">
                        <div><span class="clr-grey1">Price:</span> ${formatCurrency(meta.offerPrice, meta.offerCurrency)}</div>
                        <div><span class="clr-grey1">Unit:</span> ${meta.offerUnit}</div>
                        <div><span class="clr-grey1">Min Qty:</span> ${meta.offerMinQty}</div>
                        <div><span class="clr-grey1">Max Qty:</span> ${meta.offerMaxQty ?? '-'}</div>
                        <div><span class="clr-grey1">Smart:</span> ${meta.offerSmart}</div>
                        <div><span class="clr-grey1">License Required:</span> ${meta.offerRequiresLicense}</div>
                        <div><span class="clr-grey1">License Type:</span> ${meta.offerLicenseType}</div>
                        <div><span class="clr-grey1">Subscription:</span> ${meta.offerSubscription}</div>
                    </div>
                </div>
            `;
    } else if (pricingComponentViewMode === 'compact') {
        supplierCardsMarkup = `
            <div class="pd-12 br-8 bdr-all-22 bg-white5">
                <div class="d-flex jc-between ai-center fs-12 mg-b-6">
                    <div class="fw-bold">Base Component</div>
                    <div class="d-flex ai-center">
                        <input class="w-60 pd-5 bdr-all-22 br-5" type="number" id="qty-${component.id}-base" value="${componentMinQty}" min="${componentMinQty}" ${baseMaxAttr}>
                        <button type="button" class="bg-blue clr-white pd-5 br-5 mg-l-10 cursor-pointer" style="border: 0;" data-add-component-id="${component.id}" data-supplier-id="" data-qty-id="qty-${component.id}-base">Add</button>
                    </div>
                </div>
                <div class="fs-12 clr-grey1">${formatCurrency(basePrice, currency)} | Min ${componentMinQty} | Max ${componentMaxQty ?? '-'}</div>
            </div>
        `;
    } else {
        supplierCardsMarkup = `
            <div class="pd-14 br-8 bdr-all-22 bg-white5">
                <div class="d-flex jc-between ai-center mg-b-8">
                    <div class="fw-bold">Base Component</div>
                    <div class="d-flex ai-center">
                        <input class="w-60 pd-5 bdr-all-22 br-5" type="number" id="qty-${component.id}-base" value="${componentMinQty}" min="${componentMinQty}" ${baseMaxAttr}>
                        <button type="button" class="bg-blue clr-white pd-5 br-5 mg-l-10 cursor-pointer" style="border: 0;" data-add-component-id="${component.id}" data-supplier-id="" data-qty-id="qty-${component.id}-base">Add</button>
                    </div>
                </div>
                <div class="fs-12 clr-grey1 mg-b-8">Code: ${componentCode}</div>
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
        `;
    }

    return `
        <div class="pd-10 bdr-bottom-22">
            <div class="fw-bold mg-b-8">${componentName}</div>
            <div class="d-grid gap-8">
                ${supplierCardsMarkup}
            </div>
        </div>
    `;
}

function bindAddComponentActions(container, component) {
    container.querySelectorAll('[data-add-component-id]').forEach((addButton) => {
        addButton.addEventListener('click', async () => {
            try {
                const currentProjectId = pricingProjectResolver?.();
                if (!currentProjectId) {
                    globalThis.show_popup_temp('error', 'Error', ['Please select a project first']);
                    return;
                }

                const qtyId = addButton.dataset.qtyId || '';
                const qtyInputElement = qtyId ? document.getElementById(qtyId) : null;
                const minQty = Number(qtyInputElement?.min || 1) || 1;
                const quantity = Math.max(minQty, qtyInputElement ? Number(qtyInputElement.value || minQty) : minQty);
                const selectedSupplierId = Number(addButton.dataset.supplierId || 0);

                await globalThis.api_request('/api/add-component', 'POST', {
                    project_id: currentProjectId,
                    component_id: component.id,
                    quantity,
                    supplier_id: selectedSupplierId || null,
                });

                await refreshSelectedProjectComponents(currentProjectId);
                globalThis.show_popup_temp('success', 'Success', ['Component added']);
            } catch (error) {
                console.error(error);
                globalThis.show_popup_temp('error', 'Error', ['Failed to add component']);
            }
        });
    });
}

function renderPricingComponentsList() {
    const list = document.getElementById('components');
    const pagination = document.getElementById('components-pagination');
    if (!list) {
        return;
    }

    const visibleEntries = sortByKey(getVisiblePricingEntries(), (entry) => `${entry?.component?.component_name || ''} ${entry?.offer?.supplier?.name || ''}`);
    const totalItems = visibleEntries.length;
    const totalPages = Math.max(1, Math.ceil(totalItems / pricingComponentState.perPage));

    if (pricingComponentState.currentPage > totalPages) {
        pricingComponentState.currentPage = totalPages;
    }

    const startIndex = (pricingComponentState.currentPage - 1) * pricingComponentState.perPage;
    const paginatedEntries = visibleEntries.slice(startIndex, startIndex + pricingComponentState.perPage);

    list.innerHTML = '';
    list.style.display = 'grid';
    list.style.rowGap = '2px';
    list.style.padding = '8px 12px 12px';
    list.style.boxSizing = 'border-box';
    renderPricingViewToggle(list);

    if (paginatedEntries.length === 0) {
        const emptyState = document.createElement('div');
        emptyState.className = 'pd-10 clr-grey1';
        emptyState.textContent = pricingComponentState.allComponents.length === 0
            ? 'No components available in this category.'
            : 'No components matched your search.';
        list.appendChild(emptyState);

        if (pagination) {
            updatePaginationSummary(0, 1);
        }
        return;
    }

    paginatedEntries.forEach(({ component, offer, offerIndex }) => {
        const card = document.createElement('div');
        card.innerHTML = buildPricingComponentMarkup(component, offer, offerIndex);
        bindAddComponentActions(card, component);
        list.appendChild(card);
    });

    if (pagination) {
        updatePaginationSummary(totalItems, totalPages);
    }
}

export async function loadPricingComponents(categoryId, getCurrentProjectId) {
    const resolvedCategoryId = categoryId === 'all'
        ? 'all'
        : (Number(categoryId || 0) || null);
    activePricingCategoryId = resolvedCategoryId;
    pricingProjectResolver = typeof getCurrentProjectId === 'function' ? getCurrentProjectId : null;

    const list = document.getElementById('components');
    if (!list) {
        return;
    }

    const pagination = document.getElementById('components-pagination');
    attachPricingComponentControls();

    if (!categoryId) {
        pricingComponentState.allComponents = [];
        pricingComponentState.currentPage = 1;
        list.innerHTML = '';
        if (pagination) {
            pagination.innerHTML = '';
        }
        return;
    }

    const componentsEndpoint = resolvedCategoryId === 'all'
        ? '/api/components/all'
        : `/api/components/${categoryId}`;

    const response = await globalThis.api_request(componentsEndpoint, 'GET');
    pricingComponentState.allComponents = Array.isArray(response?.data) ? response.data : [];
    pricingProjectResolver = typeof getCurrentProjectId === 'function' ? getCurrentProjectId : pricingProjectResolver;
    pricingComponentState.currentPage = 1;
    renderPricingComponentsList();
}
