<?php

use App\Http\Controllers\AdminExportController;
use App\Http\Controllers\GeniusPayController;
use App\Http\Controllers\GroupExportController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\OfflineCounterController;
use App\Http\Controllers\OrderExportController;
use App\Http\Controllers\OrderReceiptController;
use App\Http\Controllers\PublicReceiptController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SitePageController;
use App\Http\Middleware\EnsureUserHasPressing;
use App\Http\Middleware\HandleSandboxPressing;
use App\Livewire\Account\Settings as AccountSettings;
use App\Livewire\Activity\Index as ActivityIndex;
use App\Livewire\Admin\Administrators\Index as AdminAdministratorsIndex;
use App\Livewire\Admin\Clients\Index as AdminClientsIndex;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Orders\Index as AdminOrdersIndex;
use App\Livewire\Admin\Payments\Index as AdminPaymentsIndex;
use App\Livewire\Admin\Plans\Index as AdminPlansIndex;
use App\Livewire\Admin\Pressings\Create as AdminPressingsCreate;
use App\Livewire\Admin\Pressings\Index as AdminPressingsIndex;
use App\Livewire\Admin\Pressings\Show as AdminPressingsShow;
use App\Livewire\Admin\Requests\Index as AdminRequestsIndex;
use App\Livewire\Admin\Roles\Index as AdminRolesIndex;
use App\Livewire\Admin\Services\Index as AdminServicesIndex;
use App\Livewire\Admin\Site\Index as AdminSiteIndex;
use App\Livewire\Admin\Users\Index as AdminUsersIndex;
use App\Livewire\Auth\AdminLogin;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\PressingSignup;
use App\Livewire\Auth\SandboxStart;
use App\Livewire\Cash\Index as CashIndex;
use App\Livewire\Clients\Index as ClientsIndex;
use App\Livewire\Clients\Show as ClientsShow;
use App\Livewire\Dashboard;
use App\Livewire\Group\Activity as GroupActivity;
use App\Livewire\Group\Cash as GroupCash;
use App\Livewire\Group\Clients as GroupClients;
use App\Livewire\Group\Harmonize as GroupHarmonize;
use App\Livewire\Group\Orders as GroupOrders;
use App\Livewire\Group\Reports as GroupReports;
use App\Livewire\Group\Team as GroupTeam;
use App\Livewire\Issues\Index as IssuesIndex;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Livewire\Orders\Index as OrdersIndex;
use App\Livewire\Orders\Show as OrdersShow;
use App\Livewire\Pressing\Settings as PressingSettings;
use App\Livewire\Services\Index as ServicesIndex;
use App\Livewire\Subscription\Checkout as SubscriptionCheckout;
use App\Livewire\Subscription\Plans as SubscriptionPlans;
use App\Livewire\Subscription\Show as SubscriptionShow;
use App\Livewire\Support\Show as SupportShow;
use App\Livewire\Team\Index as TeamIndex;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use App\Support\Guides;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Pages publiques (référencement) : accueil, pages du site + sitemap. L'accueil
// renvoie un utilisateur déjà connecté vers son espace (tableau de bord ou /admin).
Route::get('/', LandingController::class)->name('home');
Route::controller(SitePageController::class)->name('site.')->group(function () {
    Route::get('/pour-les-clients', 'clients')->name('clients');
    Route::get('/pour-les-pressings', 'pressings')->name('pressings');
    Route::get('/offres', 'pricing')->name('pricing');
    Route::get('/comment-ca-marche', 'how')->name('how');
    Route::get('/installer', 'install')->name('install');
    Route::get('/questions-frequentes', 'faq')->name('faq');
    Route::get('/guides', 'guides')->name('guides');
    Route::get('/guides/{slug}', 'guide')->whereIn('slug', array_keys(Guides::ALL))->name('guide');
    Route::get('/contact', 'contact')->name('contact');
    Route::post('/contact', 'sendContact')->middleware('throttle:5,10')->name('contact.send');
    Route::get('/mentions-legales', 'legalNotice')->name('legal');
    Route::get('/conditions-generales', 'terms')->name('terms');
    Route::get('/confidentialite', 'privacy')->name('privacy');
});
Route::redirect('/formules', '/offres', 301);
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Reçus partagés au client (WhatsApp…) ou ouverts depuis son appli : lien signé, sans connexion.
Route::middleware(['signed', 'throttle:60,1'])->group(function () {
    Route::get('/recus/commande/{order}', [PublicReceiptController::class, 'order'])->name('receipts.order');
    Route::get('/recus/paiement/{payment}', [PublicReceiptController::class, 'payment'])->name('receipts.payment');
});

// Lien « Ne plus recevoir ces emails » (signé : impossible à forger).
Route::get('/emails/desabonnement/{customer}', function (Customer $customer) {
    $customer->update(['email_notifications' => false]);

    return view('emails-unsubscribed');
})->middleware('signed')->name('emails.unsubscribe');

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/inscription', PressingSignup::class)->name('signup');
    // « Découvrir avec un pressing fictif » : test de quelques jours, données fictives.
    Route::get('/tester', SandboxStart::class)->name('sandbox.start');
    Route::get('/admin/connexion', AdminLogin::class)->name('admin.login');
});

Route::middleware(['auth', HandleSandboxPressing::class])->group(function () {
    Route::get('/tableau-de-bord', Dashboard::class)->name('dashboard');
    // Depuis un pressing de test : créer son vrai compte en gardant ses articles et ses prix.
    Route::get('/test/creer-mon-compte', PressingSignup::class)->name('sandbox.convert');

    Route::get('/vue-ensemble', function () {
        session()->forget('active_pressing_id');

        return redirect()->route('dashboard');
    })->name('pressings.overview');

    Route::get('/pressings/{pressing}/activer', function (Pressing $pressing) {
        abort_unless(auth()->user()->belongsToPressing($pressing), 403);

        session(['active_pressing_id' => $pressing->id]);

        // ?vers=/commandes?status=prete : ouvre directement la bonne page du
        // pressing (alertes du groupe). Chemins internes uniquement.
        $to = (string) request()->query('vers', '');

        return str_starts_with($to, '/') && ! str_starts_with($to, '//') && ! str_contains($to, '\\')
            ? redirect($to)
            : redirect()->route('dashboard');
    })->name('pressings.switch');

    Route::middleware(EnsureUserHasPressing::class)->group(function () {
        Route::get('/commandes', OrdersIndex::class)->name('orders.index');
        Route::get('/commandes/nouvelle', OrdersCreate::class)->name('orders.create');
        Route::get('/commandes/export/{format}', OrderExportController::class)
            ->name('orders.export')
            ->where('format', 'csv|xlsx|pdf');
        // Avant /commandes/{order} : « recus » n'est pas un numéro de commande.
        Route::get('/commandes/recus/{format?}', [OrderReceiptController::class, 'batch'])
            ->name('orders.receipts')
            ->where('format', 'pdf');
        Route::get('/commandes/{order}', OrdersShow::class)->name('orders.show');
        Route::get('/commandes/{order}/recu/{format?}', [OrderReceiptController::class, 'show'])
            ->name('orders.receipt')
            ->where('format', 'pdf');
        Route::get('/commandes/{order}/paiements/{payment}/recu/{format?}', [OrderReceiptController::class, 'payment'])
            ->name('orders.payment-receipt')
            ->where('format', 'pdf');

        Route::get('/caisse', CashIndex::class)->name('cash.index');
        Route::get('/journal', ActivityIndex::class)->name('activity.index');

        // Groupe : propriétaire ou responsable de zone qui administre au moins
        // deux pressings (contrôle dans chaque page / contrôleur).
        Route::prefix('groupe')->name('group.')->group(function () {
            Route::get('/commandes', GroupOrders::class)->name('orders');
            Route::get('/commandes/{order}', function (Order $order) {
                abort_unless(in_array($order->pressing_id, auth()->user()->managedPressingIds(), true), 403);
                session(['active_pressing_id' => $order->pressing_id]);

                return redirect()->route('orders.show', $order);
            })->name('open-order');
            Route::get('/caisse', GroupCash::class)->name('cash');
            Route::get('/journal', GroupActivity::class)->name('activity');
            Route::get('/clients', GroupClients::class)->name('clients');
            Route::get('/equipe', GroupTeam::class)->name('team');
            Route::get('/harmoniser', GroupHarmonize::class)->name('harmonize');
            Route::get('/rapports', GroupReports::class)->name('reports');
            Route::get('/rapport.pdf', [GroupExportController::class, 'report'])->name('report');
            Route::get('/export/{type}/{format}', [GroupExportController::class, 'export'])
                ->whereIn('type', ['commandes', 'paiements'])
                ->whereIn('format', ['csv', 'xlsx'])
                ->name('export');
        });

        Route::get('/clients', ClientsIndex::class)->name('clients.index');
        Route::get('/clients/{customer}', ClientsShow::class)->name('clients.show');

        Route::get('/signalements', IssuesIndex::class)->name('issues.index');

        Route::get('/services', ServicesIndex::class)->name('services.index');
        // Ancienne adresse du module « Tarifs » (favoris, liens enregistrés).
        Route::redirect('/tarifs', '/services', 301);

        Route::get('/equipe', TeamIndex::class)->name('team.index');
        Route::redirect('/profil-pressing', '/parametres', 301);
        Route::get('/parametres', PressingSettings::class)->name('pressing.settings');
        Route::get('/abonnement', SubscriptionShow::class)->name('subscription.show');
        Route::get('/abonnement/formules', SubscriptionPlans::class)->name('subscription.plans');
        Route::get('/abonnement/paiement/{subscriptionRequest}', SubscriptionCheckout::class)->name('subscription.checkout');
        Route::get('/abonnement/paiement/{subscriptionRequest}/retour', [GeniusPayController::class, 'return'])->name('subscription.payment.return');
        Route::get('/support', SupportShow::class)->name('support');

        // Comptoir hors connexion (page gardée sur le PC, données, envoi).
        Route::get('/comptoir-hors-ligne', [OfflineCounterController::class, 'show'])->name('offline.show');
        Route::get('/comptoir-hors-ligne/donnees', [OfflineCounterController::class, 'snapshot'])->name('offline.snapshot');
        Route::post('/comptoir-hors-ligne/envoyer', [OfflineCounterController::class, 'sync'])->middleware('throttle:60,1')->name('offline.sync');
    });

    Route::get('/mon-compte', AccountSettings::class)->name('account.settings');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboard::class)->name('dashboard');
        Route::get('/pressings', AdminPressingsIndex::class)->name('pressings.index');
        Route::get('/pressings/nouveau', AdminPressingsCreate::class)->name('pressings.create');
        Route::get('/pressings/{pressing}', AdminPressingsShow::class)->name('pressings.show');
        Route::get('/commandes', AdminOrdersIndex::class)->name('orders.index');
        Route::get('/paiements', AdminPaymentsIndex::class)->name('payments.index');
        Route::get('/clients', AdminClientsIndex::class)->name('clients.index');
        Route::get('/administrateurs', AdminAdministratorsIndex::class)->name('administrators.index');
        Route::get('/utilisateurs', AdminUsersIndex::class)->name('users.index');
        Route::get('/formules', AdminPlansIndex::class)->name('plans.index');
        Route::get('/demandes', AdminRequestsIndex::class)->name('requests.index');
        Route::get('/site', AdminSiteIndex::class)->name('site.index');
        Route::get('/roles', AdminRolesIndex::class)->name('roles.index');
        Route::get('/services', AdminServicesIndex::class)->name('services.index');
        Route::get('/export/{type}', AdminExportController::class)->whereIn('type', ['pressings', 'orders', 'clients'])->name('export');
    });

    Route::post('/logout', function () {
        $wasSuperAdmin = (bool) Auth::user()?->is_super_admin;
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route($wasSuperAdmin ? 'admin.login' : 'login');
    })->name('logout');
});
