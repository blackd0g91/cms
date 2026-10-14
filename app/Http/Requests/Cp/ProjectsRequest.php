<?php

namespace App\Http\Requests\Cp;

use App\Models\Media;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectsRequest extends FormRequest
{
    /**
     * Projects are other apps and sites: web addresses only.
     */
    private const string ADDRESS = '#^https?://[^\s/]+\S*$#i';

    /**
     * Complete addresses typed the short way: "silver.example.com" becomes
     * https://silver.example.com.
     */
    protected function prepareForValidation(): void
    {
        $projects = array_map(function (mixed $project) {
            if (! is_array($project) || ! is_string($project['url'] ?? null)) {
                return $project;
            }

            $url = trim($project['url']);

            if (preg_match('#^(www\.)?[a-z0-9-]+(\.[a-z0-9-]+)+(/\S*)?$#i', $url)) {
                $url = "https://{$url}";
            }

            $project['url'] = $url;

            return $project;
        }, (array) $this->input('projects', []));

        $this->merge(['projects' => $projects]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $image = ['nullable', 'integer', Rule::exists(Media::class, 'id')->whereNull('deleted_at')];

        return [
            'projects' => ['present', 'array', 'max:24'],
            'projects.*.id' => ['nullable', 'integer'],
            'projects.*.name' => ['required', 'string', 'max:60'],
            'projects.*.url' => ['required', 'string', 'max:2048', 'regex:'.self::ADDRESS],
            'projects.*.description' => ['nullable', 'string', 'max:160'],
            'projects.*.logo_id' => $image,
            'projects.*.background_id' => $image,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'projects.*.url.regex' => 'Use a web address, like https://app.example.com.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'projects.*.name' => 'name',
            'projects.*.url' => 'address',
            'projects.*.description' => 'description',
            'projects.*.logo_id' => 'logo',
            'projects.*.background_id' => 'background image',
        ];
    }

    /**
     * The projects in order.
     *
     * @return list<array{id: int|null, name: string, url: string, description: string|null, logo_id: int|null, background_id: int|null}>
     */
    public function projects(): array
    {
        return array_values(array_map(fn (array $project) => [
            'id' => isset($project['id']) ? (int) $project['id'] : null,
            'name' => Str::squish((string) $project['name']),
            'url' => (string) $project['url'],
            'description' => Str::squish((string) ($project['description'] ?? '')) ?: null,
            'logo_id' => isset($project['logo_id']) ? (int) $project['logo_id'] : null,
            'background_id' => isset($project['background_id']) ? (int) $project['background_id'] : null,
        ], (array) $this->validated('projects')));
    }
}
