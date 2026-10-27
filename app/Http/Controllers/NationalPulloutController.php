<?php

namespace App\Http\Controllers;

use App\Models\NationalProduct;
use App\Models\NationalPullout;
use App\Models\StoreHub;
use App\Support\NationalPulloutWorkbook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NationalPulloutController extends Controller
{
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
            'stock' => (int) $product->stock,
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
            'remarks' => ['nullable', 'string', 'max:150'],
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

        // Keep this flow consistent with Event: save the complete pull-out,
        // then return the user to Transaction Logs with the new record in view.
        return redirect()->route('inventory-transactions.index', [
            'hub_id' => $hub->id,
            'type' => 'national_bookstore_pullout',
            'national_pullout_saved' => 1,
            'reference' => $validated['po_number'],
        ])->with('success', 'National Bookstore pull-out recorded successfully.');
    }

    public function worksheet(Request $request)
    {
        return $this->excelWorksheet($request);
    }

    public function excelWorksheet(Request $request)
    {
        $this->headOffice($request);
        $validated = $request->validate([
            'include_products' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:150'],
        ]);
        $items = $request->boolean('include_products', true)
            ? NationalProduct::query()
                ->where('status', 'active')
                ->orderByRaw('LENGTH(item_id)')
                ->orderBy('item_id')
                ->get()
                ->map(fn (NationalProduct $product) => [
                    'name' => $product->name,
                    'item_id' => $product->item_id,
                    'quantity' => '',
                    'purpose' => 'National Bookstore Pullout',
                    'physical_stock' => (int) $product->stock,
                    'actual_pullout' => '',
                ])->all()
            : [];
        $path = NationalPulloutWorkbook::create($items, [
            'po_number' => trim((string) $request->input('po_number')),
            'occurred_on' => (string) $request->input('occurred_on'),
            'remarks' => trim((string) ($validated['remarks'] ?? '')),
        ]);

        return response()->download(
            $path,
            'NATIONAL_BOOKSTORE_PULLOUT_WORKSHEET_'.now()->format('Ymd_His').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function export(NationalPullout $nationalPullout, Request $request)
    {
        $hub = $this->headOffice($request);
        abort_unless((int) $nationalPullout->store_hub_id === (int) $hub->id, 403);
        $nationalPullout->load('items');
        $items = $nationalPullout->items->map(fn ($item) => [
            'name' => $item->product_name,
            'item_id' => $item->item_id,
            'quantity' => $item->quantity,
            'purpose' => $item->purpose,
            'physical_stock' => $item->physical_stock,
            'actual_pullout' => $item->actual_pullout,
        ])->all();
        $path = NationalPulloutWorkbook::create($items, [
            'po_number' => $nationalPullout->po_number,
            'occurred_on' => $nationalPullout->occurred_on->format('Y-m-d'),
            'remarks' => $nationalPullout->remarks ?? '',
        ]);

        return response()->download(
            $path,
            'NATIONAL_BOOKSTORE_PULLOUT_'.Str::slug($nationalPullout->po_number, '_').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function exportExcelWorksheet(Request $request)
    {
        $this->headOffice($request);
        $validated = $request->validate([
            'po_number' => ['nullable', 'string', 'max:100'],
            'occurred_on' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:national_products,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.actual_pullout' => ['nullable', 'integer', 'min:1'],
        ]);

        $products = NationalProduct::query()
            ->where('status', 'active')
            ->whereIn('id', collect($validated['items'])->pluck('product_id'))
            ->get()
            ->keyBy('id');
        if ($products->count() !== count($validated['items'])) {
            throw ValidationException::withMessages(['items' => 'One or more National products are inactive or unavailable.']);
        }

        $items = collect($validated['items'])->map(function (array $item) use ($products) {
            $product = $products->get((int) $item['product_id']);

            return [
                'name' => $product->name,
                'item_id' => $product->item_id,
                'quantity' => $item['quantity'] ?? '',
                'purpose' => 'National Bookstore Pullout',
                'physical_stock' => (int) $product->stock,
                'actual_pullout' => $item['actual_pullout'] ?? '',
            ];
        })->all();
        $path = NationalPulloutWorkbook::create($items, $validated);

        return response()->download(
            $path,
            'NATIONAL_BOOKSTORE_PULLOUT_'.now()->format('Ymd_His').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function importExcelWorksheet(Request $request)
    {
        $this->headOffice($request);
        $request->validate([
            'worksheet' => ['required', 'file', 'max:10240'],
        ]);

        $worksheet = $request->file('worksheet');
        if (strtolower($worksheet->getClientOriginalExtension()) !== 'xlsx') {
            throw ValidationException::withMessages([
                'worksheet' => 'Please choose a valid .xlsx Excel worksheet.',
            ]);
        }

        try {
            $data = NationalPulloutWorkbook::read($worksheet->getRealPath());
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'worksheet' => 'The Excel worksheet could not be read. Use the downloaded National Bookstore Pullout template.',
            ]);
        }

        return response()->json($data);
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
