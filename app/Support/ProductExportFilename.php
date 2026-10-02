<?php

namespace App\Support;

use App\Models\StoreHub;
use Illuminate\Support\Str;

class ProductExportFilename
{
    public static function make(StoreHub $hub, string $extension): string
    {
        $hubCode = Str::upper(Str::slug($hub->code, '_')) ?: (string) $hub->id;

        return 'products_'.$hubCode.'_'.now()->format('Ymd_His_u').'.'.$extension;
    }
}
