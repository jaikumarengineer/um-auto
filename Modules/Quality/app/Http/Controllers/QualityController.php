<?php

namespace Modules\Quality\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MediaStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Models\InspectionStandard;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Support\InspectionReportWorkbook;
use Modules\Quality\Support\InspectionStandardWorkbook;

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

    public function partStandardDocument(IncomingPart $part)
    {
        $standard = $part->editableStandard();
        abort_unless($standard?->document_path, 404);

        $name = $standard->document_name ?: basename($standard->document_path);

        foreach (['public_uploads', 'local'] as $disk) {
            if (Storage::disk($disk)->exists($standard->document_path)) {
                return Storage::disk($disk)->download($standard->document_path, $name);
            }
        }

        abort(404);
    }

    public function partStandardPdf(IncomingPart $part)
    {
        $standard = $this->savedStandard($part);

        return Pdf::loadView('quality::documents.inspection-standard', [
            'standard' => $standard,
            'part' => $part,
        ])->setPaper('a4', 'landscape')->download($this->standardFilename($part, 'pdf'));
    }

    public function partStandardExcel(IncomingPart $part)
    {
        $standard = $this->savedStandard($part);

        return InspectionStandardWorkbook::download($standard, $part, $this->standardFilename($part, 'xlsx'));
    }

    public function reportPdf(InspectionReport $report)
    {
        $report = $this->savedReport($report);

        return Pdf::loadView('quality::documents.inspection-report', [
            'report' => $report,
            'images' => $this->reportImagePaths($report),
        ])->setPaper('a4', 'landscape')->download($this->reportFilename($report, 'pdf'));
    }

    public function reportExcel(InspectionReport $report)
    {
        $report = $this->savedReport($report);

        return InspectionReportWorkbook::download($report, $this->reportFilename($report, 'xlsx'));
    }

    private function savedReport(InspectionReport $report): InspectionReport
    {
        return $report->load([
            'lot.part',
            'lot.vendor',
            'standard',
            'samplingPlan',
            'samples',
            'results.characteristic',
            'inspector',
            'verifier',
        ]);
    }

    /**
     * Dompdf clips local Windows paths, so each photo is embedded as a data URI.
     *
     * @return list<string>
     */
    private function reportImagePaths(InspectionReport $report): array
    {
        $encoded = [];

        foreach ($report->images ?? [] as $image) {
            $absolute = $this->reportImageFile($image);

            if ($absolute === null) {
                continue;
            }

            $dataUri = $this->imageDataUri($absolute);

            if ($dataUri !== null) {
                $encoded[] = $dataUri;
            }
        }

        return $encoded;
    }

    private function reportImageFile(mixed $image): ?string
    {
        $url = is_array($image) ? (string) ($image['url'] ?? '') : (string) $image;
        $url = ltrim(str_replace('\\', '/', $url), '/');
        $id = is_array($image) ? ($image['id'] ?? null) : null;

        if ($id) {
            $media = MediaStorage::query()->find($id);
            $diskName = $media?->disk ?: 'public_uploads';

            if ($media && $media->path && Storage::disk($diskName)->exists($media->path)) {
                return Storage::disk($diskName)->path($media->path);
            }
        }

        $relative = preg_replace('#^(storage/|uploads/)#', '', $url) ?? '';
        $candidates = array_filter([
            $url !== '' ? public_path($url) : null,
            $relative !== '' ? public_path('uploads/'.$relative) : null,
            $relative !== '' ? storage_path('app/public/'.$relative) : null,
        ]);

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        if ($relative !== '') {
            foreach (['public_uploads', 'public'] as $diskName) {
                $disk = Storage::disk($diskName);

                if ($disk->exists($relative)) {
                    return $disk->path($relative);
                }
            }
        }

        return null;
    }

    private function imageDataUri(string $absolute): ?string
    {
        $extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));

        if ($extension === 'webp' && function_exists('imagecreatefromwebp')) {
            $webp = @imagecreatefromwebp($absolute);

            if ($webp !== false) {
                ob_start();
                imagejpeg($webp, null, 85);
                $jpeg = ob_get_clean();
                imagedestroy($webp);

                if (is_string($jpeg) && $jpeg !== '') {
                    return 'data:image/jpeg;base64,'.base64_encode($jpeg);
                }
            }
        }

        $contents = @file_get_contents($absolute);

        if ($contents === false || $contents === '') {
            return null;
        }

        $mime = match ($extension) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    private function reportFilename(InspectionReport $report, string $extension): string
    {
        $code = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $report->report_number) ?: 'inspection-report';

        return $code.'.'.$extension;
    }

    private function savedStandard(IncomingPart $part): InspectionStandard
    {
        $standard = $part->editableStandard();
        abort_unless($standard, 404);

        return $standard->load('characteristics');
    }

    private function standardFilename(IncomingPart $part, string $extension): string
    {
        $code = preg_replace('/[^A-Za-z0-9._-]+/', '-', $part->part_number) ?: 'standard';

        return $code.'-inspection-standard.'.$extension;
    }
}
