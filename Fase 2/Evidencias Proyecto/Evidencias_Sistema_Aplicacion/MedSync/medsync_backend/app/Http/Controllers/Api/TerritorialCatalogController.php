<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TerritorialCatalog;

class TerritorialCatalogController extends Controller
{
    public function __construct(private readonly TerritorialCatalog $catalog)
    {
    }

    /** Public, local-to-the-app list used by the patient address forms. */
    public function index()
    {
        return response()->json(['data' => $this->catalog->regions()]);
    }
}
