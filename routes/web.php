<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('team', 'pages::team')->name('team.edit');
    Route::livewire('predictions', 'pages::predictions')->name('predictions.edit');
    Route::livewire('surveys', 'pages::surveys')->name('surveys.index');

    Route::livewire('admin', 'pages::admin.dashboard')
        ->middleware('can:access-admin')
        ->name('admin.dashboard');
    Route::livewire('admin/scoring', 'pages::admin.scoring')
        ->middleware('can:access-admin')
        ->name('admin.scoring');
});

require __DIR__.'/settings.php';
