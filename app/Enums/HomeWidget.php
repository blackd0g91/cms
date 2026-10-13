<?php

namespace App\Enums;

/**
 * The small cards at the top of the home page. Each member chooses theirs,
 * and their order, on their account page; everyone else sees the defaults.
 */
enum HomeWidget: string
{
    case Weather = 'weather';
    case PictureOfTheDay = 'picture';
    case FreeGames = 'games';

    /**
     * What guests, and members who have not chosen, see.
     *
     * @return list<self>
     */
    public static function defaults(): array
    {
        return [self::Weather, self::PictureOfTheDay];
    }

    public function label(): string
    {
        return match ($this) {
            self::Weather => 'Weather',
            self::PictureOfTheDay => 'Picture of the day',
            self::FreeGames => 'Free games',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Weather => 'Where you are, with the week ahead',
            self::PictureOfTheDay => "Wikimedia Commons' picture of the day",
            self::FreeGames => 'Games given away free to keep right now',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Weather => '🌤️',
            self::PictureOfTheDay => '🖼️',
            self::FreeGames => '🎮',
        };
    }
}
