<?php

namespace App\Http\Requests\Cp;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LinksRequest extends FormRequest
{
    /**
     * Web addresses, email and phone links, or paths on this site like /tags.
     * Anything else (like javascript:) is refused.
     */
    private const string ADDRESS = '#^(https?://[^\s/]+\S*|mailto:\S+@\S+|tel:[0-9+\-(). ]+|/\S*)$#i';

    /**
     * Complete addresses typed the short way: "github.com/me" becomes
     * https://github.com/me and "me@example.com" a mailto: link.
     */
    protected function prepareForValidation(): void
    {
        $links = array_map(function (mixed $link) {
            if (! is_array($link) || ! is_string($link['url'] ?? null)) {
                return $link;
            }

            $url = trim($link['url']);

            $link['url'] = match (true) {
                (bool) preg_match('#^[^\s@/:]+@[^\s@/]+\.[^\s@/]+$#', $url) => "mailto:{$url}",
                (bool) preg_match('#^(www\.)?[a-z0-9-]+(\.[a-z0-9-]+)+(/\S*)?$#i', $url) => "https://{$url}",
                default => $url,
            };

            return $link;
        }, (array) $this->input('links', []));

        $this->merge(['links' => $links]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'heading' => ['nullable', 'string', 'max:50'],
            'links' => ['present', 'array', 'max:50'],
            'links.*.id' => ['nullable', 'integer'],
            'links.*.post_id' => ['nullable', 'integer', Rule::exists(Post::class, 'id')],
            'links.*.url' => ['nullable', 'required_without:links.*.post_id', 'string', 'max:2048', 'regex:'.self::ADDRESS],
            'links.*.label' => ['nullable', 'required_without:links.*.post_id', 'string', 'max:100'],
            'links.*.emoji' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'links.*.url.required_without' => 'Add an address, or pick a post.',
            'links.*.url.regex' => 'Use an address like https://github.com/you, an email address, or a path on this site like /tags.',
            'links.*.label.required_without' => 'Links to addresses need a label.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'heading' => 'heading',
            'links.*.url' => 'address',
            'links.*.label' => 'label',
            'links.*.emoji' => 'emoji',
            'links.*.post_id' => 'post',
        ];
    }

    /**
     * The links in order, each to either a post or an address.
     *
     * @return list<array{id: int|null, label: string|null, url: string|null, post_id: int|null, emoji: string|null}>
     */
    public function links(): array
    {
        return array_values(array_map(function (array $link) {
            $postId = isset($link['post_id']) ? (int) $link['post_id'] : null;

            return [
                'id' => isset($link['id']) ? (int) $link['id'] : null,
                'label' => Str::squish((string) ($link['label'] ?? '')) ?: null,
                'url' => $postId === null ? $link['url'] : null,
                'post_id' => $postId,
                'emoji' => trim((string) ($link['emoji'] ?? '')) ?: null,
            ];
        }, (array) $this->validated('links')));
    }
}
