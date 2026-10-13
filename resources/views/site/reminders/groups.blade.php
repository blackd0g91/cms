{{-- Reminders under their group's heading. Expects $groups, $reminders, $user and optionally $compact. --}}
<div class="space-y-4">
    @foreach ($groups as $group)
        <section>
            <h3 @class([
                'font-mono text-xs tracking-wider uppercase',
                'text-accent' => $group['label'] === 'Overdue',
                'text-muted' => $group['label'] !== 'Overdue',
            ])>{{ $group['label'] }}</h3>
            <ul class="divide-y divide-line">
                @foreach ($group['reminders'] as $reminder)
                    @include('site.reminders.item', ['reminder' => $reminder])
                @endforeach
            </ul>
        </section>
    @endforeach
</div>
