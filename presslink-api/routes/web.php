<?php

use App\Livewire\Auth\Login;
use App\Livewire\Clients\Index as ClientsIndex;
use App\Livewire\Clients\Show as ClientsShow;
use App\Livewire\Dashboard;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Livewire\Orders\Index as OrdersIndex;
use App\Livewire\Orders\Show as OrdersShow;
use App\Livewire\Services\Index as ServicesIndex;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');

    Route::get('/commandes', OrdersIndex::class)->name('orders.index');
    Route::get('/commandes/nouvelle', OrdersCreate::class)->name('orders.create');
    Route::get('/commandes/{order}', OrdersShow::class)->name('orders.show');

    Route::get('/clients', ClientsIndex::class)->name('clients.index');
    Route::get('/clients/{customer}', ClientsShow::class)->name('clients.show');

    Route::get('/tarifs', ServicesIndex::class)->name('services.index');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
