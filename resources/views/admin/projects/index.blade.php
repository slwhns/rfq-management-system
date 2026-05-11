@extends('layouts.app')

@section('content')

{{-- Page Title --}}
<div class="dash-title-wrap mg-b-20">
    <div class="d-flex fd-column ai-center jc-center gap-8 txt-center">
        <div class="d-flex ai-center gap-10 jc-center">
            <span class="dash-greeting-emoji">◎</span>
            <div class="dash-greeting-text">Projects & Pricing</div>
        </div>
        <div class="dash-greeting-sub">Manage and configure projects, items, and generate RFQs</div>
    </div>
</div>

{{-- Top Row: Project Select + Pricing Summary --}}
<div class="d-grid gap-20 mg-b-20" style="grid-template-columns: 1.2fr 1fr;">

    {{-- Project Selector --}}
    <div class="dash-table-card">
        <div class="dash-table-header">
            <div class="dash-table-title">Select Project</div>
            <button type="button" class="proj-text-btn" onclick="openProjectManagerModal()">
                Manage Projects
            </button>
        </div>
        <select id="project-select" class="rfq-filter-input w-100 mg-b-10">
            <option value="">Loading projects...</option>
        </select>
        <div id="pricing-selected-project-name" class="fs-12 clr-plt2">No project selected</div>
    </div>

    {{-- Pricing Summary --}}
    <div class="dash-table-card">
        <div class="dash-table-header">
            <div class="dash-table-title">Pricing Summary</div>
        </div>
        <div class="proj-summary-row">
            <span class="proj-summary-label">Subtotal</span>
            <span id="subtotal" class="proj-summary-value">RM0</span>
        </div>
        <div class="proj-summary-row">
            <span class="proj-summary-label">Tax</span>
            <span id="tax" class="proj-summary-value">RM0</span>
        </div>
        <div class="proj-summary-row proj-summary-total">
            <span class="proj-summary-label">Total</span>
            <span id="total" class="proj-summary-value">RM0</span>
        </div>
        <div class="d-flex gap-10 mg-t-16">
            <button type="button" class="rfq-filter-btn-apply" onclick="calculatePrice()">
                Calculate
            </button>
            <button type="button" class="proj-btn-generate" onclick="generateQuote()">
                Generate RFQ
            </button>
        </div>
    </div>

</div>

{{-- Bottom Row: Item Details + Selected Items --}}
<div class="d-grid gap-20 mg-b-20" style="grid-template-columns: minmax(0, 1.45fr) minmax(320px, 0.95fr); align-items: start;">

    {{-- Item Catalog --}}
    <div class="dash-table-card">
        <div class="dash-table-title mg-b-12">Item Details</div>

        <div class="d-grid gap-10 mg-b-12" style="grid-template-columns: minmax(0, 1fr) 220px 110px; align-items: center;">
            <input id="component-search" class="rfq-filter-input" type="text"
                placeholder="Search by name, SKU, description, supplier">
            <select id="component-filter" class="rfq-filter-input">
                <option value="">Loading categories...</option>
            </select>
            <button id="component-clear" type="button" class="rfq-filter-btn-reset">
                Clear
            </button>
        </div>

        <div id="components" data-mode="pricing"></div>
        <div id="components-pagination" class="d-flex jc-between ai-center pd-10 fs-12" style="color: var(--plt2);"></div>
    </div>

    {{-- Selected Items --}}
    <div class="dash-table-card h-mc">
        <div class="d-flex jc-between ai-center mg-b-10">
            <div class="dash-table-title">Selected Items</div>
            <div class="fs-12" style="color: var(--plt2);">By selected project</div>
        </div>
        <div id="pricing-selected-components" class="br-10 of-hidden"></div>
    </div>

</div>

{{-- Manage Projects Modal --}}
<div id="project-manager-modal" class="modal-dialog" style="max-width: 760px;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Manage Projects</h3>
            <button class="modal-close" onclick="closeAllModals()">×</button>
        </div>
        <div class="modal-body">
            <div id="project-manager-list" class="pd-10"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-primary" onclick="openAddProjectModal()">+ Add Project</button>
            <button class="btn-secondary" onclick="closeAllModals()">Close</button>
        </div>
    </div>
</div>

{{-- Modal Overlay --}}
<button type="button" id="modal-overlay" class="modal-overlay" onclick="closeAllModals()" aria-label="Close modal"></button>

{{-- Add / Edit Project Modal --}}
<div id="project-modal" class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="project-modal-title">Add Project</h3>
            <button class="modal-close" onclick="closeAllModals()">×</button>
        </div>
        <div class="modal-body">
            <div class="d-grid gap-15">
                <div>
                    <label class="modal-field-label" for="modal-project-name">Project Name *</label>
                    <input id="modal-project-name" class="modal-field-input pd-10 br-5 w-100" type="text" placeholder="e.g. Smart Campus DC Upgrade">
                </div>
                <div>
                    <label class="modal-field-label" for="modal-project-title">Project Title *</label>
                    <input id="modal-project-title" class="modal-field-input pd-10 br-5 w-100" type="text" placeholder="e.g. Smart Data Center Upgrade Phase 2">
                </div>
                <div>
                    <label class="modal-field-label" for="modal-project-location">Location</label>
                    <input id="modal-project-location" class="modal-field-input pd-10 br-5 w-100" type="text" placeholder="e.g. Kuala Lumpur">
                </div>
                <div>
                    <label class="modal-field-label" for="modal-project-type">Project Type *</label>
                    <select id="modal-project-type" class="modal-field-input pd-10 br-5 w-100">
                        <option value="new">New</option>
                        <option value="retrofit">Retrofit</option>
                        <option value="expansion">Expansion</option>
                    </select>
                </div>
                <div>
                    <label class="modal-field-label" for="modal-project-tax-rate">Tax Rate (%)</label>
                    <input id="modal-project-tax-rate" class="modal-field-input pd-10 br-5 w-100" type="number" min="0" max="100" step="0.01" value="10.00" placeholder="e.g. 10">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeAllModals()">Cancel</button>
            <button id="project-modal-submit" class="btn-primary" onclick="submitProjectModal()">Add</button>
        </div>
    </div>
</div>

{{-- Edit Project Component Modal --}}
<div id="project-component-modal" class="modal-dialog" style="max-width: 520px;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Project Item</h3>
            <button class="modal-close" onclick="closeAllModals()">×</button>
        </div>
        <div class="modal-body">
            <div class="d-grid gap-15">
                <div class="proj-item-info-card">
                    <div class="fw-bold" id="project-component-modal-name">-</div>
                    <div class="fs-12" style="color: var(--plt2);">SKU: <span id="project-component-modal-code">-</span></div>
                </div>
                <div>
                    <label class="modal-field-label" for="project-component-modal-quantity">Quantity *</label>
                    <input id="project-component-modal-quantity" class="modal-field-input pd-10 br-5 w-100" type="number" min="1" step="1" placeholder="Enter quantity">
                </div>
                <div>
                    <label class="modal-field-label" for="project-component-modal-discount">Discount Level</label>
                    <select id="project-component-modal-discount" class="modal-field-input pd-10 br-5 w-100">
                        <option value="0">No Discount</option>
                        <option value="5">5%</option>
                        <option value="10">10%</option>
                        <option value="15">15%</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeAllModals()">Cancel</button>
            <button id="project-component-modal-submit" class="btn-primary">Update</button>
        </div>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div id="delete-modal" class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Confirmation</h3>
            <button class="modal-close" onclick="closeAllModals()">×</button>
        </div>
        <div class="modal-body">
            <p id="delete-modal-message" style="color: var(--white2);">Are you sure you want to delete this project?</p>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeAllModals()">Cancel</button>
            <button id="delete-modal-confirm" class="btn-danger">Delete</button>
        </div>
    </div>
</div>

@endsection
