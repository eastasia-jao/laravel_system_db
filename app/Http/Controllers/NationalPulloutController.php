<?php

namespace App\Http\Controllers;

use App\Models\NationalProduct;
use App\Models\NationalPullout;
use App\Models\StoreHub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NationalPulloutController extends Controller
{
    private const ITEM_HEADERS = [
        'Product Item Name', 'Qty', 'Actual Pull-out',
    ];

    public function create(Request $request)
    {
        $hub = $this->headOffice($request);
        $products = NationalProduct::query()
            ->where('status', 'active')
            ->orderByRaw('LENGTH(item_id)')
            ->orderBy('item_id')
            ->get();

        $productOptions = $products->map(fn (NationalProduct $product) => [
            'id' => $product->id,
            'item_id' => $product->item_id,
            'name' => $product->name,
        ])->values();

        return view('inventory-transactions.forms.national-pullout', compact('hub', 'products', 'productOptions'));
    }

    public function store(Request $request)
    {
        $hub = $this->headOffice($request);
        $request->merge(['po_number' => trim((string) $request->input('po_number'))]);
        $validated = $request->validate([
            'po_number' => ['required', 'string', 'max:100', Rule::unique('national_pullouts', 'po_number')],
            'occurred_on' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:national_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.actual_pullout' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($validated, $request, $hub) {
            $productIds = collect($validated['items'])->pluck('product_id');
            $products = NationalProduct::query()
                ->where('status', 'active')
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $productIds->unique()->count()) {
                throw ValidationException::withMessages(['items' => 'One or more National products are inactive or unavailable.']);
            }

            foreach ($validated['items'] as $index => $item) {
                $product = $products->get((int) $item['product_id']);
                if ((int) $item['actual_pullout'] > (int) $item['quantity']) {
                    throw ValidationException::withMessages(["items.{$index}.actual_pullout" => 'Actual Pull-out cannot exceed Qty.']);
                }
                if ((int) $item['actual_pullout'] > (int) $product->stock) {
                    throw ValidationException::withMessages(["items.{$index}.actual_pullout" => "Actual Pull-out exceeds the physical stock for {$product->name}."]);
                }
            }

            $pullout = NationalPullout::create([
                'po_number' => trim($validated['po_number']),
                'occurred_on' => $validated['occurred_on'],
                'remarks' => $validated['remarks'] ?? null,
                'store_hub_id' => $hub->id,
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $item) {
                $product = $products->get((int) $item['product_id']);
                $physicalStock = (int) $product->stock;
                $pullout->items()->create([
                    'national_product_id' => $product->id,
                    'item_id' => $product->item_id,
                    'product_name' => $product->name,
                    'quantity' => (int) $item['quantity'],
                    'unit_type' => $product->unit_type,
                    'purpose' => 'National Bookstore Pullout',
                    'physical_stock' => $physicalStock,
                    'actual_pullout' => (int) $item['actual_pullout'],
                ]);
                $product->decrement('stock', (int) $item['actual_pullout']);
            }
        });

        return redirect()->route('inventory-transactions.index', [
            'hub_id' => $hub->id,
            'type' => 'national_bookstore_pullout',
        ]);
    }

    public function worksheet(Request $request)
    {
        $hub = $this->headOffice($request);
        $products = NationalProduct::query()->where('status', 'active')->orderByRaw('LENGTH(item_id)')->orderBy('item_id');

        return $this->csvResponse(
            'NATIONAL_BOOKSTORE_PULLOUT_WORKSHEET_'.now()->format('Ymd_His').'.csv',
            '',
            '',
            '',
            function ($file) use ($products) {
                $products->chunk(250, function ($chunk) use ($file) {
                    foreach ($chunk as $product) {
                        fputcsv($file, [$product->name, '', '']);
                    }
                });
            }
        );
    }

    public function export(NationalPullout $nationalPullout, Request $request)
    {
        $hub = $this->headOffice($request);
        abort_unless((int) $nationalPullout->store_hub_id === (int) $hub->id, 403);
        $nationalPullout->load('items');
        $filename = 'NATIONAL_BOOKSTORE_PULLOUT_'.Str::slug($nationalPullout->po_number, '_').'.csv';

        return $this->csvResponse(
            $filename,
            $nationalPullout->po_number,
            $nationalPullout->occurred_on->format('Y-m-d'),
            $nationalPullout->remarks ?? '',
            function ($file) use ($nationalPullout) {
                foreach ($nationalPullout->items as $item) {
                    fputcsv($file, [
                        $item->product_name,
                        $item->quantity,
                        $item->actual_pullout,
                    ]);
                }
            }
        );
    }

    private function csvResponse(string $filename, string $poNumber, string $date, string $remarks, callable $writeItems)
    {
        return response()->streamDownload(function () use ($poNumber, $date, $remarks, $writeItems) {
            $file = fopen('php://output', 'wb');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['P.O. #:', $poNumber]);
            fputcsv($file, ['Date:', $date]);
            fputcsv($file, ['Remarks:', $remarks]);
            fputcsv($file, []);
            fputcsv($file, self::ITEM_HEADERS);
            $writeItems($file);
            fclose($file);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function headOffice(Request $request): StoreHub
    {
        $user = $request->user();
        abort_unless(in_array($user?->role, ['admin', 'inventory_staff'], true), 403);
        $query = StoreHub::query()->where('status', 'active')->where('is_head_office', true);
        if ($user->role === 'inventory_staff') {
            $query->whereKey($user->hub_id);
        }
        $hub = $request->integer('hub_id')
            ? $query->whereKey($request->integer('hub_id'))->first()
            : $query->orderBy('name')->first();
        abort_unless($hub, 403, 'National Bookstore Pullout is available only in an active Head Office mode.');

        return $hub;
    }
}
