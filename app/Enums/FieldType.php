<?php

namespace App\Enums;

use App\Cms\Image;
use App\Cms\Markdown;
use App\Models\Media;
use Carbon\CarbonImmutable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
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
    case Image = 'image';

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
            self::Image => 'Image',
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
            self::Image => [$key => [$presence, 'integer', Rule::exists(Media::class, 'id')]],
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
            self::Image => $value === null ? null : (int) $value,
            self::List => array_values((array) $value),
            default => $value,
        };
    }

    /**
     * Turn a value stored for a field of type $from into a value for this
     * type, after the field's type was changed. Values that have no sensible
     * equivalent become null (empty).
     *
     * @param  list<string>  $options  The allowed options, for select fields
     */
    public function convertFrom(self $from, mixed $value, array $options = []): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return $this === self::Boolean ? false : null;
        }

        // Images only make sense as images, and nothing else becomes one.
        if ($from === self::Image || $this === self::Image) {
            return $from === $this ? $value : null;
        }

        $items = match (true) {
            is_array($value) => array_values(array_filter(array_map(strval(...), $value), filled(...))),
            // One list item per line, without markdown bullets.
            in_array($from, [self::Textarea, self::Markdown], true) => array_values(array_filter(
                array_map(fn (string $line) => trim((string) preg_replace('/^\s*(?:[-*+]|\d+\.)\s+/', '', $line)), preg_split('/\R/', (string) $value) ?: []),
                filled(...),
            )),
            is_bool($value) => [],
            default => [(string) $value],
        };

        $text = match (true) {
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => implode($this === self::Text ? ', ' : "\n", $items),
            default => (string) $value,
        };

        return match ($this) {
            self::Text => Str::limit(trim((string) preg_replace('/\s+/', ' ', $text)), 255, ''),
            self::Textarea => $text,
            self::Markdown => is_array($value) ? implode("\n", array_map(fn (string $item) => "- {$item}", $items)) : $text,
            self::Number => is_numeric(trim($text)) ? trim($text) + 0 : null,
            self::Boolean => is_bool($value) ? $value : (filter_var(trim($text), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true),
            self::Select => collect($options)->first(fn (string $option) => Str::lower($option) === Str::lower(trim($text))),
            self::Date => self::parseDate($text),
            self::List => $items,
        };
    }

    private static function parseDate(string $text): ?string
    {
        try {
            return CarbonImmutable::parse(trim($text))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
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
            self::Image => is_int($value) && ($media = Media::query()->find($value)) instanceof Media
                ? Image::fromMedia($media)
                : null,
            default => $value,
        };
    }
}
