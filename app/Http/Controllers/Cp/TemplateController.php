<?php

namespace App\Http\Controllers\Cp;

use App\Cms\LayoutRenderer;
use App\Enums\FieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cp\TemplateRequest;
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
                ->get(['id', 'name', 'handle', 'description']),
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
            'template' => $template->only(['id', 'name', 'handle', 'description', 'fields', 'layout']),
            'fieldTypes' => $this->fieldTypes(),
        ]);
    }

    public function update(TemplateRequest $request, Template $template): RedirectResponse
    {
        DB::transaction(function () use ($request, $template) {
            $renames = $request->renamedFields();

            $attributes = $request->templateAttributes();
            $attributes['layout'] = LayoutRenderer::renameVariables($attributes['layout'], $renames);

            $template->update($attributes);
            $template->renameFieldsInPosts($renames);
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
        if ($template->posts()->exists()) {
            throw ValidationException::withMessages([
                'template' => 'Delete this template\'s posts before deleting the template.',
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
