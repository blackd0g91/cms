<?php

namespace App\Http\Controllers\Site\Concerns;

use App\Models\Label;
use App\Models\Note;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The labels picked for a note or reminder (see site/labels/picker.blade.php),
 * which can only be the member's own. Forms without the picker leave the
 * labels as they are.
 */
trait SyncsLabels
{
    /**
     * The ids of the labels picked, or null when the form had no picker.
     * Checked before anything is saved.
     *
     * @return list<int>|null
     */
    protected function pickedLabels(Request $request, User $user, string $errorBag = 'default'): ?array
    {
        if (! $request->boolean('with_labels')) {
            return null;
        }

        $ids = $request->validateWithBag($errorBag, [
            'labels' => ['nullable', 'array'],
            'labels.*' => ['integer', 'distinct', Rule::exists(Label::class, 'id')->where('user_id', $user->id)],
        ])['labels'] ?? [];

        return array_values(array_map('intval', $ids));
    }

    /**
     * @param  list<int>|null  $ids
     */
    protected function syncLabels(Note|Reminder $item, ?array $ids): void
    {
        if ($ids !== null) {
            $item->labels()->sync($ids);
        }
    }

    /**
     * The member's label to show only those with it, from ?label=.
     */
    protected function labelFilter(Request $request, User $user): ?Label
    {
        $id = $request->query('label');

        return is_numeric($id) ? $user->labels()->find((int) $id) : null;
    }
}
