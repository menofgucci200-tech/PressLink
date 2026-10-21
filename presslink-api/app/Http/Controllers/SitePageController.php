<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Mail\ContactMessage;
use App\Support\PlanCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Pages publiques du site presslink.org (une page par entrée de navigation),
 * chacune avec son propre title / description pour le référencement.
 */
class SitePageController extends Controller
{
    /**
     * Adresse publique de l'application web (PWA).
     */
    public static function appUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/app/';
    }

    public function clients(): View
    {
        return view('site.clients', ['appUrl' => self::appUrl()]);
    }

    public function pressings(): View
    {
        return view('site.pressings');
    }

    public function pricing(): View
    {
        return view('site.pricing', [
            'plans' => PlanCatalog::plans(),
            'trialDays' => PlanCatalog::trialDays(),
            'launchOffer' => PlanCatalog::launchOffer(),
            'options' => collect(Feature::cases())->filter(fn ($f) => $f->priceFcfa() !== null)->values(),
        ]);
    }

    public function how(): View
    {
        return view('site.how', ['appUrl' => self::appUrl()]);
    }

    public function install(): View
    {
        return view('site.install', ['appUrl' => self::appUrl()]);
    }

    public function faq(): View
    {
        return view('site.faq', ['faqs' => LandingController::faqs()]);
    }

    public function contact(): View
    {
        return view('site.contact', [
            'supportPhoneDisplay' => config('presslink.support.phone_display'),
            'supportEmail' => config('presslink.support.email'),
            'canSendMail' => self::canSendMail(),
        ]);
    }

    /**
     * Le formulaire n'envoie que si un vrai transport mail est configuré :
     * avec le mailer « log » (défaut), les messages seraient perdus — la page
     * propose alors d'écrire directement à l'adresse de contact.
     */
    public static function canSendMail(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    public function sendContact(Request $request): RedirectResponse
    {
        // Champ piège invisible : rempli uniquement par les robots.
        if ($request->filled('website')) {
            return to_route('site.contact')->with('contact_sent', true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'profile' => ['required', 'in:Client,Pressing,Autre'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:190'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:30'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ], [
            'email.required_without' => 'Indiquez un email ou un téléphone pour que nous puissions vous répondre.',
            'phone.required_without' => 'Indiquez un téléphone ou un email pour que nous puissions vous répondre.',
        ]);

        abort_unless(self::canSendMail(), 503);

        Mail::to(config('presslink.support.email'))->send(new ContactMessage([
            'name' => $data['name'],
            'profile' => $data['profile'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'message' => $data['message'],
        ]));

        return to_route('site.contact')->with('contact_sent', true);
    }

    public function legalNotice(): View
    {
        return view('site.legal-notice');
    }

    public function terms(): View
    {
        return view('site.terms');
    }

    public function privacy(): View
    {
        return view('site.privacy');
    }
}
