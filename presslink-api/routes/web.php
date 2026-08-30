<?php

use App\Http\Controllers\OrderExportController;
use App\Livewire\Account\Settings as AccountSettings;
use App\Livewire\Admin\Administrators\Index as AdminAdministratorsIndex;
use App\Livewire\Admin\Clients\Index as AdminClientsIndex;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Orders\Index as AdminOrdersIndex;
use App\Livewire\Admin\Pressings\Create as AdminPressingsCreate;
use App\Livewire\Admin\Pressings\Index as AdminPressingsIndex;
use App\Livewire\Admin\Pressings\Show as AdminPressingsShow;
use App\Livewire\Auth\Login;
use App\Livewire\Clients\Index as ClientsIndex;
use App\Livewire\Clients\Show as ClientsShow;
use App\Livewire\Dashboard;
use App\Livewire\Issues\Index as IssuesIndex;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Livewire\Orders\Index as OrdersIndex;
use App\Livewire\Orders\Show as OrdersShow;
use App\Livewire\Pressing\Settings as PressingSettings;
use App\Livewire\Services\Index as ServicesIndex;
use App\Livewire\Services\Variants as ServicesVariants;
use App\Livewire\Subscription\Show as SubscriptionShow;
use App\Livewire\Team\Index as TeamIndex;
use App\Models\Pressing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');

    Route::get('/vue-ensemble', function () {
        session()->forget('active_pressing_id');

        return redirect()->route('dashboard');
    })->name('pressings.overview');

    Route::get('/pressings/{pressing}/activer', function (Pressing $pressing) {
        abort_unless(auth()->user()->belongsToPressing($pressing), 403);

        session(['active_pressing_id' => $pressing->id]);

        return redirect()->route('dashboard');
    })->name('pressings.switch');

    Route::get('/commandes', OrdersIndex::class)->name('orders.index');
    Route::get('/commandes/nouvelle', OrdersCreate::class)->name('orders.create');
    Route::get('/commandes/export/{format}', OrderExportController::class)
        ->name('orders.export')
        ->where('format', 'csv|xlsx|pdf');
    Route::get('/commandes/{order}', OrdersShow::class)->name('orders.show');

    Route::get('/clients', ClientsIndex::class)->name('clients.index');
    Route::get('/clients/{customer}', ClientsShow::class)->name('clients.show');

    Route::get('/signalements', IssuesIndex::class)->name('issues.index');

    Route::get('/tarifs', ServicesIndex::class)->name('services.index');
    Route::get('/tarifs/{service}/variantes', ServicesVariants::class)->name('services.variants');

    Route::get('/mon-compte', AccountSettings::class)->name('account.settings');

    Route::get('/equipe', TeamIndex::class)->name('team.index');
    Route::get('/parametres', PressingSettings::class)->name('pressing.settings');
    Route::get('/abonnement', SubscriptionShow::class)->name('subscription.show');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboard::class)->name('dashboard');
        Route::get('/pressings', AdminPressingsIndex::class)->name('pressings.index');
        Route::get('/pressings/nouveau', AdminPressingsCreate::class)->name('pressings.create');
        Route::get('/pressings/{pressing}', AdminPressingsShow::class)->name('pressings.show');
        Route::get('/commandes', AdminOrdersIndex::class)->name('orders.index');
        Route::get('/clients', AdminClientsIndex::class)->name('clients.index');
        Route::get('/administrateurs', AdminAdministratorsIndex::class)->name('administrators.index');
    });

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
