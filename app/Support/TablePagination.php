<?php

namespace App\Support;

use Illuminate\Http\Request;

class TablePagination
{
    public const array SIZES = [10, 25, 50, 100];

    public static function perPage(Request $request): int
    {
        $size = filter_var($request->query('per_page'), FILTER_VALIDATE_INT);

        return in_array($size, self::SIZES, true) ? $size : 25;
    }
}
