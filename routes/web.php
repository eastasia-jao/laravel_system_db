<?php

use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HubController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RetailController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\StockAllocationController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\FullyBookedOrderController;
use App\Http\Controllers\StaffActivityLogController;
use App\Http\Controllers\UnitTypeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// 1. Public Routes
Route::get('/', function () {
    return redirect()->route('login');
});

require __DIR__.'/auth.php';

// 2. Protected Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/product-catalog', [\App\Http\Controllers\CatalogController::class, 'index'])->middleware('can:manage-shared-catalog')->name('catalog.index');
    Route::post('/product-catalog/assign', [\App\Http\Controllers\CatalogController::class, 'assign'])->middleware('can:manage-shared-catalog')->name('catalog.assign');
    Route::get('/sales/order-number-preview', [\App\Http\Controllers\SalesController::class, 'previewOrderNumber'])->middleware('can:access-sales')->name('sales.order-number-preview');
    Route::get('/product-file-requests', [\App\Http\Controllers\ProductFileRequestController::class, 'index'])->middleware('can:view-products')->name('product-file-requests.index');
    // Legacy detail routes remain available for previously created records.
    // New Admin/Inventory Staff imports and exports do not use an approval flow.
    Route::get('/product-file-requests/{fileRequest}', [\App\Http\Controllers\ProductFileRequestController::class, 'show'])->middleware('can:view-products')->name('product-file-requests.show');
    Route::post('/product-file-requests/{fileRequest}/review', [\App\Http\Controllers\ProductFileRequestController::class, 'review'])->middleware('can:verify-inventory')->name('product-file-requests.review');
    Route::get('/product-file-requests/{fileRequest}/download', [\App\Http\Controllers\ProductFileRequestController::class, 'download'])->middleware('can:view-products')->name('product-file-requests.download');
    Route::get('/product-file-requests/{fileRequest}/download-csv', [\App\Http\Controllers\ProductFileRequestController::class, 'downloadCsv'])->middleware('can:view-products')->name('product-file-requests.download-csv');
    Route::post('/notifications/read-all', function () {
        auth()->user()->unreadNotifications->markAsRead();

        return back();
    })->name('notifications.read-all');

    Route::get('/notifications', function () {
        $notifications = auth()->user()->notifications()->latest()->paginate(20);
        $notificationHubIds = $notifications->getCollection()
            ->pluck('data.hub_id')
            ->filter()
            ->unique()
            ->values();
        $notificationHubs = \App\Models\StoreHub::whereIn('id', $notificationHubIds)->get()->keyBy('id');

        return view('notifications.index', compact('notifications', 'notificationHubs'));
    })->name('notifications.index');

    Route::get('/notifications/feed', function () {
        $notifications = auth()->user()->notifications()->latest()->limit(10)->get();
        $notificationHubIds = $notifications->pluck('data.hub_id')->filter()->unique()->values();
        $notificationHubs = \App\Models\StoreHub::whereIn('id', $notificationHubIds)->get()->keyBy('id');

        return response()->json([
            'unread_count' => auth()->user()->unreadNotifications()->count(),
            'html' => view('layouts.notification-feed', compact('notifications', 'notificationHubs'))->render(),
        ]);
    })->name('notifications.feed');

    Route::get('/notifications/{notification}/read', function (string $notification) {
        $record = auth()->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        if (auth()->user()?->role === 'sales_associate'
            && in_array($record->data['event'] ?? null, ['branch_transfer_approved', 'branch_transfer_rejected'], true)) {
            $reference = $record->data['reference'] ?? null;
            if (! $reference && preg_match('/Transfer Document ([^ ]+)/', $record->data['message'] ?? '', $matches)) {
                $reference = $matches[1];
            }
            $hasVisibleTransferLog = $reference && \App\Models\StaffActivityLog::where('action_type', 'branch_transfer_sent')
                ->whereIn('store_hub_id', auth()->user()->accessibleStoreHubIds())
                ->where('description', 'like', '%'.$reference.'%')
                ->exists();

            if ($hasVisibleTransferLog) {
                return redirect()->route('staff-logs.index', ['search' => $reference]);
            }
        }

        $destination = $record->data['url'] ?? route('dashboard');
        if (! in_array(auth()->user()?->role, ['admin', 'inventory_staff'], true)
            && str_contains($destination, '/pending-sales')) {
            return redirect()->route('dashboard')->with('notification_error',
                'Access restricted. The Inventory Verification Queue is only available to inventory staff and admins. You can read the verification update in your notifications.');
        }

        return redirect()->to($destination);
    })->name('notifications.read');

    // Dashboard
    Route::get('/dashboard', [HubController::class, 'index'])->middleware('can:access-dashboard')->name('dashboard');

    // Configuration Panel & Master Data (admin only)
    Route::middleware(['can:manage-master-data'])->group(function () {
        Route::get('/configuration', [ConfigurationController::class, 'index'])->name('configuration');

        // Store Hub Routes
        Route::prefix('storehub')->name('storehub.')->group(function () {
            Route::post('/', [ConfigurationController::class, 'storeHub'])->name('storeHub');
            Route::put('/{id}', [ConfigurationController::class, 'updateHub'])->name('update');
            Route::patch('/{id}/toggle', [ConfigurationController::class, 'toggleHubStatus'])->name('toggle-status');
            Route::delete('/{id}', [ConfigurationController::class, 'destroyHub'])->name('destroy');
        });
        Route::post('/configuration/store-hub', [ConfigurationController::class, 'storeHub'])->name('units.storeHub');

        // Unit Type Routes
        Route::prefix('unittypes')->name('unittypes.')->group(function () {
            Route::post('/', [UnitTypeController::class, 'store'])->name('store');
            Route::patch('/{id}/toggle-status', [UnitTypeController::class, 'toggleStatus'])->name('toggle-status');
            Route::delete('/{id}', [UnitTypeController::class, 'destroy'])->name('destroy');
        });
        Route::post('/unit-types', [UnitTypeController::class, 'store']);

        // Retail Department & Group Routes
        Route::prefix('retail')->name('retail.')->group(function () {
            Route::post('/department/store', [RetailController::class, 'storeDept'])->name('storeDept');
            Route::post('/group/store', [RetailController::class, 'storeGroup'])->name('storeGroup');
        });

        // Brands & Configuration Deletions
        Route::post('/brands', [ConfigurationController::class, 'storeBrand'])->name('brands.store');
        Route::delete('/brands/{id}', [ConfigurationController::class, 'destroyBrand'])->name('brands.destroy');
        Route::delete('/configuration/departments/{id}', [ConfigurationController::class, 'destroyDepartment'])->name('department.destroy');
        Route::delete('/configuration/groups/{id}', [ConfigurationController::class, 'destroyGroup'])->name('group.destroy');

    });

    Route::middleware(['can:full-access'])->group(function () {
        Route::resource('users', UserController::class)->except(['create', 'show']);
        Route::put('/users/{id}/toggle', [UserController::class, 'toggleStatus'])->name('users.toggleStatus');
        Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    });

    // Product viewing (admin + inventory staff + sales staff)
    Route::middleware(['can:view-inventory'])->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->middleware('can:view-products')->name('products.index');
        Route::get('/stock-allocation', [StockAllocationController::class, 'index'])->middleware('can:manage-stock-allocation')->name('stock-allocation.index');
        Route::get('/inventory-transactions', [InventoryTransactionController::class, 'index'])
            ->middleware('can:view-transaction-logs')
            ->name('inventory-transactions.index');
        Route::get('/inventory-transactions/fully-booked', [FullyBookedOrderController::class, 'index'])
            ->middleware('can:view-fully-booked-orders')
            ->name('inventory-transactions.fully-booked.index');
        Route::get('/inventory-transactions/fully-booked/{fullyBookedOrder}/attachment', [FullyBookedOrderController::class, 'attachment'])
            ->middleware('can:view-fully-booked-orders')
            ->name('inventory-transactions.fully-booked.attachment');
        Route::patch('/inventory-transactions/fully-booked/{fullyBookedOrder}/review', [FullyBookedOrderController::class, 'review'])
            ->middleware('can:manage-fully-booked-orders')
            ->name('inventory-transactions.fully-booked.review');
        Route::post('/inventory-transactions/fully-booked/{fullyBookedOrder}/pull-out', [FullyBookedOrderController::class, 'pullOut'])
            ->middleware('can:manage-fully-booked-orders')
            ->name('inventory-transactions.fully-booked.pull-out');
    });

    // Product creating/editing/deleting inventory (admin + inventory staff)
    Route::middleware(['can:manage-inventory'])->group(function () {
        Route::prefix('products')->name('products.')->group(function () {
            Route::post('/bulk-destroy', [ProductController::class, 'bulkDestroy'])->middleware('can:full-access')->name('bulk-destroy');
            Route::patch('/{id}/toggle', [ProductController::class, 'toggleStatus'])->name('toggle');
        });
        Route::resource('products', ProductController::class)->only(['update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy'])->middleware('can:full-access')->name('products.destroy');
        Route::post('/inventory/add-stock', [ProductController::class, 'addStock'])->name('inventory.addStock');
        Route::get('/inventory-transactions/transfer/create/{type?}', [InventoryTransactionController::class, 'create'])->defaults('type', 'stock_transfer')->name('inventory-transactions.transfer.create');
        Route::get('/inventory-transactions/create', fn () => redirect()->route('inventory-transactions.transfer.create'))->name('inventory-transactions.create');
        Route::get('/inventory-transactions/restock/create', [InventoryTransactionController::class, 'create'])->defaults('type', 'restock')->name('inventory-transactions.restock.create');
        Route::post('/inventory-transactions', [InventoryTransactionController::class, 'store'])->name('inventory-transactions.store');
    });

    Route::post('/stock-allocation', [StockAllocationController::class, 'update'])
        ->middleware('can:manage-stock-allocation')
        ->name('stock-allocation.update');

    Route::get('/inventory-transactions/sponsor-workshop/create', [InventoryTransactionController::class, 'create'])
        ->middleware(['can:view-inventory', 'can:view-sponsor-workshop'])
        ->defaults('type', 'sponsor_workshop')
        ->name('inventory-transactions.sponsor.create');
    Route::post('/fully-booked-orders', [FullyBookedOrderController::class, 'store'])
        ->middleware(['can:view-inventory', 'can:request-fully-booked-orders'])
        ->name('fully-booked-orders.store');

    Route::middleware(['can:manage-branch-returns'])->group(function () {
        Route::get('/inventory-transactions/return/create', [InventoryTransactionController::class, 'create'])->defaults('type', 'return')->name('inventory-transactions.return.create');
        Route::get('/inventory-transactions/return/sales', [InventoryTransactionController::class, 'returnSalesLookup'])->name('inventory-transactions.return.sales');
        Route::post('/inventory-transactions/return', [InventoryTransactionController::class, 'store'])->name('inventory-transactions.return.store');
    });

    Route::get('/inventory-transactions/product-worksheet', [InventoryTransactionController::class, 'downloadProductWorksheet'])
        ->middleware('can:manage-branch-transfers')
        ->name('inventory-transactions.product-worksheet');

    Route::middleware(['can:submit-branch-transfers'])->group(function () {
        Route::get('/inventory-transactions/branch-transfer/create', [InventoryTransactionController::class, 'create'])
            ->defaults('type', 'branch_transfer')
            ->name('inventory-transactions.branch-transfer.create');
        Route::post('/inventory-transactions/branch-transfer', [InventoryTransactionController::class, 'store'])
            ->name('inventory-transactions.branch-transfer.store');
    });

    Route::post('/inventory-transactions/branch-transfer/{batch}/review', [InventoryTransactionController::class, 'reviewBranchTransfer'])
        ->middleware('can:review-branch-transfers')
        ->name('inventory-transactions.branch-transfer.review');

    // Hub Specific Routes (Protected securely via hub.access middleware)
    Route::middleware(['hub.access', 'can:access-sales'])->group(function () {
        Route::prefix('hub/{hub}')->name('hub.')->group(function () {
            Route::get('/products/export', [ProductController::class, 'export'])->middleware('can:manage-inventory')->name('products.export');
            Route::post('/products/import', [ProductController::class, 'importCsv'])->middleware('can:manage-inventory')->name('products.import');
            Route::get('/report', [SalesReportController::class, 'report'])->middleware('can:view-sales-reports')->name('report');
            Route::patch('/report/tiktok/{transaction}', [SalesReportController::class, 'updateTikTokFields'])->middleware('can:view-sales-reports')->name('report.tiktok.update');
            Route::patch('/report/tiktok/{transaction}/returns/{item}', [SalesReportController::class, 'updateTikTokReturn'])->middleware('can:view-sales-reports')->name('report.tiktok.return.update');
            Route::post('/report/tiktok/{transaction}/replacements/{item}', [SalesReportController::class, 'replaceWholesaleItem'])->middleware('can:manage-sales-status')->name('report.tiktok.replacement.store');
            Route::post('/report/online/{transaction}/replacements/{item}', [SalesReportController::class, 'replaceWholesaleItem'])->middleware('can:manage-sales-status')->name('report.online.replacement.store');
            Route::post('/report/walk-in/{transaction}/replacements/{item}', [SalesReportController::class, 'replaceWholesaleItem'])->middleware('can:request-walk-in-replacements')->name('report.walk-in.replacement.store');
            Route::post('/report/walk-in/{transaction}/replacement-payment', [SalesReportController::class, 'recordWalkInReplacementPayment'])->middleware('can:manage-sales-status')->name('report.walk-in.replacement-payment.store');
            Route::post('/report/wholesale/{transaction}/items/{item}/replace', [SalesReportController::class, 'replaceWholesaleItem'])->middleware('can:manage-sales-status')->name('report.wholesale.replace');
            Route::get('/wholesale-report', [SalesReportController::class, 'wholesaleReport'])->middleware('can:view-wholesale-reports')->name('wholesale.report');
        });

        Route::get('/hub/{id}/dashboard', [DashboardController::class, 'dashboard'])->name('hub.dashboard');
        Route::get('/hub/{hubId}/products/search-ajax', [ProductController::class, 'searchAjax'])->name('hub.products.search.ajax');
        Route::get('/hub/{hubId}/pending-sales', [SalesController::class, 'pendingSalesIndex'])->name('hub.sales.pending');

        Route::get('/products/create/{hub_id}', [ProductController::class, 'create'])->name('products.create.hub');
        Route::post('/products/{hub_id}/store', [ProductController::class, 'store'])->middleware('can:manage-inventory')->name('products.store.hub');
    });

    Route::middleware(['can:view-rejected-sales'])->group(function () {
        Route::get('/my-rejected-sales', [SalesController::class, 'rejectedSalesIndex'])->name('sales.rejected');
        Route::get('/my-rejected-sales/{id}/order-slip', [SalesController::class, 'rejectedOrderSlip'])->name('sales.rejected-order-slip');
    });

    Route::middleware(['can:record-channel-sales'])->group(function () {
        Route::post('/inventory/record-sale', [SalesController::class, 'storeMultiChannelSale'])->name('sales.record');
        Route::post('/sales', [SalesController::class, 'store'])->name('sales.store');
        Route::post('/multichannel-sales/store', [SalesController::class, 'storeMultiChannelSale'])->name('sales.storeMultiChannelSale');
        Route::post('/multichannel-sales/store-alt', [SalesController::class, 'storeMultiChannelSale'])->name('multichannel.sales.store');
        Route::get('/hub/{hubId}/sales/customers', [SalesController::class, 'customerLookup'])->name('hub.sales.customers');
    });

    Route::middleware(['can:verify-inventory'])->group(function () {
        Route::get('/pending-sales', [SalesController::class, 'pendingSalesIndex'])->name('sales.pending');
        Route::get('/pending-sales/{id}/order-slip', [SalesController::class, 'orderSlip'])->name('sales.order-slip');
        Route::post('/pending-sales/{id}/confirm', [SalesController::class, 'confirmPendingSale'])->name('sales.confirmPending');
        Route::post('/pending-sales/{id}/reject', [SalesController::class, 'rejectPendingSale'])->name('sales.rejectPending');
        Route::post('/wholesale-replacements/{replacement}/approve', [SalesReportController::class, 'approveWholesaleReplacement'])->name('wholesale-replacements.approve');
        Route::post('/wholesale-replacements/{replacement}/reject', [SalesReportController::class, 'rejectWholesaleReplacement'])->name('wholesale-replacements.reject');
    });

    Route::middleware(['can:manage-sales-status'])->group(function () {
        Route::patch('/sales/{id}/status', [SalesController::class, 'updateStatus'])->name('sales.status.update');
    });

    Route::get('/staff-logs', [StaffActivityLogController::class, 'index'])
        ->middleware('can:view-staff-logs')
        ->name('staff-logs.index');
    Route::get('/staff-logs/{staffLog}', [StaffActivityLogController::class, 'show'])
        ->middleware('can:view-staff-logs')
        ->name('staff-logs.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

});
