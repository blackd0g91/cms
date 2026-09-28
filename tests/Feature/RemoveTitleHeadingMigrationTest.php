<?php

use App\Models\Template;

test('the migration removes the generated title heading but keeps custom ones', function () {
    $generated = Template::factory()->create(['layout' => "<article>\n  <h1>{{ title }}</h1>\n\n  {{ body }}\n</article>\n"]);
    $custom = Template::factory()->create(['layout' => '<h1 class="big">{{ title }}</h1>{{ body }}']);

    $migration = require database_path('migrations/2026_09_28_000006_remove_title_heading_from_layouts.php');
    $migration->up();

    expect($generated->fresh()->layout)->toBe("<article>\n\n  {{ body }}\n</article>\n")
        ->and($custom->fresh()->layout)->toBe('<h1 class="big">{{ title }}</h1>{{ body }}');
});
