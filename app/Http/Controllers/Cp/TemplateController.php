<?php

namespace App\Http\Controllers\Cp;

use App\Cms\LayoutRenderer;
use App\Enums\FieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cp\TemplateRequest;
use App\Models\Post;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('cp/templates/Index', [
            'templates' => Template::query()
                ->withCount('posts')
                ->orderBy('name')
                ->get(['id', 'name', 'handle', 'description', 'color'])
                // The chosen color, or the automatic one the site uses.
                ->map(fn (Template $template) => [...$template->toArray(), 'accent' => $template->accentColor()]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('cp/templates/Edit', [
            'template' => null,
            'fieldTypes' => $this->fieldTypes(),
        ]);
    }

    public function store(TemplateRequest $request): RedirectResponse
    {
        $template = Template::create($request->templateAttributes());

        return redirect()->route('cp.templates.edit', $template);
    }

    public function edit(Template $template): Response
    {
        return Inertia::render('cp/templates/Edit', [
            'template' => $template->only(['id', 'name', 'handle', 'description', 'color', 'fields', 'layout']),
            // Templates with posts can not be deleted, counting those in the trash.
            'postsCount' => $template->posts()->withTrashed()->count(),
            'trashedPostsCount' => $template->posts()->onlyTrashed()->count(),
            'fieldTypes' => $this->fieldTypes(),
        ]);
    }

    public function update(TemplateRequest $request, Template $template): RedirectResponse
    {
        DB::transaction(function () use ($request, $template) {
            $renames = $request->renamedFields();
            $typeChanges = $request->changedFieldTypes();
            $added = $request->addedFields();

            $attributes = $request->templateAttributes();
            $attributes['layout'] = LayoutRenderer::renameVariables($attributes['layout'], $renames);

            $template->update($attributes);
            $template->renameFieldsInPosts($renames);
            $template->convertFieldTypesInPosts($typeChanges);
            $template->clearFieldsInPosts($added);

            // Image fields may have been added or removed.
            $template->posts()->withTrashed()->each(fn (Post $post) => $post->setRelation('template', $template)->syncMedia());
        });

        return redirect()->route('cp.templates.edit', $template);
    }

    /**
     * Copy a template's fields and layout into a new template, without posts.
     */
    public function duplicate(Template $template): RedirectResponse
    {
        $copy = $template->replicate();
        $copy->name = "{$template->name} (copy)";
        $copy->handle = $this->uniqueHandle("{$template->handle}-copy");
        $copy->save();

        return redirect()->route('cp.templates.edit', $copy);
    }

    public function destroy(Template $template): RedirectResponse
    {
        if ($template->posts()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'template' => $template->posts()->exists()
                    ? 'Delete this template\'s posts before deleting the template.'
                    : 'This template\'s posts are in the trash. Delete them for good from the trash first.',
            ]);
        }

        $template->delete();

        return redirect()->route('cp.templates.index');
    }

    /**
     * The handle, or the handle with the first free number appended.
     */
    private function uniqueHandle(string $handle): string
    {
        $candidate = $handle;

        for ($i = 2; Template::query()->where('handle', $candidate)->exists(); $i++) {
            $candidate = "{$handle}-{$i}";
        }

        return $candidate;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function fieldTypes(): array
    {
        return array_map(
            fn (FieldType $type) => ['value' => $type->value, 'label' => $type->label()],
            FieldType::cases(),
        );
    }
}
