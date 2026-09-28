<?php

namespace App\Http\Requests\Cp;

use App\Cms\LayoutRenderer;
use App\Enums\FieldType;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Mustache\Exception\SyntaxException;

class TemplateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Template|null $template */
        $template = $this->route('template');

        return [
            'name' => ['required', 'string', 'max:255'],
            'handle' => [
                'required', 'string', 'max:64',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(Template::RESERVED_HANDLES),
                Rule::unique(Template::class)->ignore($template),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'fields' => ['present', 'array'],
            'fields.*.handle' => [
                'required', 'string', 'max:64', 'distinct',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::notIn(Template::RESERVED_FIELD_HANDLES),
            ],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::enum(FieldType::class)],
            'fields.*.required' => ['boolean'],
            'fields.*.options' => ['array', 'required_if:fields.*.type,select'],
            'fields.*.options.*' => ['required', 'string', 'max:255', 'distinct'],
            'layout' => ['nullable', 'string', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'handle.regex' => 'The handle may only contain lowercase letters, numbers and dashes.',
            'handle.not_in' => 'This handle is reserved.',
            'fields.*.handle.regex' => 'Field handles must start with a letter and contain only lowercase letters, numbers and underscores.',
            'fields.*.handle.not_in' => 'This field handle is reserved.',
            'fields.*.handle.distinct' => 'Field handles must be unique.',
            'fields.*.options.required_if' => 'Select fields need at least one option.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $layout = $this->input('layout');

                if (! is_string($layout) || $validator->errors()->has('layout')) {
                    return;
                }

                try {
                    app(LayoutRenderer::class)->compile($layout);
                } catch (SyntaxException $e) {
                    $validator->errors()->add('layout', 'Layout syntax error: '.$e->getMessage());
                }
            },
        ];
    }

    /**
     * The validated attributes, with fields normalized and an empty layout
     * replaced by one generated from the fields.
     *
     * @return array{name: string, handle: string, description: string|null, fields: list<array{handle: string, label: string, type: string, required: bool, options: list<string>}>, layout: string}
     */
    public function templateAttributes(): array
    {
        $fields = array_map(fn (array $field) => [
            'handle' => (string) $field['handle'],
            'label' => (string) $field['label'],
            'type' => (string) $field['type'],
            'required' => (bool) ($field['required'] ?? false),
            'options' => $field['type'] === FieldType::Select->value
                ? array_values(array_map(strval(...), $field['options'] ?? []))
                : [],
        ], array_values($this->array('fields')));

        return [
            'name' => $this->string('name')->toString(),
            'handle' => $this->string('handle')->toString(),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'fields' => $fields,
            'layout' => $this->filled('layout')
                ? $this->string('layout')->toString()
                : LayoutRenderer::defaultLayout($fields),
        ];
    }
}
