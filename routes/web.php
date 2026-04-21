<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

// User archive routes (Browse, Viewer, Compare) are added in Phase 6.
// Admin panel is mounted at /admin by the Filament AdminPanelProvider.
// Horizon dashboard is mounted at /horizon by HorizonServiceProvider.
