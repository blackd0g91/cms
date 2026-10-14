{{--
    The owner's apps and sites, as cards linking out to them (see
    App\Models\Project): the logo, or the name's first letter, then the name
    and description, over the background image faded into the card. Expects
    $projects, not empty.
--}}
<section aria-labelledby="projects-title" class="mb-10">
    <h2 id="projects-title" class="mb-5 flex items-center gap-4 font-mono text-xs tracking-wider text-muted uppercase">
        Projects
        <span class="h-px flex-1 bg-line"></span>
    </h2>

    <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($projects as $project)
            <li>
                <a
                    href="{{ $project->url }}"
                    data-click="project:{{ $project->id }}"
                    class="project-card group relative isolate flex h-full min-h-44 flex-col overflow-hidden rounded-2xl border border-line bg-card p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-accent hover:shadow-md"
                >
                    @if ($project->background)
                        <img
                            src="{{ $project->background->urlFor(800) }}"
                            @if ($srcset = $project->background->srcset())
                                srcset="{{ $srcset }}"
                                sizes="(min-width: 1024px) 20rem, (min-width: 640px) 50vw, 100vw"
                            @endif
                            alt=""
                            loading="lazy"
                            class="project-card-background"
                        >
                    @endif

                    <span class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-xl border border-line bg-card shadow-sm">
                        @if ($project->logo)
                            <img src="{{ $project->logo->urlFor(160) }}" alt="" loading="lazy" class="size-full object-cover">
                        @else
                            <span class="font-display text-xl font-semibold" aria-hidden="true">{{ mb_strtoupper(mb_substr($project->name, 0, 1)) }}</span>
                        @endif
                    </span>

                    <span class="mt-auto block pt-6">
                        <span class="block font-display text-xl leading-tight font-semibold tracking-tight">{{ $project->name }}</span>
                        @if ($project->description)
                            <span class="mt-1 block text-sm text-muted">{{ $project->description }}</span>
                        @endif
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
