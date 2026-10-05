<?php

use App\Models\Post;
use App\Models\Template;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @param  array<string, mixed>  $data
 * @param  list<array{handle: string, type: string}>  $fields
 */
function summaryOf(array $data, array $fields = [['handle' => 'body', 'type' => 'markdown']], int $length = 180): string
{
    $template = new Template(['fields' => array_map(fn (array $field) => [...$field, 'label' => $field['handle'], 'required' => false, 'options' => []], $fields)]);
    $post = (new Post)->forceFill(['data' => $data]);
    $post->setRelation('template', $template);

    return $post->summary($length);
}

test('the summary is the first paragraphs, as they read on the page', function () {
    expect(summaryOf(['body' => "## Getting started\n\nA paragraph of **notes**, with a [link](https://example.com) and `code`.\n\n![A warm gradient](/storage/media/a.png \"Fresh\")\n\nAnother one"]))
        ->toBe('A paragraph of notes, with a link and code. Another one.');
});

test('an image within a sentence reads as its alt text', function () {
    expect(summaryOf(['body' => 'A small ![dot](/storage/media/dot.png) inside a sentence.

[![Logo](/storage/media/logo.png)](https://example.com)']))
        ->toBe('A small dot inside a sentence.');
});

test('headings, code, quotes, tables, definitions and footnotes are left out', function () {
    expect(summaryOf(['body' => "# Title\n\n```php\necho 1;\n```\n\n> [!TIP]\n> A tip.\n\n| a | b |\n|---|---|\n| 1 | 2 |\n\nTerm\n: Definition\n\nThe text.[^1]\n\n[^1]: A note."]))
        ->toBe('The text.');
});

test('widgets with words of their own keep them, and the rest are left out', function () {
    expect(summaryOf(['body' => 'Rest the dough {{ timer:10 | Proof }} at {{ temp:180c }}, run {{ copy:npm run dev }} for {{ recipe-amount:1 1/2 cups }} until {{ countdown:2026-12-25 | Christmas }}. The end: {{ spoiler:it was a dream }} {{ qr:https://example.com }} {{ youtube:dQw4w9WgXcQ }} {{ stopwatch }}']))
        ->toBe('Rest the dough 10:00 at 180 °C, run npm run dev for 1 1/2 cups until Christmas. The end:');

    expect(summaryOf(['body' => '{{ recipe-servings:12 | cookies }} or {{ recipe-servings:4 }}, kept {{ nothing:here }}']))
        ->toBe('Makes 12 cookies or Serves 4, kept {{ nothing:here }}.');
});

test('keys, highlights and emoji read as text', function () {
    expect(summaryOf(['body' => 'Press [[Ctrl]]+[[C]] to copy ==this== H~2~O :tada:']))
        ->toBe('Press Ctrl+C to copy this H2O 🎉.');
});

test('text fields come first, and a post of only lists is summed up by them', function () {
    $fields = [['handle' => 'intro', 'type' => 'text'], ['handle' => 'tools', 'type' => 'list'], ['handle' => 'body', 'type' => 'markdown']];

    expect(summaryOf(['intro' => 'A short intro', 'tools' => ['Knife'], 'body' => 'Then the method.'], $fields))
        ->toBe('A short intro. Then the method.')
        ->and(summaryOf(['intro' => '', 'tools' => ['Knife', 'Board'], 'body' => "- Laptop\n- **Keyboard**"], $fields))
        ->toBe('Knife, Board, Laptop, Keyboard')
        ->and(summaryOf(['body' => "## Only a heading\n\n```\ncode\n```"]))
        ->toBe('');
});

test('a long summary is cut between words', function () {
    expect(summaryOf(['body' => 'Knead the dough until it is smooth and springs back when pressed.'], length: 30))
        ->toBe('Knead the dough until it is…');
});
