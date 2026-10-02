<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProductFileRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['product_ids' => 'array', 'reviewed_at' => 'datetime'];

    public function getCsvAttribute(?string $value): ?string
    {
        // Keep existing requests with inline CSV readable.
        if ($value !== null || ! $this->exists) {
            return $value;
        }

        $chunks = DB::table('product_file_csv_chunks')
            ->where('product_file_request_id', $this->id)->orderBy('position')->pluck('content');

        return $chunks->isEmpty() ? null : $chunks->implode('');
    }

    public function storeCsv(string $csv): void
    {
        DB::transaction(function () use ($csv) {
            DB::table('product_file_csv_chunks')->where('product_file_request_id', $this->id)->delete();
            $position = 0;
            for ($offset = 0, $length = strlen($csv); $offset < $length;) {
                // Keep each query well below MySQL's packet limit without splitting UTF-8 characters.
                $chunk = mb_strcut($csv, $offset, 32768, 'UTF-8');
                DB::table('product_file_csv_chunks')->insert([
                    'product_file_request_id' => $this->id,
                    'position' => $position++,
                    'content' => $chunk,
                ]);
                $offset += strlen($chunk);
            }
            $this->forceFill(['csv' => null])->save();
        });
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function hub()
    {
        return $this->belongsTo(StoreHub::class, 'store_hub_id');
    }
}
