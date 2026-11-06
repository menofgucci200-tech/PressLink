<?php

namespace App\Actions\Orders;

use App\Enums\ActivityAction;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderPhoto;
use App\Models\User;
use App\Support\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Photos des articles d'une commande : ajout (réduite en WebP, sans données
 * EXIF ni position GPS, plus une miniature) et suppression (tracée au journal).
 */
class OrderPhotos
{
    /** Ceux qui enregistrent ou traitent les commandes prennent les photos. */
    public static function canAdd(User $user, Order $order): bool
    {
        return $user->can('update', $order)
            && $order->status !== OrderStatus::Annulee
            && ($user->may(Permission::OrdersCreate, $order->pressing) || $user->may(Permission::OrdersWorkshop, $order->pressing));
    }

    /** L'auteur de la photo, ou un administrateur du pressing. */
    public static function canRemove(User $user, OrderPhoto $photo): bool
    {
        return $user->can('update', $photo->order)
            && ($photo->uploaded_by === $user->id || $user->isAdminOf($photo->order->pressing));
    }

    public function add(Order $order, UploadedFile $file, User $user): OrderPhoto
    {
        if (! self::canAdd($user, $order)) {
            throw new RuntimeException('Vous ne pouvez pas ajouter de photo à cette commande.');
        }

        if ($order->photos()->count() >= OrderPhoto::MAX_PER_ORDER) {
            throw new RuntimeException('Cette commande a déjà '.OrderPhoto::MAX_PER_ORDER.' photos, le maximum.');
        }

        $full = ImageOptimizer::toWebp((string) $file->getRealPath(), OrderPhoto::MAX_SIDE);
        $thumb = ImageOptimizer::toWebp((string) $file->getRealPath(), OrderPhoto::THUMB_SIDE);

        if ($full === null || $thumb === null) {
            throw new RuntimeException('Cette image n\'a pas pu être lue. Reprenez la photo.');
        }

        $base = OrderPhoto::directoryFor($order->pressing_id, $order->id).'/'.Str::random(40);
        Storage::disk(OrderPhoto::DISK)->put("{$base}.webp", $full);
        Storage::disk(OrderPhoto::DISK)->put("{$base}-mini.webp", $thumb);

        return $order->photos()->create([
            'path' => "{$base}.webp",
            'thumb_path' => "{$base}-mini.webp",
            'size_bytes' => strlen($full) + strlen($thumb),
            'uploaded_by' => $user->id,
        ]);
    }

    public function remove(OrderPhoto $photo, User $user): void
    {
        if (! self::canRemove($user, $photo)) {
            throw new RuntimeException('Seul l\'auteur de la photo ou un administrateur peut la supprimer.');
        }

        $order = $photo->order;
        $photo->delete();

        ActivityLog::record($order->pressing_id, ActivityAction::OrderPhotoRemoved,
            "Photo supprimée de la commande {$order->order_number}", $order);
    }
}
