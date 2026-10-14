<?php

namespace App\Cms;

use App\Enums\HomeWidget;

/**
 * What the small cards need, for the home page (the usual ones) and a
 * member's own page (the ones they chose). Only the cards shown are fetched.
 *
 * @phpstan-import-type Picture from PictureOfTheDay
 * @phpstan-import-type Game from FreeGames
 * @phpstan-import-type Event from OnThisDay
 * @phpstan-import-type Word from WordOfTheDay
 * @phpstan-import-type Apod from AstronomyPicture
 * @phpstan-import-type Fact from UselessFact
 */
class HomeWidgets
{
    public function __construct(
        private PictureOfTheDay $picture,
        private FreeGames $games,
        private OnThisDay $onThisDay,
        private WordOfTheDay $word,
        private AstronomyPicture $astronomy,
        private UselessFact $fact,
    ) {}

    /**
     * @param  list<HomeWidget>  $widgets
     * @return array{widgets: list<HomeWidget>, picture: Picture|null, games: list<Game>, onThisDay: array{date: string, events: list<Event>}|null, word: Word|null, astronomy: Apod|null, fact: Fact|null}
     */
    public function data(array $widgets): array
    {
        $shown = fn (HomeWidget $widget) => in_array($widget, $widgets, true);

        return [
            'widgets' => $widgets,
            'picture' => $shown(HomeWidget::PictureOfTheDay) ? $this->picture->today() : null,
            'games' => $shown(HomeWidget::FreeGames) ? $this->games->current() : [],
            'onThisDay' => $shown(HomeWidget::OnThisDay) ? $this->onThisDay->today() : null,
            'word' => $shown(HomeWidget::WordOfTheDay) ? $this->word->today() : null,
            'astronomy' => $shown(HomeWidget::AstronomyPicture) ? $this->astronomy->today() : null,
            'fact' => $shown(HomeWidget::UselessFact) ? $this->fact->today() : null,
        ];
    }
}
