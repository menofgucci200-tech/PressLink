<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Exports\OrdersExport;
use App\Models\Pressing;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Export CSV/Excel/PDF des commandes du pressing courant — Dashboard
 * (aucun filtre) et Commandes (respecte les filtres affichés à l'écran).
 * Route HTTP dédiée plutôt qu'une action Livewire : un fichier binaire
 * n'a pas besoin de transiter en base64 dans une réponse AJAX.
 */
class OrderExportController extends Controller
{
    public function __invoke(Request $request, string $format): Response
    {
        $pressing = $request->user()->currentPressing();
        abort_unless($pressing !== null, 403);

        $filters = [
            'status' => $request->string('status')->toString() ?: null,
            'search' => $request->string('search')->toString() ?: null,
            'date_from' => $request->string('date_from')->toString() ?: null,
            'date_to' => $request->string('date_to')->toString() ?: null,
        ];

        return match ($format) {
            'csv' => Excel::download(new OrdersExport($pressing, $filters), 'commandes.csv', ExcelFormat::CSV),
            'xlsx' => Excel::download(new OrdersExport($pressing, $filters), 'commandes.xlsx', ExcelFormat::XLSX),
            'pdf' => $this->pdf($pressing, $filters),
            default => abort(404),
        };
    }

    /** @param  array{status: ?string, search: ?string, date_from: ?string, date_to: ?string}  $filters */
    private function pdf(Pressing $pressing, array $filters): Response
    {
        $orders = $pressing->filteredOrders($filters)->latest('dropped_off_at')->get();

        return Pdf::loadView('exports.orders-pdf', [
            'pressing' => $pressing,
            'orders' => $orders,
            'filtersSummary' => $this->describeFilters($filters),
        ])->download('commandes.pdf');
    }

    /** @param  array{status: ?string, search: ?string, date_from: ?string, date_to: ?string}  $filters */
    private function describeFilters(array $filters): ?string
    {
        $parts = [];

        if ($filters['status']) {
            $parts[] = 'statut '.OrderStatus::from($filters['status'])->label();
        }

        if ($filters['search']) {
            $parts[] = 'recherche « '.$filters['search'].' »';
        }

        if ($filters['date_from']) {
            $parts[] = 'depuis le '.Carbon::parse($filters['date_from'])->format('d/m/Y');
        }

        if ($filters['date_to']) {
            $parts[] = 'jusqu\'au '.Carbon::parse($filters['date_to'])->format('d/m/Y');
        }

        return $parts === [] ? null : implode(', ', $parts);
    }
}
