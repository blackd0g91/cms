<?php

namespace App\Http\Requests\Cp;

use App\Enums\FieldType;
use App\Enums\PostStatus;
use App\Models\Media;
use App\Models\Post;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
{
    public function template(): Template
    {
        /** @var Template */
        return $this->route('template');
    }

    /**
     * Fill in a missing slug and drop blank list items.
     */
    protected function prepareForValidation(): void
    {
        $data = (array) $this->input('data', []);

        foreach ($this->template()->fieldTypes() as $handle => $type) {
            if ($type === FieldType::List && is_array($data[$handle] ?? null)) {
                $data[$handle] = array_values(array_filter($data[$handle], filled(...)));
            }
        }

        $this->merge([
            'slug' => $this->filled('slug') ? $this->input('slug') : Str::slug((string) $this->input('title')),
            'data' => $data,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Post|null $post */
        $post = $this->route('post');

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(Post::class)->where('template_id', $this->template()->id)->ignore($post),
            ],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'thumbnail_id' => ['nullable', 'integer', Rule::exists(Media::class, 'id')],
            'pinned' => ['boolean'],
            'tags' => ['array', 'max:30'],
            'tags.*' => ['string', 'max:50'],
            'data' => ['array'],
        ];

        foreach ($this->template()->fields as $field) {
            $rules = [
                ...$rules,
                ...FieldType::from($field['type'])->rules("data.{$field['handle']}", $field['required'], $field['options']),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect($this->template()->fields)
            ->mapWithKeys(fn (array $field) => ["data.{$field['handle']}" => $field['label']])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and dashes.',
        ];
    }

    /**
     * The tag names to give the post.
     *
     * @return list<string>
     */
    public function tagNames(): array
    {
        return array_values(array_filter(array_map(strval(...), (array) $this->validated('tags', [])), filled(...)));
    }

    /**
     * The validated attributes, keeping only data for the template's fields.
     *
     * @return array{title: string, slug: string, status: PostStatus, thumbnail_id: int|null, data: array<string, mixed>}
     */
    public function postAttributes(): array
    {
        $data = collect($this->template()->fieldTypes())
            ->map(fn (FieldType $type, string $handle) => $type->cast($this->validated("data.{$handle}")))
            ->all();

        return [
            'title' => $this->validated('title'),
            'slug' => $this->validated('slug'),
            'status' => PostStatus::from($this->validated('status')),
            'thumbnail_id' => $this->filled('thumbnail_id') ? $this->integer('thumbnail_id') : null,
            'data' => $data,
        ];
    }
}
