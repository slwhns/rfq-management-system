<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\ProfileController;
use App\Models\User;

const QUOTE_BY_ID_PATH = '/quotes/{id}';
const QUOTE_STATUS_BY_ID_PATH = '/quotes/{id}/status';

// Redirect root to dashboard directly - NO INDEX VIEW NEEDED
Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'role:superadmin,admin,staff'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', function () {
        $user = auth()->user();
        $role = $user instanceof User ? $user->normalizedRole() : User::ROLE_STAFF;
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

    Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get(QUOTE_BY_ID_PATH . '/edit', [QuoteController::class, 'edit'])->name('quotes.edit');
    Route::get(QUOTE_BY_ID_PATH, [QuoteController::class, 'show'])->name('quotes.show');
    Route::patch(QUOTE_BY_ID_PATH, [QuoteController::class, 'update'])->name('quotes.update');
    Route::patch(QUOTE_STATUS_BY_ID_PATH, [QuoteController::class, 'updateStatus'])->name('quotes.status.update');
    Route::middleware('role:superadmin,admin')->patch(QUOTE_BY_ID_PATH . '/admin-notes', [QuoteController::class, 'updateAdminNotes'])->name('quotes.admin-notes.update');
    Route::middleware('role:staff')->patch(QUOTE_BY_ID_PATH . '/staff-response', [QuoteController::class, 'updateStaffResponse'])->name('quotes.staff-response.update');
    Route::delete(QUOTE_BY_ID_PATH, [QuoteController::class, 'destroy'])->name('quotes.destroy');
    Route::post('/quotes/generate', [QuoteController::class, 'generate'])->name('quotes.generate');

    Route::get('/suppliers', [SupplierController::class, 'page'])->name('suppliers.index');
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::get('/purchase-orders/create/{quote}', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');

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
});

require __DIR__.'/auth.php';
