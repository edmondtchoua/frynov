<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Services\InvoicePdfRenderer;
use App\Modules\Accounting\Services\InvoiceService;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * RC-30 — factures client : liste, détail, brouillon (+ depuis commande), émission, allocation
 * de paiement, PDF. RBAC porté par les routes.
 */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $service,
        private readonly InvoicePdfRenderer $pdf,
    ) {}

    /** GET /api/accounting/invoices?status=&customer_id= */
    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::query()
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('customer_id'), fn ($q, $c) => $q->where('customer_id', $c))
            ->withCount('lines')
            ->latest('created_at')
            ->paginate((int) $request->integer('per_page', 30));

        return response()->json($invoices);
    }

    /** GET /api/accounting/invoices/{id} */
    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => Invoice::with('lines')->findOrFail($id)]);
    }

    /** POST /api/accounting/invoices */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id'            => ['nullable', 'uuid'],
            'customer_name'          => ['nullable', 'string', 'max:255'],
            'currency'               => ['nullable', 'string', 'size:3'],
            'due_date'               => ['nullable', 'date'],
            'order_id'               => ['nullable', 'uuid'],
            'notes'                  => ['nullable', 'string'],
            'lines'                  => ['required', 'array', 'min:1'],
            'lines.*.label'          => ['required', 'string', 'max:255'],
            'lines.*.quantity'       => ['required', 'integer', 'min:1'],
            'lines.*.unit_price_minor' => ['required', 'integer', 'min:0'],
            'lines.*.discount_bp'    => ['nullable', 'integer', 'min:0', 'max:10000'],
            'lines.*.tax_id'         => ['nullable', 'uuid'],
        ]);

        $invoice = $this->service->createDraft($data, $request->user()->tenant_id, $request->user()->id);

        return response()->json(['data' => $invoice], 201);
    }

    /** POST /api/accounting/invoices/from-order/{orderId} */
    public function fromOrder(Request $request, string $orderId): JsonResponse
    {
        $order = Order::find($orderId);
        if (! $order) {
            return response()->json(['message' => 'Commande introuvable.'], 404);
        }

        $invoice = $this->service->fromOrder($order, $request->user()->tenant_id, $request->user()->id);

        return response()->json(['data' => $invoice], 201);
    }

    /** POST /api/accounting/invoices/{id}/issue */
    public function issue(Request $request, string $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);
        $invoice = $this->service->issue($invoice, $request->user()->id);

        return response()->json(['message' => 'Facture émise.', 'data' => $invoice->load('lines')]);
    }

    /** POST /api/accounting/invoices/{id}/payments — alloue un paiement existant à la facture. */
    public function allocate(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'payment_id'   => ['required', 'uuid'],
            'amount_minor' => ['required', 'integer', 'min:1'],
        ]);

        $invoice = Invoice::findOrFail($id);
        $payment = Payment::where('tenant_id', $request->user()->tenant_id)->findOrFail($data['payment_id']);

        $allocation = $this->service->allocatePayment($payment, $invoice, $data['amount_minor'], $request->user()->id);

        return response()->json([
            'message' => 'Paiement alloué.',
            'data'    => ['allocation' => $allocation, 'invoice' => $invoice->fresh()],
        ], 201);
    }

    /** GET /api/accounting/invoices/{id}/pdf */
    public function pdf(string $id): Response
    {
        return $this->pdf->download(Invoice::with('lines')->findOrFail($id));
    }
}
