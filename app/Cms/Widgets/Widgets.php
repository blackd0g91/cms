<?php

namespace App\Cms\Widgets;

use Illuminate\Container\Attributes\Singleton;

/**
 * The widgets available in markdown, by name.
 */
#[Singleton]
class Widgets
{
    /**
     * @var array<string, Widget>
     */
    private array $widgets = [];

    public function __construct()
    {
        foreach ([new QrWidget] as $widget) {
            $this->widgets[$widget->name()] = $widget;
        }
    }

    public function get(string $name): ?Widget
    {
        return $this->widgets[strtolower($name)] ?? null;
    }
}
