<?php

namespace App\Http\Controllers\Cp;

use App\Cms\Settings;
use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $hex = 'regex:/^#[0-9a-fA-F]{6}$/';

        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'home_intro' => ['nullable', 'string', 'max:20000'],
            'footer_text' => ['nullable', 'string', 'max:500'],
            'logo_id' => ['nullable', 'integer', Rule::exists(Media::class, 'id')],
            'favicon_id' => ['nullable', 'integer', Rule::exists(Media::class, 'id')],
            'logo_background' => ['nullable', 'array'],
            'logo_background.type' => ['required_with:logo_background', Rule::in(['none', 'solid', 'gradient'])],
            'logo_background.from' => ['required_if:logo_background.type,solid,gradient', 'nullable', $hex],
            'logo_background.to' => ['required_if:logo_background.type,gradient', 'nullable', $hex],
            'logo_background.angle' => ['nullable', 'integer', 'between:0,360'],
        ], [
            'logo_background.*.regex' => 'Colors must be hex colors like #c2410c.',
        ]);

        $validated['logo_background'] = $this->logoBackground($validated['logo_background'] ?? null);

        $settings->update($validated);

        return redirect()->route('cp.settings.edit');
    }

    /**
     * Store every part of the background, so switching between solid and
     * gradient in the form keeps the chosen colors.
     *
     * @param  array<string, mixed>|null  $background
     * @return array{type: string, from: string, to: string, angle: int}|null
     */
    private function logoBackground(?array $background): ?array
    {
        if ($background === null) {
            return null;
        }

        return [
            'type' => (string) $background['type'],
            'from' => Str::lower((string) ($background['from'] ?? '#221d17')),
            'to' => Str::lower((string) ($background['to'] ?? '#c2410c')),
            'angle' => (int) ($background['angle'] ?? 135),
        ];
    }
}
