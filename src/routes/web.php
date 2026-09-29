<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Volt::route('usuarios', 'usuarios.index')->name('usuarios.index');
    Volt::route('usuarios/crear', 'usuarios.form')->name('usuarios.create');
    Volt::route('usuarios/{user}/editar', 'usuarios.form')->name('usuarios.edit');
});

require __DIR__.'/auth.php';
