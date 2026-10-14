<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Réduction des images envoyées (photos de profil, logos) avant stockage :
 * côté le plus long ramené à {@see self::MAX_SIDE} px, conversion en WebP
 * (transparence conservée pour les logos), métadonnées EXIF supprimées
 * (dont la position GPS des photos prises au téléphone).
 *
 * Utilise uniquement l'extension GD, disponible sur l'hébergement mutualisé.
 */
final class ImageOptimizer
{
    public const MAX_SIDE = 512;

    public const QUALITY = 80;

    /** Au-delà, décoder l'image dépasserait la mémoire PHP de l'hébergement (384 Mo). */
    public const MAX_DIMENSION = 6000;

    /**
     * Enregistre une version réduite du fichier et renvoie son chemin. Si GD
     * ne sait pas lire l'image, le fichier d'origine est stocké tel quel.
     */
    public static function store(UploadedFile $file, string $directory, string $disk = 'public', int $maxSide = self::MAX_SIDE): string
    {
        $webp = self::toWebp((string) $file->getRealPath(), $maxSide);

        if ($webp === null) {
            return $file->store($directory, $disk);
        }

        $path = trim($directory, '/').'/'.Str::random(40).'.webp';
        Storage::disk($disk)->put($path, $webp);

        return $path;
    }

    public static function toWebp(string $sourcePath, int $maxSide = self::MAX_SIDE): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return null;
        }

        $contents = @file_get_contents($sourcePath);
        $image = $contents === false ? false : @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            return null;
        }

        $image = self::applyExifOrientation($image, $sourcePath);
        imagepalettetotruecolor($image);

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxSide / max($width, $height));

        if ($scale < 1) {
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        } else {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        ob_start();
        $ok = imagewebp($image, null, self::QUALITY);
        $webp = ob_get_clean();
        imagedestroy($image);

        return $ok && $webp !== false && $webp !== '' ? $webp : null;
    }

    /** Les photos de téléphone sont souvent stockées « couchées » avec une indication EXIF. */
    private static function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated instanceof GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }
}
