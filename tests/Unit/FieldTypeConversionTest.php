<?php

use App\Enums\FieldType;

test('values are converted between field types', function (FieldType $from, FieldType $to, mixed $value, mixed $expected, array $options = []) {
    expect($to->convertFrom($from, $value, $options))->toBe($expected);
})->with([
    'list to markdown' => [FieldType::List, FieldType::Markdown, ['Flour', 'Eggs'], "- Flour\n- Eggs"],
    'list to long text' => [FieldType::List, FieldType::Textarea, ['Flour', 'Eggs'], "Flour\nEggs"],
    'list to text' => [FieldType::List, FieldType::Text, ['Flour', 'Eggs'], 'Flour, Eggs'],
    'markdown to list' => [FieldType::Markdown, FieldType::List, "- Flour\n* Eggs\n\n1. Milk", ['Flour', 'Eggs', 'Milk']],
    'long text to list' => [FieldType::Textarea, FieldType::List, "one\r\ntwo", ['one', 'two']],
    'text to list' => [FieldType::Text, FieldType::List, 'single', ['single']],
    'multi-line text to text' => [FieldType::Textarea, FieldType::Text, "line one\nline two", 'line one line two'],
    'numeric text to number' => [FieldType::Text, FieldType::Number, ' 4.5 ', 4.5],
    'integer text to number' => [FieldType::Text, FieldType::Number, '12', 12],
    'words to number' => [FieldType::Text, FieldType::Number, 'a few', null],
    'number to text' => [FieldType::Number, FieldType::Text, 4, '4'],
    'yes to boolean' => [FieldType::Text, FieldType::Boolean, 'yes', true],
    'no to boolean' => [FieldType::Text, FieldType::Boolean, 'no', false],
    'other text to boolean' => [FieldType::Text, FieldType::Boolean, 'spicy', true],
    'empty to boolean' => [FieldType::Text, FieldType::Boolean, '', false],
    'boolean to text' => [FieldType::Boolean, FieldType::Text, true, 'Yes'],
    'boolean to list' => [FieldType::Boolean, FieldType::List, true, []],
    'text to matching select option' => [FieldType::Text, FieldType::Select, 'easy', 'Easy', ['Easy', 'Hard']],
    'text to missing select option' => [FieldType::Text, FieldType::Select, 'Medium', null, ['Easy', 'Hard']],
    'text to date' => [FieldType::Text, FieldType::Date, 'March 5, 2024', '2024-03-05'],
    'nonsense to date' => [FieldType::Text, FieldType::Date, 'someday', null],
    'date to text' => [FieldType::Date, FieldType::Text, '2024-03-05', '2024-03-05'],
    'image to text' => [FieldType::Image, FieldType::Text, 12, null],
    'text to image' => [FieldType::Text, FieldType::Image, '12', null],
    'image to gallery' => [FieldType::Image, FieldType::Gallery, 12, [12]],
    'gallery to image' => [FieldType::Gallery, FieldType::Image, [12, 13], 12],
    'gallery to list' => [FieldType::Gallery, FieldType::List, [12, 13], null],
    'list to posts' => [FieldType::List, FieldType::Posts, ['12'], null],
    'posts to gallery' => [FieldType::Posts, FieldType::Gallery, [12], null],
    'web address text to web address' => [FieldType::Text, FieldType::Url, ' https://example.com/app ', 'https://example.com/app'],
    'other text to web address' => [FieldType::Text, FieldType::Url, 'example dot com', null],
    'script to web address' => [FieldType::Text, FieldType::Url, 'javascript:alert(1)', null],
    'web address to text' => [FieldType::Url, FieldType::Text, 'https://example.com', 'https://example.com'],
    'web address to list' => [FieldType::Url, FieldType::List, 'https://example.com', ['https://example.com']],
    'empty stays empty' => [FieldType::Text, FieldType::Number, null, null],
]);

test('long text is cut to fit a text field', function () {
    expect(mb_strlen(FieldType::Text->convertFrom(FieldType::Markdown, str_repeat('word ', 100))))->toBeLessThanOrEqual(255)->toBeGreaterThan(250);
});
