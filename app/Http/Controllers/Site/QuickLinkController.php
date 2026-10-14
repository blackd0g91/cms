<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\QuickLink;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A member's quick links, the tiles on their own page: added, changed,
 * moved and deleted from a dialog there, which opens again after each
 * change so several can be made in a row.
 */
class QuickLinkController extends Controller
{
    /**
     * Web addresses only.
     */
    private const string ADDRESS = '#^https?://[^\s/]+\S*$#i';

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->quickLinks()->count() >= QuickLink::MAX) {
            throw ValidationException::withMessages(['url' => 'You have '.QuickLink::MAX.' links, the most there can be. Delete some first.'])->errorBag('quickLink');
        }

        $user->quickLinks()->create([
            ...$this->validated($request),
            'position' => (int) $user->quickLinks()->max('position') + 1,
        ]);

        return $this->back('Link added.');
    }

    public function update(Request $request, QuickLink $quickLink): RedirectResponse
    {
        $this->owner($request, $quickLink);

        $quickLink->update($this->validated($request));

        return $this->back('Link saved.');
    }

    /**
     * One place up or down, swapping with its neighbor.
     */
    public function move(Request $request, QuickLink $quickLink): RedirectResponse
    {
        $user = $this->owner($request, $quickLink);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $links = $user->quickLinks()->orderBy('position')->orderBy('id')->get()->values();
        $index = (int) $links->search(fn (QuickLink $link) => $link->is($quickLink));
        $other = $direction === 'up' ? $index - 1 : $index + 1;

        if ($links->has($other)) {
            // Numbered from the order shown, so ties are settled too.
            $order = $links->all();
            [$order[$index], $order[$other]] = [$order[$other], $order[$index]];

            DB::transaction(function () use ($order) {
                foreach ($order as $position => $link) {
                    $link->update(['position' => $position]);
                }
            });
        }

        return $this->back();
    }

    public function destroy(Request $request, QuickLink $quickLink): RedirectResponse
    {
        $this->owner($request, $quickLink);

        $quickLink->delete();

        return $this->back('Link deleted.');
    }

    /**
     * Back to the page, with the dialog open again.
     */
    private function back(?string $status = null): RedirectResponse
    {
        return back()->with(array_filter(['status' => $status, 'dialog' => 'quick-links']));
    }

    /**
     * Anyone else's link is as good as missing.
     */
    private function owner(Request $request, QuickLink $quickLink): User
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($quickLink->user_id === $user->id, 404);

        return $user;
    }

    /**
     * Addresses typed the short way, like "github.com", become https:// ones.
     *
     * @return array{label: string, url: string, emoji: string|null}
     */
    private function validated(Request $request): array
    {
        $url = trim((string) $request->input('url'));

        if (preg_match('#^(www\.)?[a-z0-9-]+(\.[a-z0-9-]+)+(/\S*)?$#i', $url)) {
            $request->merge(['url' => "https://{$url}"]);
        }

        // Their own bag, apart from the reminders' and notes' on the page.
        $validated = $request->validateWithBag('quickLink', [
            'label' => ['required', 'string', 'max:40'],
            'url' => ['required', 'string', 'max:2048', 'regex:'.self::ADDRESS],
            'emoji' => ['nullable', 'string', 'max:32'],
        ], [
            'url.regex' => 'Use a web address, like github.com or https://mail.google.com.',
        ]);

        return [
            'label' => Str::squish($validated['label']),
            'url' => $validated['url'],
            'emoji' => trim((string) ($validated['emoji'] ?? '')) ?: null,
        ];
    }
}
