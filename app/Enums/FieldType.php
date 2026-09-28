<?php

namespace App\Enums;

use App\Cms\Markdown;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Markdown = 'markdown';
    case Number = 'number';
    case Boolean = 'boolean';
    case Select = 'select';
    case Date = 'date';
    case List = 'list';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Textarea => 'Long text',
            self::Markdown => 'Markdown',
            self::Number => 'Number',
            self::Boolean => 'Yes / No',
            self::Select => 'Select',
            self::Date => 'Date',
            self::List => 'List',
        };
    }

    /**
     * Validation rules for a post value of this type, keyed by attribute
     * path. Booleans are never required, since "no" is a valid answer.
     *
     * @param  list<string>  $options
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $key, bool $required, array $options = []): array
    {
        $presence = $required && $this !== self::Boolean ? 'required' : 'nullable';

        return match ($this) {
            self::Text => [$key => [$presence, 'string', 'max:255']],
            self::Textarea, self::Markdown => [$key => [$presence, 'string']],
            self::Number => [$key => [$presence, 'numeric']],
            self::Boolean => [$key => [$presence, 'boolean']],
            self::Select => [$key => [$presence, 'string', Rule::in($options)]],
            self::Date => [$key => [$presence, 'date_format:Y-m-d']],
            self::List => [
                $key => $required ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
                "{$key}.*" => ['string'],
            ],
        };
    }

    /**
     * Normalize a validated value before it is stored.
     */
    public function cast(mixed $value): mixed
    {
        return match ($this) {
            self::Boolean => (bool) $value,
            self::Number => $value === null ? null : $value + 0,
            self::List => array_values((array) $value),
            default => $value,
        };
    }

    /**
     * Prepare a stored value for use inside a layout.
     */
    public function present(mixed $value): mixed
    {
        return match ($this) {
            self::Markdown => new HtmlString(app(Markdown::class)->render((string) $value)),
            self::Textarea => new HtmlString(nl2br(e((string) $value))),
            self::Boolean => (bool) $value,
            self::List => array_values((array) $value),
            default => $value,
        };
    }
}
