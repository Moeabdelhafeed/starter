<?php

namespace App\Http\Controllers\Api\Language;

use App\Helpers\ApiResponse;
use App\Helpers\Trans;
use App\Http\Controllers\Controller;
use App\Models\Language;

class LanguageController extends Controller
{
    public function index()
    {
        $languages = Language::active()
            ->with('image')
            ->get(['id', 'code', 'name', 'native_name', 'direction', 'is_default']);

        return ApiResponse::success($languages, Trans::get('api.languages'));
    }
}
