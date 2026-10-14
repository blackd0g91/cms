<?php

namespace App\Enums;

/**
 * The small cards at the top of the home page, where everyone gets the
 * defaults, and of a member's own page, where they get the ones they chose,
 * in their order, on their account page.
 */
enum HomeWidget: string
{
    case Weather = 'weather';
    case PictureOfTheDay = 'picture';
    case FreeGames = 'games';
    case OnThisDay = 'on-this-day';
    case WordOfTheDay = 'word';
    case AstronomyPicture = 'nasa';
    case UselessFact = 'fact';
    case FocusTimer = 'timer';

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
            self::OnThisDay => 'On this day',
            self::WordOfTheDay => 'Word of the day',
            self::AstronomyPicture => 'NASA picture of the day',
            self::UselessFact => 'Useless fact',
            self::FocusTimer => 'Focus timer',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Weather => 'Where you are, with the week ahead',
            self::PictureOfTheDay => "Wikimedia Commons' picture of the day",
            self::FreeGames => 'Games given away free to keep right now',
            self::OnThisDay => 'What happened today in years past, from Wikipedia',
            self::WordOfTheDay => "Wiktionary's word of the day, with what it means",
            self::AstronomyPicture => 'A picture of space, explained by NASA',
            self::UselessFact => 'A useless fact, a new one each day',
            self::FocusTimer => 'A pomodoro timer: focus for 25 minutes, then a break',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Weather => '🌤️',
            self::PictureOfTheDay => '🖼️',
            self::FreeGames => '🎮',
            self::OnThisDay => '📜',
            self::WordOfTheDay => '📖',
            self::AstronomyPicture => '🔭',
            self::UselessFact => '💡',
            self::FocusTimer => '🍅',
        };
    }
}
