<?php

use App\Cms\AstronomyPicture;
use App\Cms\OnThisDay;
use App\Cms\UselessFact;
use App\Cms\WordOfTheDay;
use App\Enums\HomeWidget;
use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Date::setTestNow('2026-10-14 15:00:00');

    Http::fake([
        'api.wikimedia.org/feed/v1/wikipedia/en/onthisday/*' => Http::response(['selected' => [
            ['year' => 1066, 'text' => 'The Battle of Hastings was fought.', 'pages' => [[
                'titles' => ['normalized' => 'Battle of Hastings'],
                'content_urls' => ['desktop' => ['page' => 'https://en.wikipedia.org/wiki/Battle_of_Hastings']],
                'thumbnail' => ['source' => 'https://upload.wikimedia.org/hastings.jpg?utm_source=x'],
            ]]],
            ['year' => 2021, 'text' => 'Ten thousand workers went on strike.', 'pages' => []],
            ['text' => 'No year: left out.'],
        ]]),
        'en.wiktionary.org/*' => Http::response(['parse' => ['text' => <<<'HTML'
            <table><tr><td><b><a href="/wiki/Winnie-the-Poohish"><span id="WOTD-rss-title">Winnie-the-Poohish</span></a></b> <i>adj</i></td></tr>
            <tr><td><div id="WOTD-rss-description"><ol>
                <li>Resembling the book <i>Winnie-the-Pooh</i> (1926).<dl><dd>An example, left out.</dd></dl></li>
                <li>Resembling the bear.</li>
            </ol></div></td></tr></table>
            HTML]]),
        'api.nasa.gov/*' => Http::response([
            'date' => '2026-10-14',
            'title' => 'Mars and the Beehive',
            'explanation' => 'Mars joins the Beehive cluster.',
            'media_type' => 'image',
            'url' => 'https://apod.nasa.gov/apod/image/2610/mars.jpg',
            'copyright' => "\nSomeone   Somewhere\n",
        ]),
        'uselessfacts.jsph.pl/*' => Http::response([
            'text' => 'Dolphins sleep with one eye open!',
            'source' => 'djtech.net',
            'source_url' => 'http://www.djtech.net/humor/useless_facts.htm',
        ]),
    ]);

    $this->member = User::factory()->member()->create();
});

/**
 * Answers in place of the ones above: another Http::fake() would only add
 * to them, and the first that matches wins.
 *
 * @param  array<string, mixed>  $answers
 */
function answerInstead(array $answers): void
{
    Http::swap(new Factory);
    Http::fake($answers);
}

test('on this day reads the selected events, the latest first', function () {
    $today = app(OnThisDay::class)->today();

    expect($today['date'])->toBe('October 14')
        ->and(array_column($today['events'], 'year'))->toBe([2021, 1066])
        ->and($today['events'][1])->toBe([
            'year' => 1066,
            'text' => 'The Battle of Hastings was fought.',
            'title' => 'Battle of Hastings',
            'url' => 'https://en.wikipedia.org/wiki/Battle_of_Hastings',
            'image' => 'https://upload.wikimedia.org/hastings.jpg',
        ]);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.wikimedia.org/feed/v1/wikipedia/en/onthisday/selected/10/14');
});

test('the word of the day is read from its Wiktionary page', function () {
    expect(app(WordOfTheDay::class)->today())->toBe([
        'word' => 'Winnie-the-Poohish',
        'kind' => 'adj',
        'meanings' => ['Resembling the book Winnie-the-Pooh (1926).', 'Resembling the bear.'],
        'url' => 'https://en.wiktionary.org/wiki/Winnie-the-Poohish',
    ]);

    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'page=Wiktionary:Word_of_the_day/2026/October_14'));
});

test('NASA\'s picture of the day comes with its explanation and page, using the demo key', function () {
    expect(app(AstronomyPicture::class)->today())->toBe([
        'title' => 'Mars and the Beehive',
        'explanation' => 'Mars joins the Beehive cluster.',
        'image' => 'https://apod.nasa.gov/apod/image/2610/mars.jpg',
        'video' => null,
        'credit' => 'Someone Somewhere',
        'page' => 'https://apod.nasa.gov/apod/ap261014.html',
    ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api_key=DEMO_KEY'));
});

test('the useless fact is today\'s', function () {
    expect(app(UselessFact::class)->today())->toBe([
        'text' => 'Dolphins sleep with one eye open!',
        'source' => 'djtech.net',
        'url' => 'http://www.djtech.net/humor/useless_facts.htm',
    ]);
});

test('each is fetched once a day, and again later after a failure', function () {
    app(UselessFact::class)->today();
    app(UselessFact::class)->today();
    Http::assertSentCount(1);

    answerInstead(['uselessfacts.jsph.pl/*' => Http::response('Down', 500)]);
    Date::setTestNow(now()->addDay());

    expect(app(UselessFact::class)->today())->toBeNull();
    app(UselessFact::class)->today();
    Http::assertSentCount(1);

    $this->travel(31)->minutes();
    app(UselessFact::class)->today();
    Http::assertSentCount(2);
});

test('the new cards show on a member\'s page when chosen, each with its dialog', function () {
    $this->member->update(['home_widgets' => ['on-this-day', 'word', 'nasa', 'fact', 'timer']]);

    $this->actingAs($this->member)->get(route('me'))
        ->assertOk()
        ->assertSeeInOrder([
            'data-dialog-open="on-this-day"', 'On this day · 2021', 'Ten thousand workers',
            'data-dialog-open="word-of-the-day"', 'Winnie-the-Poohish',
            'data-dialog-open="astronomy-picture"', 'Mars and the Beehive',
            'data-dialog-open="useless-fact"', 'Dolphins sleep with one eye open!',
            'data-focus-timer', '25:00',
        ], false)
        ->assertSee('id="on-this-day"', false)
        ->assertSee('Battle of Hastings on Wikipedia', false)
        ->assertSee('© Someone Somewhere', false)
        ->assertSee('id="focus-timer"', false);
});

test('the new cards are not fetched when nobody chose them, and are offered on the account page', function () {
    $this->get(route('home'))->assertOk();
    $this->actingAs($this->member)->get(route('me'))->assertOk();

    Http::assertNothingSent();

    $page = $this->get(route('account.edit'));
    foreach ([HomeWidget::OnThisDay, HomeWidget::WordOfTheDay, HomeWidget::AstronomyPicture, HomeWidget::UselessFact, HomeWidget::FocusTimer] as $widget) {
        $page->assertSee('value="'.$widget->value.'"', false)->assertSee($widget->label());
    }
});

test('a card with nothing to show today is left out', function () {
    answerInstead(['*' => Http::response('Down', 500)]);
    $this->member->update(['home_widgets' => ['on-this-day', 'word', 'nasa', 'fact']]);

    $this->actingAs($this->member)->get(route('me'))
        ->assertOk()
        ->assertDontSee('data-dialog-open="on-this-day"', false)
        ->assertDontSee('data-dialog-open="useless-fact"', false);
});

test('a NASA video day shows a plain card, linking to the video', function () {
    answerInstead(['api.nasa.gov/*' => Http::response([
        'date' => '2026-10-14', 'title' => 'A comet, moving', 'media_type' => 'video', 'url' => 'https://www.youtube.com/embed/abc',
    ])]);
    $this->member->update(['home_widgets' => ['nasa']]);

    $this->actingAs($this->member)->get(route('me'))
        ->assertSee('🔭')
        ->assertSee('href="https://www.youtube.com/embed/abc"', false);
});

test('NASA is asked once more when it answers with an error of its own', function () {
    answerInstead(['api.nasa.gov/*' => Http::sequence()
        ->push(['code' => 500, 'msg' => 'Internal Service Error'], 500)
        ->push(['date' => '2026-10-14', 'title' => 'Second time lucky', 'media_type' => 'image', 'url' => 'https://apod.nasa.gov/x.jpg']),
    ]);

    expect(app(AstronomyPicture::class)->today()['title'])->toBe('Second time lucky');
    Http::assertSentCount(2);
});
