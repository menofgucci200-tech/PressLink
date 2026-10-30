<?php

/*
 * Agent de déploiement PressLink — déposé dans le webroot par `./deploy.sh`
 * le temps d'un déploiement, puis supprimé. L'hébergement n'a pas d'accès
 * shell : c'est ce script, appelé en HTTPS avec un jeton secret, qui fait sur
 * le serveur ce qu'une commande ferait :
 *
 *   extraire  : décompresse la nouvelle version (releases/<id>.zip) dans son
 *               propre dossier, par tranches, pendant que l'ancienne tourne ;
 *   activer   : vérifie la version, puis bascule d'un coup (renommage
 *               atomique de releases/current.php) — aucune requête ne tombe
 *               sur un site à moitié envoyé ;
 *   revenir   : rebascule sur la version précédente (en cas de problème) ;
 *   nettoyer  : supprime les anciennes versions (on en garde quelques-unes).
 *
 * Les valeurs entre doubles soulignés sont remplies par deploy.sh. Le script
 * refuse de servir au-delà de 30 minutes et se supprime alors lui-même.
 */

const JETON = '__JETON__';
const RELEASES = '__RELEASES__';
const PARTAGE = '__PARTAGE__';
const VERSIONS_GARDEES = 3;
const BUDGET_SECONDES = 15;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function repondre(int $code, array $donnees): never
{
    http_response_code($code);
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (filemtime(__FILE__) < time() - 1800) {
    @unlink(__FILE__);
    repondre(410, ['erreur' => 'Agent expiré.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ! hash_equals(JETON, (string) ($_POST['jeton'] ?? ''))) {
    repondre(404, ['erreur' => 'Introuvable.']);
}

@set_time_limit(120);
$debut = microtime(true);
$action = (string) ($_POST['action'] ?? '');
$release = (string) ($_POST['release'] ?? '');
if ($release !== '' && ! preg_match('/^\d{8}-\d{6}$/', $release)) {
    repondre(422, ['erreur' => 'Nom de version invalide.']);
}

/** Version en ligne (chemin complet), ou null. */
function versionEnLigne(): ?string
{
    $chemin = @include RELEASES.'/current.php';

    return is_string($chemin) ? $chemin : null;
}

/** Écrit un fichier d'un coup : fichier temporaire puis renommage (jamais lu à moitié). */
function ecrireAtomique(string $fichier, string $contenu, int $mode = 0644): void
{
    $temporaire = $fichier.'.tmp-'.bin2hex(random_bytes(4));
    if (file_put_contents($temporaire, $contenu) === false) {
        repondre(500, ['erreur' => "Écriture impossible : {$fichier}"]);
    }
    @chmod($temporaire, $mode);
    if (! rename($temporaire, $fichier)) {
        @unlink($temporaire);
        repondre(500, ['erreur' => "Remplacement impossible : {$fichier}"]);
    }
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($fichier, true);
    }
}

/** Fait pointer la production sur ce dossier. */
function basculer(string $dossier): void
{
    ecrireAtomique(RELEASES.'/current.php', '<?php return '.var_export($dossier, true).";\n");
}

/** Supprime un dossier, dans la limite du temps restant ; true si tout est supprimé. */
function supprimer(string $chemin, float $limite): bool
{
    if (is_link($chemin) || is_file($chemin)) {
        return @unlink($chemin) || ! file_exists($chemin);
    }
    if (! is_dir($chemin)) {
        return true;
    }
    foreach (scandir($chemin) ?: [] as $nom) {
        if ($nom === '.' || $nom === '..') {
            continue;
        }
        if (microtime(true) > $limite || ! supprimer($chemin.'/'.$nom, $limite)) {
            return false;
        }
    }

    return @rmdir($chemin);
}

switch ($action) {
    case 'etat':
        repondre(200, ['php' => PHP_VERSION, 'zip' => class_exists(ZipArchive::class), 'enLigne' => versionEnLigne()]);

    case 'extraire':
        $archive = RELEASES."/{$release}.zip";
        $dossier = RELEASES."/{$release}";
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            repondre(500, ['erreur' => "Archive illisible : {$archive}"]);
        }
        if (! is_dir($dossier) && ! @mkdir($dossier, 0755, true)) {
            repondre(500, ['erreur' => "Dossier impossible à créer : {$dossier}"]);
        }
        $total = $zip->numFiles;
        $index = max(0, (int) ($_POST['depuis'] ?? 0));
        while ($index < $total && microtime(true) - $debut < BUDGET_SECONDES) {
            $noms = [];
            for ($i = $index; $i < min($total, $index + 400); $i++) {
                $noms[] = $zip->getNameIndex($i);
            }
            if (! $zip->extractTo($dossier, $noms)) {
                repondre(500, ['erreur' => "Extraction impossible à partir du fichier n° {$index}."]);
            }
            $index += count($noms);
        }
        $zip->close();
        repondre(200, ['suivant' => $index, 'total' => $total, 'fini' => $index >= $total]);

    case 'activer':
        $dossier = RELEASES."/{$release}";
        foreach (['vendor/autoload.php', 'bootstrap/app.php', 'public/index.php', 'artisan', 'public/build/manifest.json'] as $fichier) {
            if (! is_file("{$dossier}/{$fichier}")) {
                repondre(422, ['erreur' => "Version incomplète : {$fichier} manque."]);
            }
        }
        // Le .env et storage/ restent ceux du dossier partagé.
        file_put_contents("{$dossier}/.presslink-shared", PARTAGE);
        @mkdir("{$dossier}/bootstrap/cache", 0755, true);
        @unlink(RELEASES."/{$release}.zip");

        $precedente = versionEnLigne();
        if ($precedente !== null && $precedente !== $dossier) {
            ecrireAtomique(RELEASES.'/previous.php', '<?php return '.var_export($precedente, true).";\n");
        }
        basculer($dossier);

        // Lanceurs : le site (webroot/index.php) et les commandes / tâche cron
        // (artisan du dossier partagé) suivent la version en ligne.
        ecrireAtomique(__DIR__.'/index.php', <<<PHP
<?php

// Lance la version en ligne de PressLink : le dossier choisi par le dernier
// déploiement (releases/current.php, bascule instantanée), sinon le code d'origine.
\$release = @include '__RELEASES__/current.php';
if (! is_string(\$release) || ! is_file(\$release.'/public/index.php')) {
    \$release = '__PARTAGE__';
}

require \$release.'/public/index.php';

PHP);
        ecrireAtomique(PARTAGE.'/artisan', <<<PHP
#!/usr/bin/env php
<?php

// Commandes et tâche cron (schedule:run) : exécutées par la version en ligne
// (releases/current.php), sinon par le code de ce dossier.
use Symfony\Component\Console\Input\ArgvInput;

define('LARAVEL_START', microtime(true));

\$release = @include '__RELEASES__/current.php';
\$base = is_string(\$release) && is_file(\$release.'/artisan') ? \$release : __DIR__;

require \$base.'/vendor/autoload.php';
\$app = require_once \$base.'/bootstrap/app.php';

exit(\$app->handleCommand(new ArgvInput));

PHP, 0755);
        repondre(200, ['enLigne' => $dossier, 'precedente' => $precedente]);

    case 'revenir':
        $precedente = @include RELEASES.'/previous.php';
        if (! is_string($precedente) || ! is_file($precedente.'/public/index.php')) {
            repondre(422, ['erreur' => 'Aucune version précédente disponible.']);
        }
        $actuelle = versionEnLigne();
        basculer($precedente);
        if ($actuelle !== null) {
            ecrireAtomique(RELEASES.'/previous.php', '<?php return '.var_export($actuelle, true).";\n");
        }
        repondre(200, ['enLigne' => $precedente, 'remplacee' => $actuelle]);

    case 'nettoyer':
        $protegees = array_filter([versionEnLigne(), @include RELEASES.'/previous.php'], 'is_string');
        $versions = array_values(array_filter(scandir(RELEASES) ?: [], fn ($nom) => preg_match('/^\d{8}-\d{6}$/', $nom) && is_dir(RELEASES."/{$nom}")));
        rsort($versions);
        $limite = $debut + BUDGET_SECONDES;
        $reste = false;
        foreach (array_slice($versions, VERSIONS_GARDEES) as $nom) {
            if (in_array(RELEASES."/{$nom}", $protegees, true)) {
                continue;
            }
            if (! supprimer(RELEASES."/{$nom}", $limite)) {
                $reste = true;
                break;
            }
        }
        // Archives oubliées par un déploiement interrompu.
        foreach (glob(RELEASES.'/*.zip') ?: [] as $archive) {
            if (filemtime($archive) < time() - 3600) {
                @unlink($archive);
            }
        }
        repondre(200, ['fini' => ! $reste]);

    default:
        repondre(422, ['erreur' => 'Action inconnue.']);
}
