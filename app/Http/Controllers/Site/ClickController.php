<?php

namespace App\Http\Controllers\Site;

use App\Cms\LinkClicks;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ClickController extends Controller
{
    /**
     * Count a click on a sidebar link or profile icon.
     */
    public function __invoke(Request $request, LinkClicks $clicks): Response
    {
        $target = $request->input('target');

        if (is_string($target)) {
            $clicks->record($request, $target);
        }

        return response()->noContent();
    }
}
