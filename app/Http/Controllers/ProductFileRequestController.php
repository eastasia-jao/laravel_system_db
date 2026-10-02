<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessProductFileRequest;
use App\Models\Product;
use App\Models\ProductFileRequest;
use App\Models\InventoryTransaction;
use App\Notifications\InventoryWorkflowNotification;
use App\Support\CsvIdentifier;
use App\Support\ProductExportFilename;
use App\Support\ProductExportWorkbook;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductFileRequestController extends Controller
{
    private function ensureAccess(): void
    {
        $user = auth()->user();
        abort_unless(in_array($user->role, ['admin', 'inventory_staff'], true), 403);
    }

    public function index(Request $request)
    {
        $this->ensureAccess();
        $allTransferRequests = InventoryTransaction::with([
            'product.catalogProduct', 'sourceHub', 'targetHub', 'creator', 'reviewer',
        ])
            ->where('type', 'branch_transfer')
            ->latest('id')
            ->get()
            ->groupBy(fn (InventoryTransaction $entry) => $entry->transfer_batch_id ?: $entry->reference)
            ->values();
        $transferRequestCounts = [
            'total' => $allTransferRequests->count(),
            'pending' => $allTransferRequests->filter(fn ($group) => $group->first()?->status === 'pending')->count(),
            'approved' => $allTransferRequests->filter(fn ($group) => $group->first()?->status === 'approved')->count(),
            'rejected' => $allTransferRequests->filter(fn ($group) => $group->first()?->status === 'rejected')->count(),
        ];
        $page = LengthAwarePaginator::resolveCurrentPage('transfer_page');
        $transferRequests = (new LengthAwarePaginator(
            $allTransferRequests->forPage($page, 10)->values(),
            $allTransferRequests->count(),
            10,
            $page,
            ['path' => $request->url(), 'pageName' => 'transfer_page']
        ))->withQueryString();

        return view('products.file-requests', compact('transferRequests', 'transferRequestCounts'));
    }

    public function prepareCsv(string $csv): string
    {
        $this->validateCsv($csv);

        return str_starts_with($csv, "\xEF\xBB\xBF") ? substr($csv, 3) : $csv;
    }

    private function validateCsv(string $csv): void
    {
        if (! mb_check_encoding($csv, 'UTF-8')) {
            throw ValidationException::withMessages(['file' => 'Save the product file as CSV UTF-8 before submitting.']);
        }
        $stream = fopen('php://temp', 'r+');
        try {
            fwrite($stream, $csv);
            rewind($stream);
            $header = fgetcsv($stream);
            if ($header) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }
            $normalizedHeader = $header
                ? array_map(fn ($value) => strtolower(trim((string) $value)), $header)
                : [];
            $allowedColumns = ['id', 'item id', 'name', 'description', 'barcode', 'brand', 'retail group', 'retail department', 'cost price', 'retail price', 'sales price', 'wholesale price', 'shopee price', 'lazada price', 'tiktok price', 'stock', 'unit type'];
            if (! in_array('item id', $normalizedHeader, true)
                || count(array_unique($normalizedHeader)) !== count($normalizedHeader)
                || array_diff($normalizedHeader, $allowedColumns)
                || ! array_intersect($normalizedHeader, ['stock', 'cost price', 'retail price', 'sales price', 'wholesale price', 'shopee price', 'lazada price', 'name', 'description', 'barcode', 'brand', 'retail group', 'retail department', 'unit type'])) {
                throw ValidationException::withMessages(['file' => 'Include Item ID and at least one product field to update. Omitted fields are preserved.']);
            }
            $stockColumn = array_search('stock', $normalizedHeader, true);
            $count = 0;
            $line = 1;
            $seen = [];
            while (($row = fgetcsv($stream)) !== false) {
                $line++;
                if (! array_filter($row)) {
                    continue;
                }
                $id = CsvIdentifier::read($row[array_search('item id', $normalizedHeader, true)] ?? '', "Row {$line} Item ID");
                if (in_array('barcode', $normalizedHeader, true)) {
                    CsvIdentifier::read($row[array_search('barcode', $normalizedHeader, true)] ?? '', "Row {$line} Barcode");
                }
                if (count($row) !== count($normalizedHeader) || ! $id || isset($seen[$id])
                    || ($stockColumn !== false && ! preg_match('/^\d+$/', trim($row[$stockColumn] ?? '')))) {
                    throw ValidationException::withMessages(['file' => 'Each row must match the CSV header, contain a unique Item ID, and use a nonnegative whole number for Stock when Stock is included.']);
                }
                $seen[$id] = true;
                $count++;
            }
            if (! $count) {
                throw ValidationException::withMessages(['file' => 'Include at least one product row.']);
            }
        } finally {
            fclose($stream);
        }
    }

    public function show(ProductFileRequest $fileRequest)
    {
        $this->ensureAccess();
        $rows = [];
        $headers = [];
        $page = max(1, (int) request('page', 1));
        $rowCount = 0;
        if ($fileRequest->type === 'import') {
            $perPage = 10;
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $fileRequest->csv);
            rewind($stream);
            $headers = fgetcsv($stream) ?: [];
            if ($headers) {
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            }
            while (($row = fgetcsv($stream)) !== false) {
                if (array_filter($row)) {
                    if ($rowCount >= ($page - 1) * $perPage && $rowCount < $page * $perPage) {
                        $rows[] = $row;
                    }
                    $rowCount++;
                }
            }
            fclose($stream);
        }
        $products = Product::where('store_hub_id', $fileRequest->store_hub_id)
            ->whereIn('id', $fileRequest->product_ids ?? [])
            ->orderBy('id')->paginate(10)->withQueryString();
        $rowPages = (new LengthAwarePaginator($rows, $rowCount, 10, $page, ['path' => request()->url()]))->withQueryString();

        return view('products.file-request', compact('fileRequest', 'rows', 'headers', 'products', 'rowPages', 'rowCount'));
    }

    public function review(Request $request, ProductFileRequest $fileRequest)
    {
        $this->ensureAccess();
        abort_unless(auth()->user()->can('verify-inventory'), 403);
        $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])]]);
        if ($request->input('decision') === 'rejected') {
            abort_if(in_array($fileRequest->processing_status, ['queued', 'processing'], true), 409, 'This request is already being processed.');

            return $this->processReview($request, $fileRequest);
        }
        DB::transaction(function () use ($fileRequest, $request) {
            $record = ProductFileRequest::whereKey($fileRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'pending' && ! in_array($record->processing_status, ['queued', 'processing'], true), 409, 'This request has already been reviewed or queued.');
            $record->update(['processing_status' => 'queued', 'processing_error' => null, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
            ProcessProductFileRequest::dispatch($record->id, auth()->id(), $request->ip() ?? '127.0.0.1');
        });

        return back()->with('success', 'Approved for processing. The request will update when processing finishes.');
    }

    public function processReview(Request $request, ProductFileRequest $fileRequest)
    {
        abort_unless(auth()->user()->can('verify-inventory'), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'rejection_reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($fileRequest, $data, $request) {
            $record = ProductFileRequest::whereKey($fileRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'pending', 409, 'This request has already been reviewed.');
            if ($data['decision'] === 'rejected') {
                abort_if(in_array($record->processing_status, ['queued', 'processing'], true), 409, 'This request is already being processed.');
            }
            if ($data['decision'] === 'approved') {
                $controller = app(ProductController::class);
                if ($record->type === 'import') {
                    $csv = $record->csv;
                    $this->validateCsv($csv);
                    $temp = tmpfile();
                    try {
                        fwrite($temp, $csv);
                        $upload = new UploadedFile(stream_get_meta_data($temp)['uri'], $record->file_name, 'text/csv', null, true);
                        $operation = Request::create('/', 'POST', [], [], ['file' => $upload], $request->server->all());
                        $controller->applyImportCsv($operation, $record->store_hub_id);
                    } finally {
                        fclose($temp);
                    }
                } else {
                    $ids = $record->product_ids;
                    abort_unless(Product::where('store_hub_id', $record->store_hub_id)->whereIn('id', $ids)->count() === count($ids), 422, 'A selected product is no longer available. Reject this request and submit a new one.');
                    $hub = $record->hub()->firstOrFail();
                    $filename = $record->file_name ?: ProductExportFilename::make($hub, 'csv');
                    $response = $controller->prepareExport(Request::create('/', 'GET', ['product_ids' => $ids], [], [], $request->server->all()), $record->store_hub_id, $filename);
                    ob_start();
                    try {
                        $response->sendContent();
                        $csv = ob_get_contents();
                    } finally {
                        ob_end_clean();
                    }
                    $record->storeCsv($csv);
                    $record->file_name = $filename;
                }
            }
            $record->fill([
                'processing_status' => $data['decision'] === 'approved' ? 'completed' : null,
                'status' => $data['decision'], 'reviewed_by' => auth()->id(), 'reviewed_at' => now(),
                'rejection_reason' => $data['decision'] === 'rejected' ? $data['rejection_reason'] : null,
            ])->save();
            $record->submitter->notify(new InventoryWorkflowNotification(
                'product_file_reviewed',
                "Your product {$record->type} was {$record->status}.",
                $record->store_hub_id,
                route('products.index', ['hub_id' => $record->store_hub_id])
            ));
        });

        return back()->with('success', 'Request '.$data['decision'].'.');
    }

    public function download(ProductFileRequest $fileRequest)
    {
        $this->ensureAccess();
        abort_unless($fileRequest->type === 'export' && $fileRequest->status === 'approved', 403);
        $csvFilename = $fileRequest->file_name ?: ProductExportFilename::make($fileRequest->hub()->firstOrFail(), 'csv');
        $filename = pathinfo($csvFilename, PATHINFO_FILENAME).'.xlsx';

        return response(ProductExportWorkbook::fromCsv($fileRequest->csv), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function downloadCsv(ProductFileRequest $fileRequest)
    {
        $this->ensureAccess();
        abort_unless($fileRequest->type === 'export' && $fileRequest->status === 'approved', 403);
        $filename = $fileRequest->file_name ?: ProductExportFilename::make($fileRequest->hub()->firstOrFail(), 'csv');

        return response($fileRequest->csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
