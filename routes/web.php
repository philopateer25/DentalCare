<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/dashboard', function () {
    return Inertia::render('dashboard');
})->name('dashboard');

Route::get('/patients', function () {
    return Inertia::render('patiants');
})->name('patients');

Route::get('/operations', function () {
    return Inertia::render('operations');
})->name('operations');

Route::get('/finance', function () {
    return Inertia::render('finance');
})->name('finance');

Route::get('/insurance', function () {
    return Inertia::render('insurance');
})->name('insurance');

Route::get('/inventory', function () {
    return Inertia::render('inventory');
})->name('inventory');

Route::get('/labs', function () {
    return Inertia::render('labs');
})->name('labs');


