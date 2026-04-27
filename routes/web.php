<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\AdminQuoteReviewController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\ProfileController;
use App\Models\User;

const QUOTE_BY_ID_PATH = '/quotes/{id}';
const QUOTE_STATUS_BY_ID_PATH = '/quotes/{id}/status';
const RFQ_BY_ID_PATH = '/rfqs/{id}';
const RFQ_STATUS_BY_ID_PATH = '/rfqs/{id}/status';
const RFQ_SUBMIT_PATH = '/submit';

// Redirect root to dashboard directly - NO INDEX VIEW NEEDED
Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'role:superadmin,admin,client,staff'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', function () {
        $user = auth()->user();
        $role = $user instanceof User ? $user->normalizedRole() : User::ROLE_CLIENT;
        if (in_array($role, [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true) && view()->exists('admin.profile.index')) {
            return view('admin.profile.index');
        }

        if (view()->exists('staff.profile.index')) {
            return view('staff.profile.index');
        }

        abort(404, 'Profile view not found for role');
    })->name('profile.index');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{id}', [ProjectController::class, 'show'])->name('projects.show');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');

    Route::get('/pricing', [PricingController::class, 'index'])->name('pricing.index');

    // Preferred RFQ routes.
    Route::get('/rfqs', [QuoteController::class, 'index'])->name('rfqs.index');
    Route::get(RFQ_BY_ID_PATH . '/edit', [QuoteController::class, 'edit'])->name('rfqs.edit');
    Route::get(RFQ_BY_ID_PATH, [QuoteController::class, 'show'])->name('rfqs.show');
    Route::middleware('role:client,staff')->get(RFQ_BY_ID_PATH . '/quotation', function ($id) {
        return redirect()->route('rfqs.show', $id);
    })->name('rfqs.quotation.show');
    Route::post(RFQ_BY_ID_PATH . RFQ_SUBMIT_PATH, [QuoteController::class, 'submitRfq'])->name('rfqs.submit');
    Route::middleware('role:client,staff')->post(RFQ_BY_ID_PATH . '/reissue', [QuoteController::class, 'reissueRfq'])->name('rfqs.reissue');
    Route::get(RFQ_BY_ID_PATH . RFQ_SUBMIT_PATH, function ($id) {
        return redirect()->route('rfqs.show', $id);
    });
    Route::patch(RFQ_BY_ID_PATH, [QuoteController::class, 'update'])->name('rfqs.update');
    Route::patch(RFQ_STATUS_BY_ID_PATH, [QuoteController::class, 'updateStatus'])->name('rfqs.status.update');
    Route::middleware('role:superadmin,admin')->patch(RFQ_BY_ID_PATH . '/admin-notes', [QuoteController::class, 'updateAdminNotes'])->name('rfqs.admin-notes.update');
    Route::middleware('role:superadmin,admin')->post(RFQ_BY_ID_PATH . '/accept', [AdminQuoteReviewController::class, 'accept'])->name('rfqs.accept');
    Route::middleware('role:superadmin,admin')->post(RFQ_BY_ID_PATH . '/reject', [AdminQuoteReviewController::class, 'reject'])->name('rfqs.reject');
    Route::middleware('role:superadmin,admin')->post(RFQ_BY_ID_PATH . '/send-quotation', [QuoteController::class, 'sendQuotationToClient'])->name('rfqs.send-quotation');
    Route::middleware('role:client,staff')->patch(RFQ_BY_ID_PATH . '/client-response', [QuoteController::class, 'updateStaffResponse'])->name('rfqs.client-response.update');
    Route::middleware('role:client,staff')->post(RFQ_BY_ID_PATH . '/client-decision', [QuoteController::class, 'submitClientDecision'])->name('rfqs.client-decision');
    Route::delete(RFQ_BY_ID_PATH, [QuoteController::class, 'destroy'])->name('rfqs.destroy');
    Route::post('/rfqs/generate', [QuoteController::class, 'generate'])->name('rfqs.generate');

    // Legacy quote routes kept to avoid breaking existing bookmarks/integrations.
    Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get(QUOTE_BY_ID_PATH . '/edit', [QuoteController::class, 'edit'])->name('quotes.edit');
    Route::get(QUOTE_BY_ID_PATH, [QuoteController::class, 'show'])->name('quotes.show');
    Route::post(QUOTE_BY_ID_PATH . RFQ_SUBMIT_PATH, [QuoteController::class, 'submitRfq'])->name('quotes.submit');
    Route::middleware('role:client,staff')->post(QUOTE_BY_ID_PATH . '/reissue', [QuoteController::class, 'reissueRfq'])->name('quotes.reissue');
    Route::get(QUOTE_BY_ID_PATH . RFQ_SUBMIT_PATH, function ($id) {
        return redirect()->route('quotes.show', $id);
    });
    Route::patch(QUOTE_BY_ID_PATH, [QuoteController::class, 'update'])->name('quotes.update');
    Route::patch(QUOTE_STATUS_BY_ID_PATH, [QuoteController::class, 'updateStatus'])->name('quotes.status.update');
    Route::middleware('role:superadmin,admin')->patch(QUOTE_BY_ID_PATH . '/admin-notes', [QuoteController::class, 'updateAdminNotes'])->name('quotes.admin-notes.update');
    Route::middleware('role:superadmin,admin')->post(QUOTE_BY_ID_PATH . '/send-quotation', [QuoteController::class, 'sendQuotationToClient'])->name('quotes.send-quotation');
    Route::middleware('role:client,staff')->patch(QUOTE_BY_ID_PATH . '/staff-response', [QuoteController::class, 'updateStaffResponse'])->name('quotes.staff-response.update');
    Route::middleware('role:client,staff')->post(QUOTE_BY_ID_PATH . '/client-decision', [QuoteController::class, 'submitClientDecision'])->name('quotes.client-decision');
    Route::delete(QUOTE_BY_ID_PATH, [QuoteController::class, 'destroy'])->name('quotes.destroy');
    Route::post('/quotes/generate', [QuoteController::class, 'generate'])->name('quotes.generate');

    Route::middleware('role:superadmin,admin')->get('/suppliers', [SupplierController::class, 'page'])->name('suppliers.index');
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::post('/purchase-orders/create/{quote}', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');

    Route::middleware('role:superadmin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/staff', [AdminStaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [AdminStaffController::class, 'store'])->name('staff.store');
        Route::patch('/staff/{user}', [AdminStaffController::class, 'update'])->name('staff.update');
        Route::delete('/staff/{user}', [AdminStaffController::class, 'destroy'])->name('staff.destroy');
    });
});

// API Routes
Route::prefix('api')->group(function () {
    Route::get('/categories', [PricingController::class, 'categories']);
    Route::get('/components/{category}', [PricingController::class, 'components']);
    Route::post('/add-component', [PricingController::class, 'addComponent']);
    Route::get('/calculate/{projectId}', [PricingController::class, 'calculate']);
    Route::delete('/components/{id}', [PricingController::class, 'removeComponent']);

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::patch('/projects/{id}', [ProjectController::class, 'update']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
    Route::patch('/project-components/{id}', [ProjectController::class, 'updateComponent']);
    Route::delete('/project-components/{id}', [ProjectController::class, 'destroyComponent']);
});

require __DIR__.'/auth.php';
