<?php

namespace App\Livewire\Concerns;

use App\Actions\Orders\OrderPhotos;
use App\Models\Order;
use App\Support\ImageOptimizer;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Panneau « Photos des articles » (fiche commande, dépôt rapide). Le composant
 * a aussi `$errorMessage` et ShowsToasts.
 */
trait ManagesOrderPhotos
{
    use WithFileUploads;

    /** Photo en cours d'envoi (réduite dans le navigateur, une à la fois). */
    public $photo = null;

    /** La commande à laquelle les photos sont rattachées. */
    abstract protected function photoOrder(): Order;

    /** Enregistre la photo qui vient d'être envoyée. */
    public function savePhoto(OrderPhotos $photos): void
    {
        $this->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:max_width='.ImageOptimizer::MAX_DIMENSION.',max_height='.ImageOptimizer::MAX_DIMENSION],
        ], [
            'photo.required' => 'Aucune photo reçue. Réessayez.',
            'photo.image' => 'Ce fichier n\'est pas une photo.',
            'photo.mimes' => 'Format accepté : JPG, PNG ou WebP.',
            'photo.max' => 'Cette photo est trop lourde (10 Mo maximum).',
            'photo.dimensions' => 'Cette photo est trop grande.',
        ]);

        try {
            $photos->add($this->photoOrder(), $this->photo, auth()->user());
        } catch (RuntimeException $e) {
            $this->addError('photo', $e->getMessage());
        } finally {
            $this->photo = null;
        }
    }

    public function removePhoto(int $photoId, OrderPhotos $photos): void
    {
        try {
            $photos->remove($this->photoOrder()->photos()->findOrFail($photoId), auth()->user());
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->toast('Photo supprimée.');
    }
}
