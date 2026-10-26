<?php

namespace Modules\Quality\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Models\PurchaseOrder;

class QualityController extends Controller
{
    public function purchaseOrder(PurchaseOrder $order)
    {
        $order->load(['vendor', 'lines.part', 'lines.invoiceLines', 'invoices.lines.lots']);

        return view('quality::documents.purchase-order', [
            'order' => $order,
        ]);
    }

    public function purchaseInvoice(PurchaseInvoice $invoice)
    {
        $invoice->load([
            'vendor',
            'purchaseOrder.lines',
            'lines.part',
            'lines.purchaseOrderLine',
            'lines.lots',
        ]);

        return view('quality::documents.purchase-invoice', [
            'invoice' => $invoice,
        ]);
    }
}
