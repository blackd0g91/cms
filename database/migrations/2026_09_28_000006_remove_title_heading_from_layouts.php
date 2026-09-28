<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The post page now shows the title (with the thumbnail) above the layout,
 * so drop the plain title heading that generated layouts started with.
 * Customised headings are left alone.
 */
return new class extends Migration
{
    private const string PATTERN = '/^[ \t]*<h1>\s*\{\{\s*title\s*\}\}\s*<\/h1>[ \t]*\R?/m';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('templates')->orderBy('id')->each(function (object $template) {
            $layout = (string) preg_replace(self::PATTERN, '', $template->layout, 1);

            if ($layout !== $template->layout) {
                DB::table('templates')->where('id', $template->id)->update(['layout' => $layout]);
            }
        });
    }
};
