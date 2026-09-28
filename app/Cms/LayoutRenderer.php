<?php

namespace App\Cms;

use App\Enums\FieldType;
use App\Models\Post;
use Illuminate\Contracts\Support\Htmlable;
use Mustache\Engine;

/**
 * Renders a post through its template's Mustache layout.
 *
 * Double braces escape values, except markdown and long-text fields, which
 * are already HTML. Lists and yes/no fields can be used as sections.
 */
class LayoutRenderer
{
    private Engine $engine;

    public function __construct()
    {
        $this->engine = new Engine([
            'escape' => self::escape(...),
            'strict_callables' => true,
        ]);
    }

    public function render(Post $post): string
    {
        return $this->engine->render($post->template->layout, $this->context($post));
    }

    /**
     * Throw a Mustache syntax exception if the layout does not compile.
     */
    public function compile(string $layout): void
    {
        $this->engine->loadTemplate($layout);
    }

    /**
     * The variables available to a post's layout.
     *
     * @return array<string, mixed>
     */
    public function context(Post $post): array
    {
        $fields = collect($post->template->fieldTypes())
            ->map(fn (FieldType $type, string $handle) => $type->present($post->data[$handle] ?? null))
            ->all();

        return [
            ...$fields,
            'title' => $post->title,
            'slug' => $post->slug,
            'url' => $post->url(),
            'published_at' => $post->published_at?->format('F j, Y'),
            'template' => [
                'name' => $post->template->name,
                'handle' => $post->template->handle,
            ],
        ];
    }

    /**
     * A starting layout that shows every field in order.
     *
     * @param  list<array{handle: string, label: string, type: string}>  $fields
     */
    public static function defaultLayout(array $fields): string
    {
        $lines = ['<article>', '  <h1>{{ title }}</h1>'];

        foreach ($fields as $field) {
            ['handle' => $handle, 'label' => $label] = $field;

            array_push($lines, '', ...match (FieldType::from($field['type'])) {
                FieldType::Markdown, FieldType::Textarea => [
                    "  <h2>{$label}</h2>",
                    "  {{ {$handle} }}",
                ],
                FieldType::List => [
                    "  <h2>{$label}</h2>",
                    '  <ul>',
                    "    {{# {$handle} }}<li>{{ . }}</li>{{/ {$handle} }}",
                    '  </ul>',
                ],
                FieldType::Boolean => [
                    "  {{# {$handle} }}<p>{$label}</p>{{/ {$handle} }}",
                ],
                default => [
                    "  {{# {$handle} }}<p><strong>{$label}:</strong> {{ {$handle} }}</p>{{/ {$handle} }}",
                ],
            });
        }

        $lines[] = '</article>';

        return implode("\n", $lines)."\n";
    }

    private static function escape(mixed $value): string
    {
        return match (true) {
            $value instanceof Htmlable => $value->toHtml(),
            is_array($value) => e(implode(', ', array_filter($value, is_scalar(...)))),
            is_bool($value) => $value ? 'Yes' : 'No',
            default => e($value),
        };
    }
}
