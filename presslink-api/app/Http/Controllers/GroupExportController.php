<?php

namespace App\Http\Controllers;

use App\Exports\GroupOrdersExport;
use App\Exports\GroupPaymentsExport;
use App\Support\GroupReport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exports consolidés du groupe (commandes ou paiements, CSV / Excel) et
 * rapport mensuel PDF — réservés à qui administre plusieurs pressings, et
 * limités à ces pressings.
 */
class GroupExportController extends Controller
{
    public function export(Request $request, string $type, string $format): Response
    {
        $user = $request->user();
        abort_unless($user->managesGroup(), 403);

        $ids = $user->managedPressingIds();
        $pressing = (int) $request->query('pressing');
        if ($pressing && in_array($pressing, $ids, true)) {
            $ids = [$pressing];
        }

        $writer = $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX;
        $month = GroupReport::month($request->query('mois'));

        return match ($type) {
            'commandes' => Excel::download(new GroupOrdersExport($ids, $request->only(['statut', 'search', 'paiement', 'du', 'au', 'retard'])), "commandes-groupe.{$format}", $writer),
            'paiements' => Excel::download(new GroupPaymentsExport($ids, $month->copy()->startOfMonth(), $month->copy()->endOfMonth()), 'paiements-groupe-'.$month->format('Y-m').".{$format}", $writer),
            default => abort(404),
        };
    }

    public function report(Request $request): Response
    {
        abort_unless($request->user()->managesGroup(), 403);
        $month = GroupReport::month($request->query('mois'));

        return GroupReport::pdf($request->user(), $month)->download(GroupReport::filename($month));
    }
}
