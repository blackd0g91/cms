<?php

namespace App\Http\Controllers\Site;

use App\Cms\HomeWidgets;
use App\Cms\Reminders;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * A member's own page, at /me: made to stay open in a tab, with the cards
 * they chose, their reminders for the week ahead (which come up as toasts
 * when due, see reminders.ts) and their latest notes. The home page stays
 * the same for everyone.
 */
class MeController extends Controller
{
    public function __invoke(Request $request, HomeWidgets $widgets, Reminders $reminders): View
    {
        /** @var User $user */
        $user = $request->user();
        $now = now($user->localTimezone());

        return view('site.me', [
            ...$widgets->data($user->homeWidgets()),
            'user' => $user,
            'reminders' => $reminders,
            'soon' => $reminders->groups($user, until: $reminders->weekAhead($user)),
            'labels' => $user->labels()->get(),
            'quickLinks' => $user->quickLinks()->orderBy('position')->orderBy('id')->get(),
            'notes' => $user->notes()->with('labels')->ordered()->limit(5)->get(),
            'noteCount' => $user->notes()->count(),
            'today' => $now->isoFormat('dddd, MMMM D'),
            'greeting' => match (true) {
                $now->hour < 5 => 'Good night',
                $now->hour < 12 => 'Good morning',
                $now->hour < 18 => 'Good afternoon',
                default => 'Good evening',
            },
            'firstName' => Str::of($user->name)->squish()->before(' ')->toString(),
        ]);
    }
}
