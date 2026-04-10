<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Auth\AuthApiController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeliverySettingController;
use App\Http\Controllers\Api\StoreConfigurationController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PermissionUserController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductKitController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\CutController;
use App\Http\Controllers\Api\FabricRollController;
use App\Http\Controllers\Api\CutProductionController;
use App\Http\Controllers\Api\SeamstressController;
use App\Http\Controllers\Api\SeamstressCostController;
use App\Http\Controllers\Api\SeamstressAssignmentController;
use App\Http\Controllers\Api\SeamstressDistributionController;
use App\Http\Controllers\Api\InventoryMovementController;
use App\Http\Controllers\Api\NewsletterSubscriptionController;
use App\Http\Controllers\Api\AdminNotificationController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\UserAddressController;
use App\Http\Controllers\Api\ProductMeasurementController;
use App\Http\Controllers\Api\ShippingManagementController;
use App\Http\Controllers\Api\MelhorEnvioWebhookController;
use App\Http\Controllers\Api\CounterSaleController;
use App\Http\Controllers\Api\BarcodeController;
use App\Http\Controllers\Api\Admin\NewsletterController;
use App\Http\Controllers\Api\MercadoLivreAuthController;
use App\Http\Controllers\Api\MercadoLivreController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Http\Request;

// Autenticação
Route::post('/auth', [AuthApiController::class, 'auth'])->middleware('throttle:10,1')->name('auth.login');
Route::post('/register', [AuthApiController::class, 'register'])->middleware('throttle:5,60')->name('auth.register');

// Recuperação de senha
Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])->middleware('throttle:3,60')->name('password.forgot');
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:5,60')->name('password.reset');


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthApiController::class, 'me'])->name('auth.me');
    Route::post('/logout', [AuthApiController::class, 'logout'])->name('auth.logout');
});

    Route::middleware('throttle:30,1')->prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index'])->name('cart.index');
        Route::post('/add', [CartController::class, 'add'])->name('cart.add');
        Route::delete('/remove/{key}', [CartController::class, 'remove'])->name('cart.remove');
        Route::post('/clear', [CartController::class, 'clear'])->name('cart.clear');
        Route::get('/token', [CartController::class, 'generateToken'])->name('cart.token');
        Route::put('/sync', [CartController::class, 'sync'])->name('cart.sync');
    });

Route::middleware('auth:sanctum')->prefix('account')->group(function () {
    Route::get('/', [AccountController::class, 'profile'])->name('account.profile');
    Route::put('/update', [AccountController::class, 'updateProfile'])->name('account.update');
    Route::get('/orders', [AccountController::class, 'orders'])->name('account.orders');
    Route::get('/orders/{orderId}', [AccountController::class, 'orderDetails'])->name('account.orders.details');
    Route::post('/orders/{orderId}/reorder', [AccountController::class, 'reorder'])->name('account.orders.reorder');
    Route::delete('/delete', [AccountController::class, 'deleteAccount'])->name('account.delete');

    // Endereços do usuário
    Route::prefix('addresses')->group(function () {
        Route::get('/', [UserAddressController::class, 'index'])->name('account.addresses.index');
        Route::post('/', [UserAddressController::class, 'store'])->name('account.addresses.store');
        Route::get('/{id}', [UserAddressController::class, 'show'])->name('account.addresses.show');
        Route::put('/{id}', [UserAddressController::class, 'update'])->name('account.addresses.update');
        Route::delete('/{id}', [UserAddressController::class, 'destroy'])->name('account.addresses.destroy');
        Route::put('/{id}/default', [UserAddressController::class, 'setDefault'])->name('account.addresses.default');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/payments', [PaymentController::class, 'create'])->name('checkout.pix.create');
    Route::get('/payments/{paymentId}', [PaymentController::class, 'status'])->name('payment.status');
    Route::post('/coupons/validate', [CouponController::class, 'validate'])->name('coupons.validate');
});

Route::post('/mercadopago/webhook', [WebhookController::class, 'handle'])->name('mercadopago.webhook');
Route::post('/melhorenvio/webhook', [MelhorEnvioWebhookController::class, 'handle'])->name('melhorenvio.webhook');
Route::post('/mercadolivre/webhook', [MercadoLivreAuthController::class, 'webhook'])->name('mercadolivre.webhook');

// Mercado Livre OAuth — connect fora do grupo admin para token.query rodar antes do auth:sanctum
Route::get('/mercadolivre/auth/connect', [MercadoLivreAuthController::class, 'connect'])
    ->middleware(['token.query', 'auth:sanctum', 'acl', 'admin'])
    ->name('ml.connect');

// Etiqueta ML — fora do grupo admin para token.query rodar antes do auth:sanctum (abre no browser)
Route::get('/mercadolivre/orders/{orderId}/label', [MercadoLivreController::class, 'shipmentLabel'])
    ->middleware(['token.query', 'auth:sanctum', 'acl', 'admin'])
    ->name('ml.orders.label.download');
Route::get('/mercadolivre/auth/callback', [MercadoLivreAuthController::class, 'callback'])->name('ml.callback');

    Route::get('/device-id', function () {
        return view('device-id');
    });

// Banners ativos (público) - DEVE VIR ANTES do apiResource de banners
Route::get('/banners/active', [BannerController::class, 'active'])->name('banners.active');

// Categorias com produtos (público) - DEVE VIR ANTES do apiResource de categories
Route::get('/categories/public', [CategoryController::class, 'publicList'])->name('categories.public');

// Produtos em promoção (público) - DEVE VIR ANTES do apiResource de products
Route::get('/products/promotions', [ProductController::class, 'promotions'])->name('products.promotions');

// Lista de produtos ativos (público) - DEVE VIR ANTES do apiResource de products
Route::get('/products/public', [ProductController::class, 'publicList'])->name('products.public');

// Detalhe de produto pelo slug (público) - DEVE VIR ANTES do apiResource de products
Route::get('/products/{slug}/show', [ProductController::class, 'showBySlug'])->name('products.show.slug');

// Rotas administrativas – protegidas por acl e admin
Route::middleware(['auth:sanctum', 'acl', 'admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

    // Mercado Livre
    Route::prefix('mercadolivre')->name('ml.')->group(function () {
        Route::get('/auth/status', [MercadoLivreAuthController::class, 'status'])->name('status');
        Route::delete('/auth/disconnect', [MercadoLivreAuthController::class, 'disconnect'])->name('disconnect');

        // Listagens
        Route::get('/listings', [MercadoLivreController::class, 'index'])->name('listings.index');
        Route::get('/listings/{productId}', [MercadoLivreController::class, 'show'])->name('listings.show');
        Route::post('/listings/{productId}', [MercadoLivreController::class, 'publish'])->name('listings.publish');
        Route::put('/listings/{productId}/pause', [MercadoLivreController::class, 'pause'])->name('listings.pause');
        Route::put('/listings/{productId}/activate', [MercadoLivreController::class, 'activate'])->name('listings.activate');
        Route::delete('/listings/{productId}', [MercadoLivreController::class, 'destroy'])->name('listings.destroy');

        // Envio / Etiqueta / Sync
        Route::get('/orders/{orderId}/shipment-status', [MercadoLivreController::class, 'shipmentStatus'])->name('orders.shipment-status');
        Route::post('/orders/{orderId}/sync-status', [MercadoLivreController::class, 'syncOrderStatus'])->name('orders.sync-status');
    });
    Route::get('/permissions/groups', [PermissionController::class, 'groups'])->name('permissions.groups');
    Route::apiResource('/permissions', PermissionController::class);
    Route::get("/orders", [OrderController::class, "index"])->name("admin.orders.index");
    Route::get("/orders/analytics", [OrderController::class, "analytics"])->name("admin.orders.analytics");
    Route::get("/orders/export", [OrderController::class, "export"])->name("admin.orders.export");
    Route::get("/orders/{orderId}/print", [OrderController::class, "print"])->name("admin.orders.print");
    Route::get("/orders/{orderId}", [OrderController::class, "show"])->name("admin.orders.show");
    Route::put('/orders/{orderId}/status', [OrderController::class, 'updateStatus'])->name('admin.orders.updateStatus');
    Route::post('/orders/{orderId}/cancel', [OrderController::class, 'cancel'])->name('admin.orders.cancel');
    Route::apiResource('/banners', BannerController::class);
    Route::apiResource('/settings', SettingController::class);
    Route::put('/theme/{page}', [SettingController::class, 'updateThemePage'])->name('admin.theme.update');
    Route::post('/theme/{page}/reset', [SettingController::class, 'resetThemePage'])->name('admin.theme.reset');

    // Rotas exclusivas do super admin
    Route::middleware('superadmin')->group(function () {
        Route::put('/theme/{page}/defaults', [SettingController::class, 'updateThemeDefaults'])->name('superadmin.theme.defaults');
    });
    Route::apiResource('/products', ProductController::class);
    Route::post('/products/{product}/duplicate', [ProductController::class, 'duplicate'])->name('products.duplicate');

    Route::post('/products/{product}/kits', [ProductKitController::class, 'store'])->name('products.kits.store');
    Route::put('/kits/{kit}', [ProductKitController::class, 'update'])->name('kits.update');
    Route::delete('/kits/{kit}', [ProductKitController::class, 'destroy'])->name('kits.destroy');
    Route::get('/products/{product}/variants', [ProductKitController::class, 'variants'])->name('products.variants.index');
    Route::post('/products/{product}/variants', [ProductVariantController::class, 'store'])->name('products.variants.store');
    Route::put('/products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])->name('products.variants.update');
    Route::delete('/products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])->name('products.variants.destroy');
    Route::get('/products/{product}/kits', [ProductKitController::class, 'kits'])->name('products.kits.index');

    // Códigos de barras
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/barcode/{barcode}', [BarcodeController::class, 'lookup'])->name('barcode.lookup');
        Route::get('/barcode/{barcode}/label', [BarcodeController::class, 'label'])->name('barcode.label');
        Route::post('/barcode/{variantId}/generate', [BarcodeController::class, 'generate'])->name('barcode.generate');
        Route::post('/products/{product}/generate-barcodes', [BarcodeController::class, 'generateForProduct'])->name('barcode.generate-for-product');
        Route::get('/products/{product}/barcode-labels', [BarcodeController::class, 'labelsForProduct'])->name('barcode.labels-for-product');
    });

    // Tabela de medidas do produto
    Route::get('/products/{product}/measurements', [ProductMeasurementController::class, 'index'])->name('products.measurements.index');
    Route::post('/products/{product}/measurements', [ProductMeasurementController::class, 'store'])->name('products.measurements.store');
    Route::post('/products/{product}/measurements/bulk', [ProductMeasurementController::class, 'bulkStore'])->name('products.measurements.bulk');
    Route::put('/measurements/{measurement}', [ProductMeasurementController::class, 'update'])->name('measurements.update');
    Route::delete('/measurements/{measurement}', [ProductMeasurementController::class, 'destroy'])->name('measurements.destroy');
    Route::post('/products/{product}/measurement-image', [ProductMeasurementController::class, 'uploadImage'])->name('products.measurements.image.upload');
    Route::delete('/products/{product}/measurement-image', [ProductMeasurementController::class, 'deleteImage'])->name('products.measurements.image.delete');

    Route::get('/inventory/all', [InventoryController::class, 'all'])->name('inventory.all');
    Route::post('/inventory/bulk-set', [InventoryController::class, 'bulkSet'])->name('inventory.bulk-set');
    Route::get('/inventory/product/{product}', [InventoryController::class, 'showByProduct'])->name('inventory.product');
    Route::get('/inventory/variant/{variant}', [InventoryController::class, 'showByVariant'])->name('inventory.variant');
    Route::post('/inventory/variant/{variant}/add', [InventoryController::class, 'addStock'])->name('inventory.variant.add');
    Route::post('/inventory/variant/{variant}/remove', [InventoryController::class, 'removeStock'])->name('inventory.variant.remove');
    Route::put('/inventory/variant/{variant}/set', [InventoryController::class, 'setStock'])->name('inventory.variant.set');

    // Histórico de movimentações de estoque
    Route::get('/inventory-movements/history', [InventoryMovementController::class, 'history'])->name('inventory-movements.history');
    Route::get('/inventory-movements/summary', [InventoryMovementController::class, 'generalSummary'])->name('inventory-movements.summary');
    Route::get('/inventory-movements/variant/{variant}', [InventoryMovementController::class, 'showByVariant'])->name('inventory-movements.variant');
    Route::get('/inventory-movements/product/{productId}', [InventoryMovementController::class, 'showByProduct'])->name('inventory-movements.product');
    Route::get('/inventory-movements/order/{orderId}', [InventoryMovementController::class, 'showByOrder'])->name('inventory-movements.order');
    Route::get('/inventory-movements/variant/{variant}/summary', [InventoryMovementController::class, 'summaryByVariant'])->name('inventory-movements.variant.summary');
    Route::post('/inventory-movements/{movement}/revert', [InventoryMovementController::class, 'revert'])->name('inventory-movements.revert');
    Route::get('/inventory-movements/weekly-report', [InventoryMovementController::class, 'weeklyReport'])->name('inventory-movements.weekly-report');
    Route::get('/inventory/export-stock', [InventoryMovementController::class, 'exportStock'])->name('inventory.export-stock');

    Route::apiResource('/categories', CategoryController::class)->names([
        'index' => 'categories.index',
        'store' => 'categories.store',
        'show' => 'categories.show',
        'update' => 'categories.update',
        'destroy' => 'categories.destroy',
    ]);

    Route::get('/users/search', [UserController::class, 'search'])->name('users.search');
    Route::get('/users/{user}/permissions', [PermissionUserController::class, 'getPermissionsOfUser'])->name('users.permissions');
    Route::post('/users/{user}/permissions-sync', [PermissionUserController::class, 'syncPermissionsOfUser'])->name('users.permissions.sync');
    Route::apiResource('/users', UserController::class)->except(['create', 'edit']);
    Route::put('/users/{user}/make-admin', [UserController::class, 'makeAdmin'])->name('users.make-admin');
    Route::put('/users/{user}/revoke-admin', [UserController::class, 'revokeAdmin'])->name('users.revoke-admin');

    // Configurações de entrega (admin)
    Route::put('/delivery-settings', [DeliverySettingController::class, 'update'])->name('delivery-settings.update');

    // Configurações da loja (integrações)
    Route::get('/store-configuration', [StoreConfigurationController::class, 'show'])->name('store-configuration.show');
    Route::put('/store-configuration', [StoreConfigurationController::class, 'update'])->name('store-configuration.update');

    // Cupons (admin)
    Route::apiResource('/coupons', CouponController::class);

    // ============ GESTAO DE PRODUCAO ============

    // Cortes
    Route::prefix('cuts')->group(function () {
        Route::get('/', [CutController::class, 'index'])->name('cuts.index');
        Route::post('/', [CutController::class, 'store'])->name('cuts.store');
        Route::get('/summary', [CutController::class, 'summary'])->name('cuts.summary');
        Route::get('/{cut}', [CutController::class, 'show'])->name('cuts.show');
        Route::put('/{cut}', [CutController::class, 'update'])->name('cuts.update');
        Route::delete('/{cut}', [CutController::class, 'destroy'])->name('cuts.destroy');
        Route::put('/{cut}/status/{status}', [CutController::class, 'updateStatus'])->name('cuts.status');

        // Rolos de tecido (nested)
        Route::get('/{cut}/fabric-rolls', [FabricRollController::class, 'index'])->name('cuts.fabric-rolls.index');
        Route::post('/{cut}/fabric-rolls', [FabricRollController::class, 'store'])->name('cuts.fabric-rolls.store');

        // Producoes (nested)
        Route::get('/{cut}/productions', [CutProductionController::class, 'index'])->name('cuts.productions.index');
        Route::post('/{cut}/productions', [CutProductionController::class, 'store'])->name('cuts.productions.store');
        Route::post('/{cut}/productions/batch', [CutProductionController::class, 'storeBatch'])->name('cuts.productions.batch');
    });

    // Rolos de tecido (standalone)
    Route::prefix('fabric-rolls')->group(function () {
        Route::get('/{fabricRoll}', [FabricRollController::class, 'show'])->name('fabric-rolls.show');
        Route::put('/{fabricRoll}', [FabricRollController::class, 'update'])->name('fabric-rolls.update');
        Route::delete('/{fabricRoll}', [FabricRollController::class, 'destroy'])->name('fabric-rolls.destroy');
    });

    // Producoes (standalone)
    Route::prefix('productions')->group(function () {
        Route::get('/{cutProduction}', [CutProductionController::class, 'show'])->name('productions.show');
        Route::put('/{cutProduction}', [CutProductionController::class, 'update'])->name('productions.update');
        Route::delete('/{cutProduction}', [CutProductionController::class, 'destroy'])->name('productions.destroy');
        Route::post('/{cutProduction}/link-product', [CutProductionController::class, 'linkProduct'])->name('productions.link-product');
        Route::get('/{cutProduction}/assignments', [SeamstressAssignmentController::class, 'byProduction'])->name('productions.assignments');
    });

    // Costureiras
    Route::prefix('seamstresses')->group(function () {
        Route::get('/', [SeamstressController::class, 'index'])->name('seamstresses.index');
        Route::get('/all', [SeamstressController::class, 'all'])->name('seamstresses.all');
        Route::post('/', [SeamstressController::class, 'store'])->name('seamstresses.store');
        Route::get('/{seamstress}', [SeamstressController::class, 'show'])->name('seamstresses.show');
        Route::put('/{seamstress}', [SeamstressController::class, 'update'])->name('seamstresses.update');
        Route::delete('/{seamstress}', [SeamstressController::class, 'destroy'])->name('seamstresses.destroy');
        Route::get('/{seamstress}/performance', [SeamstressController::class, 'performance'])->name('seamstresses.performance');

        // Custos (nested)
        Route::get('/{seamstress}/costs', [SeamstressCostController::class, 'index'])->name('seamstresses.costs.index');
        Route::post('/{seamstress}/costs', [SeamstressCostController::class, 'store'])->name('seamstresses.costs.store');

        // Distribuicoes (nested)
        Route::get('/{seamstress}/assignments', [SeamstressAssignmentController::class, 'bySeamstress'])->name('seamstresses.assignments');
    });

    // Custos de costureira (standalone)
    Route::prefix('seamstress-costs')->group(function () {
        Route::put('/{seamstressCost}', [SeamstressCostController::class, 'update'])->name('seamstress-costs.update');
        Route::delete('/{seamstressCost}', [SeamstressCostController::class, 'destroy'])->name('seamstress-costs.destroy');
    });

    // Distribuicoes para costureiras
    Route::prefix('distributions')->group(function () {
        Route::get('/', [SeamstressDistributionController::class, 'index'])->name('distributions.index');
        Route::get('/{distribution}', [SeamstressDistributionController::class, 'show'])->name('distributions.show');
        Route::put('/{distribution}', [SeamstressDistributionController::class, 'update'])->name('distributions.update');
        Route::delete('/{distribution}', [SeamstressDistributionController::class, 'destroy'])->name('distributions.destroy');
    });

    Route::prefix('assignments')->group(function () {
        Route::get('/', [SeamstressAssignmentController::class, 'index'])->name('assignments.index');
        Route::post('/', [SeamstressAssignmentController::class, 'store'])->name('assignments.store');
        Route::post('/batch', [SeamstressAssignmentController::class, 'storeBatch'])->name('assignments.batch');
        Route::get('/{assignment}', [SeamstressAssignmentController::class, 'show'])->name('assignments.show');
        Route::put('/{assignment}', [SeamstressAssignmentController::class, 'update'])->name('assignments.update');
        Route::post('/{assignment}/return', [SeamstressAssignmentController::class, 'recordReturn'])->name('assignments.return');
        Route::delete('/{assignment}', [SeamstressAssignmentController::class, 'destroy'])->name('assignments.destroy');
    });

    // Newsletter (admin)
    Route::prefix('newsletters')->group(function () {
        Route::get('/', [NewsletterController::class, 'index'])->name('admin.newsletters.index');
        Route::post('/', [NewsletterController::class, 'store'])->name('admin.newsletters.store');
        Route::get('/{id}', [NewsletterController::class, 'show'])->name('admin.newsletters.show');
        Route::put('/{id}', [NewsletterController::class, 'update'])->name('admin.newsletters.update');
        Route::delete('/{id}', [NewsletterController::class, 'destroy'])->name('admin.newsletters.destroy');
        Route::post('/{id}/send', [NewsletterController::class, 'send'])->name('admin.newsletters.send');
        Route::post('/{id}/schedule', [NewsletterController::class, 'schedule'])->name('admin.newsletters.schedule');
        Route::post('/upload-image', [NewsletterController::class, 'uploadImage'])->name('admin.newsletters.upload-image');
    });
    Route::get('/newsletter-subscribers', [NewsletterController::class, 'subscribers'])->name('admin.newsletter-subscribers.index');
    Route::delete('/newsletter-subscribers/{id}', [NewsletterController::class, 'removeSubscriber'])->name('admin.newsletter-subscribers.destroy');
    Route::get('/newsletter-subscribers/status/{email}', [NewsletterSubscriptionController::class, 'status'])->name('admin.newsletter-subscribers.status');

    // Gestão de envios (Melhor Envio)
    Route::prefix('shipping-management')->group(function () {
        Route::post('/{orderId}/purchase-label', [ShippingManagementController::class, 'purchaseLabel'])->name('admin.shipping.purchase-label');
        Route::get('/{orderId}/print-label', [ShippingManagementController::class, 'printLabel'])->name('admin.shipping.print-label');
        Route::get('/{orderId}/tracking', [ShippingManagementController::class, 'tracking'])->name('admin.shipping.tracking');
        Route::post('/{orderId}/cancel-label', [ShippingManagementController::class, 'cancelLabel'])->name('admin.shipping.cancel-label');
        Route::post('/{orderId}/reverse-label', [ShippingManagementController::class, 'reverseLabel'])->name('admin.shipping.reverse-label');
    });

    // Vendas de balcão (PDV)
    Route::prefix('counter-sales')->group(function () {
        Route::post('/', [CounterSaleController::class, 'store'])->name('admin.counter-sales.store');
        Route::get('/', [CounterSaleController::class, 'index'])->name('admin.counter-sales.index');
        Route::get('/{id}', [CounterSaleController::class, 'show'])->name('admin.counter-sales.show');
    });

    // Notificacoes admin
    Route::prefix('notifications')->group(function () {
        Route::get('/', [AdminNotificationController::class, 'index'])->name('admin.notifications.index');
        Route::get('/unread', [AdminNotificationController::class, 'unread'])->name('admin.notifications.unread');
        Route::put('/read-all', [AdminNotificationController::class, 'markAllAsRead'])->name('admin.notifications.read-all');
        Route::put('/{notification}/read', [AdminNotificationController::class, 'markAsRead'])->name('admin.notifications.read');
    });

});

// Tema do site por página (público — front-end consome para aplicar estilos em tempo real)
Route::get('/theme/{page}', [SettingController::class, 'themePage'])->name('theme.page.public');

// Rotas públicas para configurações de entrega
Route::get('/delivery-settings', [DeliverySettingController::class, 'show'])->name('delivery-settings.show');
Route::get('/delivery-settings/check', [DeliverySettingController::class, 'checkCutoff'])->name('delivery-settings.check');

// Calculo de frete (publico)
Route::middleware('throttle:10,1')->post('/shipping/calculate', [ShippingController::class, 'calculate'])->name('shipping.calculate');

Route::prefix('address')->group(function () {
    // GET /api/address/states - Lista todos os estados
    Route::get('/states', [AddressController::class, 'getStates']);

    // GET /api/address/cities/{uf} - Lista cidades de um estado
    Route::get('/cities/{uf}', [AddressController::class, 'getCities']);

    // GET /api/address/cep/{cep} - Busca endereço por CEP (proxy ViaCEP)
    Route::middleware('throttle:30,1')->get('/cep/{cep}', [AddressController::class, 'getAddressByCep']);
});

// Newsletter (público)
Route::prefix('newsletter')->group(function () {
    Route::middleware('throttle:5,60')->post('/subscribe', [NewsletterSubscriptionController::class, 'subscribe'])->name('newsletter.subscribe');
    Route::get('/unsubscribe/{token}', [NewsletterSubscriptionController::class, 'unsubscribe'])->name('newsletter.unsubscribe');
});

// Código de barras — PDV e etiquetas (requer autenticação)
// Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
//     Route::get('/barcode/{code}', [BarcodeController::class, 'lookup'])->name('barcode.lookup');
//     Route::get('/products/{productId}/labels', [BarcodeController::class, 'labels'])->name('barcode.labels');
// });

// Página inicial e produto individual (público)
Route::get('/', [HomeController::class, 'index'])->name('home.index');
Route::get('/produto/{slug}', [HomeController::class, 'show'])->name('home.product.show');
Route::get('/kits/featured', [HomeController::class, 'featuredKits'])->name('kits.featured');