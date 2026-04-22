@extends('layouts.app')

@section('content')
<div class="bg-white5 pd-15 bdr-bottom-22 mg-b-20">
    <div class="fs-15 fw-bold">Items</div>
</div>

<div class="d-grid gap-20 mg-b-20" style="grid-template-columns: 280px 1fr;">
    <button type="button" class="bg-blue clr-white pd-10 br-5 cursor-pointer" style="border: 0;" onclick="openAddCategoryModal()">+ Add Category</button>
    <button type="button" class="bg-blue clr-white pd-10 br-5 cursor-pointer" style="border: 0;" onclick="openAddComponentModal()">+ Add Item</button>
</div>

<div class="d-grid gap-20" style="grid-template-columns: 280px 1fr;">

    <div class="bg-white5 pd-20 br-10 box-shadow-basic h-mc">
        <div class="fw-bold mg-b-10">Categories</div>
        <div class="fs-12 clr-grey1 mg-b-10">Select a category to view all items.</div>
        <ul id="categories" class="list-style-none pd-0 mg-0"></ul>
    </div>

    <div class="bg-white5 pd-20 br-10 box-shadow-basic">
        <div class="fw-bold mg-b-10">Items</div>

        <div class="bg-white5 br-10 of-hidden">
            <div class="pd-10 bdr-bottom-22 fw-bold clr-grey1">
                Item Details
            </div>

            <div id="components" data-mode="items"></div>
        </div>
    </div>

</div>

<!-- Modal Overlay -->
<button type="button" id="modal-overlay" class="modal-overlay" onclick="closeAllModals()" aria-label="Close modal"></button>

<!-- Add/Edit Category Modal -->
<div id="category-modal" class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="category-modal-title">Add Category</h3>
            <button class="modal-close" onclick="closeAllModals()">×</button>
        </div>
        <div class="modal-body">
            <div class="d-grid gap-15">
                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="modal-category-name">Category Name *</label>
                    <input id="modal-category-name" class="pd-10 bdr-all-22 br-5 w-100" type="text" placeholder="Enter category name">
                </div>
                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="modal-category-icon">Icon (optional)</label>
                    <input id="modal-category-icon" class="pd-10 bdr-all-22 br-5 w-100" type="text" placeholder="e.g. 📦">
                </div>
                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="modal-category-description">Description (optional)</label>
                    <textarea id="modal-category-description" class="pd-10 bdr-all-22 br-5 w-100" placeholder="Enter description" rows="3" style="resize: vertical;"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeAllModals()">Cancel</button>
            <button id="category-modal-submit" class="btn-primary" onclick="submitCategoryModal()">Add</button>
        </div>
    </div>
</div>

<!-- Add/Edit Item Modal (Step-based) -->
<div id="component-modal" class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="component-modal-title">Add Item</h3>
            <button class="modal-close" onclick="closeAllModals()">×</button>
        </div>
        <div class="modal-body">
            <!-- Step 1: Category & Company Selection -->
            <div id="component-modal-step1" class="d-grid gap-15">
                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="modal-component-category-id">Category *</label>
                    <select id="modal-component-category-id" class="pd-10 bdr-all-22 br-5 w-100"></select>
                </div>
                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="modal-component-supplier-id">Select Company *</label>
                    <div class="d-flex ai-center gap-10">
                        <select id="modal-component-supplier-id" class="pd-10 bdr-all-22 br-5 w-100"></select>
                        <button type="button" class="btn-icon" title="Add New Company" onclick="openAddSupplierModal()"><i class="ri-add-circle-line"></i></button>
                    </div>
                </div>
            </div>

            <!-- Step 2: Item Details -->
            <div id="component-modal-step2" class="d-grid gap-15" style="display: none;">
                <div class="d-grid gap-30" style="grid-template-columns: 1fr 1fr;">
                    <div>
                        <label class="fs-12 fw-bold mg-b-5 d-block" for="modal-component-name">Item Name *</label>
                        <input id="modal-component-name" class="pd-10 bdr-all-22 br-5 w-100" type="text" placeholder="Name">
                    </div>
                    <div>
                        <label class="fs-12 fw-bold mg-b-5 d-block" for="modal-component-code">SKU *</label>
                        <input id="modal-component-code" class="pd-10 bdr-all-22 br-5 w-100" type="text" placeholder="SKU">
                    </div>
                </div>
                <div class="d-grid gap-30" style="grid-template-columns: 1fr 1fr;">
                    <div>
                        <label class="fs-12 fw-bold mg-b-5 d-block" for="modal-component-price">Price (RM) *</label>
                        <input id="modal-component-price" class="pd-10 bdr-all-22 br-5 w-100" type="number" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div>
                        <label class="fs-12 fw-bold mg-b-5 d-block" for="modal-component-unit">Unit</label>
                        <input id="modal-component-unit" class="pd-10 bdr-all-22 br-5 w-100" type="text" placeholder="e.g. pcs, m, hr">
                    </div>
                </div>
                <div class="d-grid gap-30" style="grid-template-columns: 1fr 1fr;">
                    <div>
                        <label class="fs-12 fw-bold mg-b-5 d-block" for="modal-component-min-qty">Min Quantity</label>
                        <input id="modal-component-min-qty" class="pd-10 bdr-all-22 br-5 w-100" type="number" min="1" placeholder="1">
                    </div>
                    <div>
                        <label class="fs-12 fw-bold mg-b-5 d-block" for="modal-component-max-qty">Max Quantity</label>
                        <input id="modal-component-max-qty" class="pd-10 bdr-all-22 br-5 w-100" type="number" min="1" placeholder="100">
                    </div>
                </div>
                <div class="d-grid gap-15" style="grid-template-columns: 1fr 1fr;">
                    <label class="d-flex ai-center pd-10 bdr-all-22 br-5 cursor-pointer">
                        <input id="modal-component-is-smart" type="checkbox" class="mg-r-8">
                        <span class="fs-12">Smart Item</span>
                    </label>
                    <label class="d-flex ai-center pd-10 bdr-all-22 br-5 cursor-pointer">
                        <input id="modal-component-requires-license" type="checkbox" class="mg-r-8">
                        <span class="fs-12">Requires License</span>
                    </label>
                </div>
                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="modal-component-license-type">License Type (optional)</label>
                    <input id="modal-component-license-type" class="pd-10 bdr-all-22 br-5 w-100" type="text" placeholder="License type">
                </div>
                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="modal-component-subscription-period">Subscription Period (optional)</label>
                    <input id="modal-component-subscription-period" class="pd-10 bdr-all-22 br-5 w-100" type="text" placeholder="e.g. monthly, yearly">
                </div>
                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="modal-component-description">Description (optional)</label>
                    <textarea id="modal-component-description" class="pd-10 bdr-all-22 br-5 w-100" placeholder="Enter description" rows="3" style="resize: vertical;"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <!-- Step 1 Footer -->
            <div id="component-modal-step1-footer">
                <button class="btn-secondary" onclick="closeAllModals()">Cancel</button>
                <button id="component-modal-step1-next" class="btn-primary" onclick="componentModalGoToStep2()">Next</button>
            </div>

            <!-- Step 2 Footer -->
            <div id="component-modal-step2-footer" style="display: none;">
                <button id="component-modal-step2-back" class="btn-secondary" onclick="handleComponentStep2SecondaryAction()">Back</button>
                <button id="component-modal-submit" class="btn-primary" onclick="submitComponentModal()">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Confirmation</h3>
            <button class="modal-close" onclick="closeAllModals()">×</button>
        </div>
        <div class="modal-body">
            <p id="delete-modal-message">Are you sure you want to delete this item?</p>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeAllModals()">Cancel</button>
            <button id="delete-modal-confirm" class="btn-danger">Delete</button>
        </div>
    </div>
</div>

<!-- Add New Supplier Modal -->
<div id="supplier-modal" class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Company</h3>
            <button class="modal-close" onclick="closeSupplierModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="d-grid gap-15">
                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="supplier-modal-name">Company Name *</label>
                    <input id="supplier-modal-name" class="pd-10 bdr-all-22 br-5 w-100" type="text" placeholder="Enter company name">
                </div>
                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="supplier-modal-address">Address (optional)</label>
                    <input id="supplier-modal-address" class="pd-10 bdr-all-22 br-5 w-100" type="text" placeholder="Enter company address">
                </div>
                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="supplier-modal-phone">Phone Number (optional)</label>
                    <input id="supplier-modal-phone" class="pd-10 bdr-all-22 br-5 w-100" type="text" placeholder="Enter company phone">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeSupplierModal()">Cancel</button>
            <button class="btn-primary" onclick="createSupplierFromComponentModal()">Save Company</button>
        </div>
    </div>
</div>

@endsection

