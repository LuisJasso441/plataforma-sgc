<?php

use App\Http\Controllers\NcAttachmentController;
use App\Http\Controllers\NcBitacoraExportController;
use App\Http\Controllers\NcReportDownloadController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Sin portada: al login, o al panel si ya hay sesión
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/appearance');

    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Volt::route('usuarios', 'usuarios.index')->name('usuarios.index');
    Volt::route('usuarios/crear', 'usuarios.form')->name('usuarios.create');
    Volt::route('usuarios/{user}/editar', 'usuarios.form')->name('usuarios.edit');
});

// Módulo No Conformidad: la entrada exige Lector; lo demás lo decide NonConformityPolicy
Route::middleware(['auth', 'module:no-conformidad'])
    ->prefix('no-conformidad')
    ->name('no-conformidad.')
    ->group(function () {
        Volt::route('/', 'no-conformidad.index')->name('index');
        Route::get('bitacora/exportar', NcBitacoraExportController::class)->name('bitacora.export');
        Volt::route('crear', 'no-conformidad.form')->name('create');
        Volt::route('{nonConformity}', 'no-conformidad.show')->name('show')->whereNumber('nonConformity');
        Volt::route('{nonConformity}/editar', 'no-conformidad.form')->name('edit');
        Route::get('adjuntos/{attachment}', NcAttachmentController::class)
            ->name('attachments.download')->whereNumber('attachment');
        Route::get('{nonConformity}/reporte', NcReportDownloadController::class)
            ->name('report.download')->whereNumber('nonConformity');
        Volt::route('{nonConformity}/reporte/editar', 'no-conformidad.report-form')
            ->name('report.edit')->whereNumber('nonConformity');
    });

require __DIR__.'/auth.php';
