<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Label;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A member's labels for their notes and reminders: added, renamed,
 * recolored and deleted from a dialog on the notes and reminders pages
 * (see site/labels/dialog.blade.php), which opens again after each change.
 * Deleting a label takes it off everything, and keeps the rest.
 */
class LabelController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->labels()->count() >= Label::MAX) {
            throw ValidationException::withMessages(['name' => 'You have '.Label::MAX.' labels, the most there can be. Delete some first.'])->errorBag('label');
        }

        $user->labels()->create($this->validated($request, $user));

        return $this->back('Label added.');
    }

    public function update(Request $request, Label $label): RedirectResponse
    {
        $user = $this->owner($request, $label);

        $label->update($this->validated($request, $user, $label));

        return $this->back('Label saved.');
    }

    public function destroy(Request $request, Label $label): RedirectResponse
    {
        $this->owner($request, $label);

        $label->delete();

        return $this->back('Label deleted.');
    }

    /**
     * Back to the page, with the dialog open again.
     */
    private function back(string $status): RedirectResponse
    {
        return back()->with(['status' => $status, 'dialog' => 'labels']);
    }

    /**
     * Anyone else's label is as good as missing.
     */
    private function owner(Request $request, Label $label): User
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($label->user_id === $user->id, 404);

        return $user;
    }

    /**
     * @return array{name: string, color: string}
     */
    private function validated(Request $request, User $user, ?Label $label = null): array
    {
        $request->merge(['name' => Str::squish((string) $request->input('name'))]);

        // Their own bag, apart from the notes' and reminders' on the page.
        $validated = $request->validateWithBag('label', [
            'name' => ['required', 'string', 'max:30', function (string $attribute, string $name, Closure $fail) use ($user, $label) {
                // "Work" and "work" are the same label.
                $taken = $user->labels()
                    ->whereRaw('lower(name) = ?', [Str::lower($name)])
                    ->when($label, fn ($query) => $query->whereKeyNot($label->id))
                    ->exists();

                if ($taken) {
                    $fail('You already have a label with that name.');
                }
            }],
            'color' => ['required', 'string', 'regex:/^#[0-9a-f]{6}$/i'],
        ], [
            'color.regex' => 'Pick a color.',
        ]);

        return ['name' => $validated['name'], 'color' => Str::lower($validated['color'])];
    }
}
