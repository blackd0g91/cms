{{--
    A row of small cards that open into dialogs, in the given order (see
    HomeWidget and App\Cms\HomeWidgets). Those with nothing to show today
    are left out. Expects $widgets, $picture and $games.
--}}
@if ($widgets !== [])
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($widgets as $widget)
            @switch($widget)
                @case(\App\Enums\HomeWidget::Weather)
                    @include('site.partials.weather')
                    @break
                @case(\App\Enums\HomeWidget::PictureOfTheDay)
                    @if ($picture)
                        @include('site.partials.picture-of-the-day', ['picture' => $picture])
                    @endif
                    @break
                @case(\App\Enums\HomeWidget::FreeGames)
                    @if ($games)
                        @include('site.partials.free-games', ['games' => $games])
                    @endif
                    @break
            @endswitch
        @endforeach
    </div>
@endif
