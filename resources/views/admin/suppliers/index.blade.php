@extends('layouts.app')

@section('content')
<div class="bg-white5 pd-15 bdr-bottom-22 mg-b-20">
    <div class="fs-15 fw-bold mg-b-5">Suppliers</div>
    <div class="fs-12 clr-grey1">Follow the numbered steps below to manage your suppliers: 1. Add a company, 2. Create categories, and 3. Add item details.</div>
</div>

<div class="d-grid gap-20" style="grid-template-columns: 360px 1fr;">
    <div class="d-grid gap-20" style="grid-template-columns: 1fr; align-content: start;">
        <div class="bg-white5 pd-20 br-10 box-shadow-basic h-mc">
            <div class="d-flex jc-between ai-center mg-b-10">
                <div class="fw-bold d-flex ai-center"><span class="bg-blue clr-white d-flex jc-center ai-center mg-r-10" style="width: 22px; height: 22px; border-radius: 50%; font-size: 12px;">1</span> Supplier Companies</div>
                <button type="button" id="create-supplier-btn" class="bg-blue clr-white pd-8 br-5 cursor-pointer fs-12" style="border: 0;">+ Add Company</button>
            </div>
            <div class="fs-12 clr-grey1 mg-b-10">Select a company to view available items.</div>
            <div style="max-height: 320px; overflow-y: auto;">
                <ul id="supplier-list" class="br-5 of-hidden list-style-none pd-0 mg-0"></ul>
            </div>
        </div>

        <div class="bg-white5 pd-20 br-10 box-shadow-basic h-mc">
            <div class="d-flex jc-between ai-center mg-b-10">
                <div class="fw-bold d-flex ai-center"><span class="bg-blue clr-white d-flex jc-center ai-center mg-r-10" style="width: 22px; height: 22px; border-radius: 50%; font-size: 12px;">2</span> Categories</div>
                <button type="button" id="create-supplier-category-btn" class="bg-blue clr-white pd-8 br-5 cursor-pointer fs-12" style="border: 0;">+ Add Category</button>
            </div>
            <div class="fs-12 clr-grey1 mg-b-10">Use categories to filter and manage supplier items.</div>
            <div style="max-height: 320px; overflow-y: auto;">
                <ul id="supplier-category-list" class="br-5 of-hidden list-style-none pd-0 mg-0"></ul>
            </div>
        </div>
    </div>

    <div class="bg-white5 pd-20 br-10 box-shadow-basic">
        <div class="d-flex jc-between ai-center mg-b-10">
            <div class="fw-bold d-flex ai-center"><span class="bg-blue clr-white d-flex jc-center ai-center mg-r-10" style="width: 22px; height: 22px; border-radius: 50%; font-size: 12px;">3</span> Items List</div>
            <div class="d-flex ai-center gap-8">
                <button type="button" id="create-supplier-item-btn" class="bg-blue clr-white pd-8 br-5 cursor-pointer fs-12" style="border: 0;">+ Add Item</button>
            </div>
        </div>
        <div class="fs-12 clr-grey1 mg-b-10" id="supplier-items-description">Select a company to view all assigned items.</div>
        <div class="d-grid gap-25 mg-b-10" style="grid-template-columns: 1.2fr 1fr auto;">
            <input id="supplier-item-search" class="pd-10 bdr-all-22 br-20 w-100" type="text" placeholder="Search item by name, SKU, description, unit, price, subscription...">
            <select id="supplier-item-filter-category" class="pd-10 bdr-all-22 br-5 w-100">
                <option value="">All Categories</option>
            </select>
            <button type="button" id="supplier-item-clear-filters" class="bg-white3 clr-black1 pd-10 br-5 cursor-pointer fs-12" style="border: 1px solid #d8d8d8;">Clear</button>
        </div>
        <div id="supplier-items-container" class="br-5 of-hidden"></div>
        <div id="supplier-items-pagination" class="d-flex jc-end ai-center gap-10 mg-t-10"></div>
    </div>
</div>

<!-- Supplier Form Modal -->
<button type="button" id="supplier-form-overlay" class="modal-overlay" aria-label="Close modal"></button>

<div id="supplier-form-modal" class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="supplier-form-title">Add Company</h3>
            <button type="button" class="modal-close" id="supplier-form-close">×</button>
        </div>
        <div class="modal-body">
            <div class="d-grid gap-15">
                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="supplier-form-name">Company Name *</label>
                    <input id="supplier-form-name" class="pd-10 bdr-all-22 br-5 w-100" type="text" placeholder="Enter company name">
                </div>
                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="supplier-form-phone">Phone Number (optional)</label>
                    <input id="supplier-form-phone" class="pd-10 bdr-all-22 br-5 w-100" type="text" placeholder="Enter phone number">
                </div>
                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="supplier-form-address">Address (optional)</label>
                    <input id="supplier-form-address" class="pd-10 bdr-all-22 br-5 w-100" type="text" placeholder="Enter address">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" id="supplier-form-cancel">Cancel</button>
            <button type="button" class="btn-primary" id="supplier-form-submit">Save</button>
        </div>
    </div>
</div>

<!-- Supplier Item Form Modal -->
<button type="button" id="supplier-item-form-overlay" class="modal-overlay" aria-label="Close modal"></button>

<div id="supplier-item-form-modal" class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="supplier-item-form-title">Add Item</h3>
            <button type="button" class="modal-close" id="supplier-item-form-close">×</button>
        </div>
        <div class="modal-body">
            <div class="d-grid gap-15">
                <div id="supplier-item-readonly" class="pd-10 br-5 bg-white5 fs-12" style="display: none;"></div>

                <div id="supplier-item-category-group">
                    <div class="d-flex jc-between ai-center mg-b-5">
                        <label class="fs-12 fw-bold d-block" for="supplier-item-category-id">Category *</label>
                        <div class="d-flex ai-center gap-5">
                            <button type="button" id="supplier-item-edit-category-btn" class="btn-icon" title="Edit Category"><i class="ri-edit-box-line"></i></button>
                            <button type="button" id="supplier-item-create-category-btn" class="btn-icon" title="Create New Category"><i class="ri-add-circle-line"></i></button>
                        </div>
                    </div>
                    <select id="supplier-item-category-id" class="pd-10 bdr-all-22 br-5 w-100"></select>
                </div>

                <div id="supplier-item-component-group">
                    <div class="d-flex jc-between ai-center mg-b-5">
                        <label class="fs-12 fw-bold d-block" for="supplier-item-component-id">Item *</label>
                        <button type="button" id="supplier-item-edit-component-btn" class="btn-icon" title="Edit Item"><i class="ri-edit-box-line"></i></button>
                    </div>
                    <select id="supplier-item-component-id" class="pd-10 bdr-all-22 br-5 w-100"></select>
                    <div class="fs-12 clr-grey1 mg-t-5">If item is not listed, fill SKU and Name below then click New Item.</div>
                </div>

                <div class="d-grid gap-30" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                    <div>
                        <div class="d-flex jc-between ai-center mg-b-5">
                            <label class="fs-12 fw-bold d-block" for="supplier-item-code">SKU</label>
                            <button type="button" class="btn-icon" aria-hidden="true" tabindex="-1" style="visibility: hidden;"><i class="ri-add-circle-line"></i></button>
                        </div>
                        <input id="supplier-item-code" class="pd-10 bdr-all-22 br-5 w-100" type="text" maxlength="100" placeholder="Enter SKU">
                    </div>
                    <div>
                        <div class="d-flex jc-between ai-center mg-b-5">
                            <label class="fs-12 fw-bold d-block" for="supplier-item-name">Item Name</label>
                            <button type="button" id="supplier-item-create-component-btn" class="btn-icon" title="Create New Item"><i class="ri-add-circle-line"></i></button>
                        </div>
                        <input id="supplier-item-name" class="pd-10 bdr-all-22 br-5 w-100" type="text" maxlength="255" placeholder="Enter item name">
                    </div>
                </div>

                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="supplier-item-price">Price (RM) *</label>
                    <input id="supplier-item-price" class="pd-10 bdr-all-22 br-5 w-100" type="number" min="0" step="0.01" placeholder="Enter item price">
                </div>

                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="supplier-item-currency">Currency</label>
                    <input id="supplier-item-currency" class="pd-10 bdr-all-22 br-5 w-100" type="text" maxlength="10" placeholder="RM">
                </div>

                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="supplier-item-description">Description</label>
                    <textarea id="supplier-item-description" class="pd-10 bdr-all-22 br-5 w-100" rows="3" placeholder="Description"></textarea>
                </div>

                <div class="d-grid gap-30" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                    <div>
                        <label class="fs-12 fw-bold mg-b-5 d-block" for="supplier-item-unit">Unit</label>
                        <input id="supplier-item-unit" class="pd-10 bdr-all-22 br-5 w-100" type="text" maxlength="50" placeholder="Unit">
                    </div>
                    <div>
                        <label class="fs-12 fw-bold mg-b-5 d-block" for="supplier-item-license-type">License Type</label>
                        <input id="supplier-item-license-type" class="pd-10 bdr-all-22 br-5 w-100" type="text" maxlength="100" placeholder="License type">
                    </div>
                </div>

                <div class="d-grid gap-30" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                    <div>
                        <label class="fs-12 fw-bold mg-b-5 d-block" for="supplier-item-min-qty">Min Qty</label>
                        <input id="supplier-item-min-qty" class="pd-10 bdr-all-22 br-5 w-100" type="number" min="1" step="1" placeholder="Minimum quantity">
                    </div>
                    <div>
                        <label class="fs-12 fw-bold mg-b-5 d-block" for="supplier-item-max-qty">Max Qty</label>
                        <input id="supplier-item-max-qty" class="pd-10 bdr-all-22 br-5 w-100" type="number" min="1" step="1" placeholder="Maximum quantity">
                    </div>
                </div>

                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="supplier-item-subscription">Subscription Period</label>
                    <input id="supplier-item-subscription" class="pd-10 bdr-all-22 br-5 w-100" type="text" maxlength="100" placeholder="Monthly / Yearly / etc.">
                </div>

                <div class="d-grid gap-10" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                    <label class="d-flex ai-center gap-8 fs-12">
                        <input id="supplier-item-is-smart" type="checkbox">
                        Smart Item
                    </label>
                    <label class="d-flex ai-center gap-8 fs-12">
                        <input id="supplier-item-requires-license" type="checkbox">
                        Requires License
                    </label>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" id="supplier-item-form-cancel">Cancel</button>
            <button type="button" class="btn-primary" id="supplier-item-form-submit">Save</button>
        </div>
    </div>
</div>

<!-- Mini Category Modal -->
<button type="button" id="supplier-category-mini-overlay" class="modal-overlay" aria-label="Close modal"></button>

<div id="supplier-category-mini-modal" class="modal-dialog" style="max-width: 680px;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="supplier-category-mini-title">Add New Category</h3>
            <button type="button" class="modal-close" id="supplier-category-mini-close">×</button>
        </div>
        <div class="modal-body">
            <div class="d-grid gap-15" style="padding-right: 25px;">
                <div class="fs-12 clr-grey1">Create a category that can be reused across items and pricing pages.</div>
                <div class="d-grid gap-30" style="grid-template-columns: minmax(0, 1fr) 180px;">
                    <div>
                        <label class="fs-12 fw-bold mg-b-5 d-block" for="supplier-category-mini-name">Category Name *</label>
                        <input id="supplier-category-mini-name" class="pd-10 bdr-all-22 br-5 w-100" type="text" maxlength="255" placeholder="Enter category name">
                    </div>
                    <div>
                        <label class="fs-12 fw-bold mg-b-5 d-block" for="supplier-category-mini-icon">Category Icon (optional)</label>
                        <input id="supplier-category-mini-icon" class="pd-10 bdr-all-22 br-5 w-100" type="text" maxlength="10" placeholder="e.g. 📦 or ⚡">
                    </div>
                </div>
                <div>
                    <label class="fs-12 fw-bold mg-b-5 d-block" for="supplier-category-mini-description">Category Description (optional)</label>
                    <textarea id="supplier-category-mini-description" class="pd-10 bdr-all-22 br-5 w-100" rows="4" maxlength="255" placeholder="Describe this category"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" id="supplier-category-mini-cancel">Cancel</button>
            <button type="button" class="btn-primary" id="supplier-category-mini-submit">Save Category</button>
        </div>
    </div>
</div>

@endsection

