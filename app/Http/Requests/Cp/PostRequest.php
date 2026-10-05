<?php

namespace App\Http\Requests\Cp;

use App\Enums\FieldType;
use App\Enums\PostStatus;
use App\Models\Media;
use App\Models\Post;
use App\Models\Template;
use App\Models\User;
use Closure;
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
            'summary' => ['nullable', 'string', 'max:160'],
            'slug' => [
                'required', 'string', 'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(Post::class)->where('template_id', $this->template()->id)->ignore($post)->withoutTrashed(),
                // Posts in the trash keep their slugs, for when they are restored.
                function (string $attribute, mixed $value, Closure $fail) {
                    if (Post::onlyTrashed()->where('template_id', $this->template()->id)->where('slug', $value)->exists()) {
                        $fail('A post in the trash uses this slug. Restore it, or delete it for good from the trash.');
                    }
                },
            ],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'thumbnail_id' => ['nullable', 'integer', Rule::exists(Media::class, 'id')],
            'author_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
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
     * The author only when one (or none, as null) was sent.
     *
     * @return array{title: string, summary: string|null, slug: string, status: PostStatus, thumbnail_id: int|null, author_id?: int|null, data: array<string, mixed>}
     */
    public function postAttributes(): array
    {
        $data = collect($this->template()->fieldTypes())
            ->map(fn (FieldType $type, string $handle) => $type->cast($this->validated("data.{$handle}")))
            ->all();

        return [
            'title' => $this->validated('title'),
            'summary' => $this->filled('summary') ? Str::squish($this->validated('summary')) : null,
            'slug' => $this->validated('slug'),
            'status' => PostStatus::from($this->validated('status')),
            'thumbnail_id' => $this->filled('thumbnail_id') ? $this->integer('thumbnail_id') : null,
            ...($this->has('author_id') ? ['author_id' => $this->filled('author_id') ? $this->integer('author_id') : null] : []),
            'data' => $data,
        ];
    }
}
