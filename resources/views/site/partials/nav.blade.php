<nav class="text-sm">
    <ul class="space-y-1">
        <li>
            <a
                href="{{ route('home') }}"
                @class([
                    'block rounded-md px-3 py-2 hover:bg-neutral-100 dark:hover:bg-neutral-800',
                    'bg-neutral-100 font-medium dark:bg-neutral-800' => request()->routeIs('home'),
                ])
            >
                Home
            </a>
        </li>
        @foreach ($navTemplates as $navTemplate)
            <li>
                <a
                    href="{{ route('site.template', $navTemplate) }}"
                    @class([
                        'block truncate rounded-md px-3 py-2 hover:bg-neutral-100 dark:hover:bg-neutral-800',
                        'bg-neutral-100 font-medium dark:bg-neutral-800' => $currentTemplate?->is($navTemplate),
                    ])
                >
                    {{ $navTemplate->name }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
