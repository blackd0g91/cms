<?php

namespace App\Http\Controllers\Site;

use App\Cms\Markdown;
use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * A member's notes: all of them on their own page, searchable, and the
 * latest few with a quick form on the member's page (see MeController).
 * Written in markdown, and only ever shown to them.
 */
class NoteController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $search = Str::squish((string) $request->query('q'));

        $notes = $user->notes()
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($query) => $query->where('title', 'like', $like)->orWhere('body', 'like', $like));
            })
            ->ordered()
            ->paginate(30)
            ->withQueryString();

        return view('site.notes.index', [
            'notes' => $notes,
            'search' => $search,
            'total' => $user->notes()->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->notes()->count() >= Note::MAX) {
            throw ValidationException::withMessages(['body' => 'You have '.Note::MAX.' notes, the most there can be. Delete some first.'])->errorBag('note');
        }

        $note = $this->validated($request);

        // Jotted down on the member's page: titled with when, so it is
        // easy to find again.
        if ($note['title'] === null && $request->boolean('quick')) {
            $note['title'] = now($user->localTimezone())->isoFormat('ddd, MMM D · HH:mm');
        }

        $user->notes()->create($note);

        return back()->with('status', 'Note saved.');
    }

    /**
     * The note as it will read, while it is being written (see notes.ts):
     * the same markdown as the saved note.
     */
    public function preview(Request $request, Markdown $markdown): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:'.Note::MAX_LENGTH],
        ]);

        return response()->json(['html' => $markdown->render($validated['body'] ?? '')]);
    }

    public function update(Request $request, Note $note): RedirectResponse
    {
        $this->owner($request, $note);

        $note->update($this->validated($request));

        return back()->with('status', 'Note saved.');
    }

    /**
     * Pin it to the top, or unpin it, without it counting as changed.
     */
    public function pin(Request $request, Note $note): RedirectResponse
    {
        $this->owner($request, $note);

        $note->timestamps = false;
        $note->update(['pinned_at' => $note->pinned_at ? null : now()]);

        return back()->with('status', $note->pinned_at ? 'Note pinned.' : 'Note unpinned.');
    }

    public function destroy(Request $request, Note $note): RedirectResponse
    {
        $this->owner($request, $note);

        $note->delete();

        return back()->with('status', 'Note deleted.');
    }

    /**
     * Anyone else's note is as good as missing.
     */
    private function owner(Request $request, Note $note): void
    {
        abort_unless($note->user_id === $request->user()?->id, 404);
    }

    /**
     * @return array{title: string|null, body: string}
     */
    private function validated(Request $request): array
    {
        // Their own bag, apart from the reminders' on the member's page.
        $validated = $request->validateWithBag('note', [
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:'.Note::MAX_LENGTH],
        ], [
            'body.required' => 'Write something first.',
        ]);

        return [
            'title' => Str::squish((string) ($validated['title'] ?? '')) ?: null,
            'body' => rtrim($validated['body']),
        ];
    }
}
