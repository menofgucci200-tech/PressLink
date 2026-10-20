<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Support\ReceiptLinks;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reçus ouverts par un lien signé (partagé au client, ou depuis son appli) :
 * pas de connexion, le lien suffit, tant qu'il est valable (voir ReceiptLinks).
 */
class PublicReceiptController extends Controller
{
    public function order(Request $request, Order $order): Response
    {
        $order->load(['pressing', 'customer', 'items', 'payments', 'creator']);

        return $this->pdf($request, 'orders.receipt', ['orders' => collect([$order]), 'payment' => null], ReceiptLinks::orderFileName($order));
    }

    public function payment(Request $request, OrderPayment $payment): Response
    {
        $payment->load(['order.pressing', 'order.customer', 'order.payments', 'recorder']);

        return $this->pdf($request, 'orders.receipt', ['orders' => collect([$payment->order]), 'payment' => $payment], ReceiptLinks::paymentFileName($payment));
    }

    /** @param  array<string, mixed>  $data */
    private function pdf(Request $request, string $view, array $data, string $filename): Response
    {
        $pdf = Pdf::loadView($view, [...$data, 'isPdf' => true, 'size' => 'a5', 'autoPrint' => false])->setPaper('a5');

        $response = $request->boolean('telecharger') ? $pdf->download($filename) : $pdf->stream($filename);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
