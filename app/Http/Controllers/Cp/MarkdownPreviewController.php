<?php

namespace App\Http\Controllers\Cp;

use App\Cms\Markdown;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarkdownPreviewController extends Controller
{
    /**
     * Render markdown the same way the public site does.
     */
    public function __invoke(Request $request, Markdown $markdown): JsonResponse
    {
        $validated = $request->validate([
            'markdown' => ['nullable', 'string', 'max:500000'],
        ]);

        return response()->json([
            'html' => $markdown->render($validated['markdown'] ?? ''),
        ]);
    }
}
