<?php

namespace App\Http\Controllers\Cp;

use App\Cms\Settings;
use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(Settings $settings): Response
    {
        return Inertia::render('cp/Settings', [
            'settings' => $settings->all(),
            'media' => Media::query()
                ->whereKey(array_filter([$settings->get('logo_id'), $settings->get('favicon_id')]))
                ->get()
                ->keyBy('id'),
        ]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $settings->update($request->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'home_intro' => ['nullable', 'string', 'max:20000'],
            'footer_text' => ['nullable', 'string', 'max:500'],
            'logo_id' => ['nullable', 'integer', Rule::exists(Media::class, 'id')],
            'favicon_id' => ['nullable', 'integer', Rule::exists(Media::class, 'id')],
        ]));

        return redirect()->route('cp.settings.edit');
    }
}
