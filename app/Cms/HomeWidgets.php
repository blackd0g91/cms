<?php

namespace App\Cms;

use App\Enums\HomeWidget;

/**
 * What the small cards need, for the home page (the usual ones) and a
 * member's own page (the ones they chose). Only the cards shown are fetched.
 *
 * @phpstan-import-type Picture from PictureOfTheDay
 * @phpstan-import-type Game from FreeGames
 */
class HomeWidgets
{
    public function __construct(private PictureOfTheDay $picture, private FreeGames $games) {}

    /**
     * @param  list<HomeWidget>  $widgets
     * @return array{widgets: list<HomeWidget>, picture: Picture|null, games: list<Game>}
     */
    public function data(array $widgets): array
    {
        return [
            'widgets' => $widgets,
            'picture' => in_array(HomeWidget::PictureOfTheDay, $widgets, true) ? $this->picture->today() : null,
            'games' => in_array(HomeWidget::FreeGames, $widgets, true) ? $this->games->current() : [],
        ];
    }
}
