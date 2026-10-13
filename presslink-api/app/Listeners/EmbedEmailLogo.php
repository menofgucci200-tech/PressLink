<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Intègre le logo PressLink dans chaque email (image en ligne « cid: »)
 * plutôt qu'en lien externe : il s'affiche même quand la messagerie bloque
 * les images distantes ou que l'URL publique de l'app est mal configurée.
 */
class EmbedEmailLogo
{
    /** URL publique du logo, utilisée telle quelle dans les aperçus. */
    public const PLACEHOLDER = 'https://presslink.org/images/email-logo.png';

    public function handle(MessageSending $event): void
    {
        $email = $event->message;
        $html = $email->getHtmlBody();

        if (! is_string($html) || ! str_contains($html, self::PLACEHOLDER)) {
            return;
        }

        $logo = (new DataPart(file_get_contents(public_path('images/email-logo.png')), 'presslink-logo.png', 'image/png'))->asInline();

        $email->addPart($logo);
        $email->html(str_replace(self::PLACEHOLDER, 'cid:'.$logo->getContentId(), $html));
    }
}
