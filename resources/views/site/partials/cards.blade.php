{{--
    A row of small cards that open into dialogs, in the given order (see
    HomeWidget and App\Cms\HomeWidgets). Those with nothing to show today
    are left out. Expects what App\Cms\HomeWidgets::data() gives.
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
                @case(\App\Enums\HomeWidget::OnThisDay)
                    @if ($onThisDay ?? null)
                        @include('site.cards.on-this-day')
                    @endif
                    @break
                @case(\App\Enums\HomeWidget::WordOfTheDay)
                    @if ($word ?? null)
                        @include('site.cards.word')
                    @endif
                    @break
                @case(\App\Enums\HomeWidget::AstronomyPicture)
                    @if ($astronomy ?? null)
                        @include('site.cards.astronomy')
                    @endif
                    @break
                @case(\App\Enums\HomeWidget::UselessFact)
                    @if ($fact ?? null)
                        @include('site.cards.fact')
                    @endif
                    @break
                @case(\App\Enums\HomeWidget::FocusTimer)
                    @include('site.cards.focus-timer')
                    @break
            @endswitch
        @endforeach
    </div>
@endif
