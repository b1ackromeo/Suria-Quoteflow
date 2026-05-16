<?php

use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/setup', [AuthController::class, 'setupForm'])->name('setup');
    Route::post('/setup', [AuthController::class, 'setup'])->name('setup.store');
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:admin,manager,sales,procurement,accounts,viewer'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/search', SearchController::class)->name('search.index');

    Route::resource('customers', CustomerController::class)->middleware('role:admin,manager,sales')->except(['show', 'destroy']);
    Route::resource('suppliers', SupplierController::class)->middleware('role:admin,manager,procurement')->except(['show', 'destroy']);
    Route::resource('products', ProductController::class)->middleware('role:admin,manager,sales,procurement')->except(['show', 'destroy']);

    Route::get('/documents/{module}', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{module}/export', [DocumentController::class, 'exportCsv'])->name('documents.export');
    Route::get('/documents/{module}/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents/{module}', [DocumentController::class, 'store'])->name('documents.store');

    Route::get('/document/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/document/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
    Route::put('/document/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::post('/document/{document}/submit', [DocumentController::class, 'submit'])->name('documents.submit');
    Route::post('/document/{document}/approve', [DocumentController::class, 'approve'])->middleware('role:admin,manager')->name('documents.approve');
    Route::post('/document/{document}/reject', [DocumentController::class, 'reject'])->middleware('role:admin,manager')->name('documents.reject');
    Route::post('/document/{document}/transition/{action}', [DocumentController::class, 'transition'])->name('documents.transition');
    Route::post('/document/{document}/attachments', [DocumentController::class, 'uploadAttachment'])->name('documents.attachments.store');
    Route::post('/attachments/{attachment}/extract', [DocumentController::class, 'extractAttachment'])->name('attachments.extract');
    Route::put('/attachment-extractions/{extraction}', [DocumentController::class, 'verifyExtraction'])->name('attachment-extractions.verify');
    Route::get('/attachments/{attachment}/preview', [DocumentController::class, 'previewAttachment'])->name('attachments.preview');
    Route::get('/attachments/{attachment}', [DocumentController::class, 'downloadAttachment'])->name('attachments.download');
    Route::get('/document/{document}/pdf', [DocumentController::class, 'pdf'])->name('documents.pdf');
    Route::get('/document/{document}/pdf/download', [DocumentController::class, 'downloadPdf'])->name('documents.pdf.download');

    Route::get('/payments', [PaymentController::class, 'index'])->middleware('role:admin,manager,accounts')->name('payments.index');
    Route::get('/document/{document}/payments/create', [PaymentController::class, 'create'])->middleware('role:admin,manager,accounts')->name('payments.create');
    Route::post('/document/{document}/payments', [PaymentController::class, 'store'])->middleware('role:admin,manager,accounts')->name('payments.store');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

    Route::resource('users', UserController::class)->middleware('role:admin')->except(['show', 'destroy']);
    Route::resource('company-profiles', CompanyProfileController::class)->middleware('role:admin')->except(['show', 'destroy']);
    Route::post('/company-profiles/{companyProfile}/activate', [CompanyProfileController::class, 'activate'])->middleware('role:admin')->name('company-profiles.activate');
    Route::get('/audit-trail', [AuditTrailController::class, 'index'])->middleware('role:admin,manager')->name('audit.index');
});
