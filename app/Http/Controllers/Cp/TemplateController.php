<?php

namespace App\Http\Controllers\Cp;

use App\Enums\FieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cp\TemplateRequest;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
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
        $template->update($request->templateAttributes());

        return redirect()->route('cp.templates.edit', $template);
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
