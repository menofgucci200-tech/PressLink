<?php

namespace App\Http\Controllers;

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
        ]);
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
