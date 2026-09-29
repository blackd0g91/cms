<?php

namespace App\Console\Commands;

use App\Cms\ImageVariants;
use App\Models\Media;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:image-variants {--all : Regenerate every image, not only those without resized copies}')]
#[Description('Create resized copies of uploaded images')]
class GenerateImageVariants extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ImageVariants $variants): int
    {
        $media = Media::query()
            ->unless($this->option('all'), fn ($query) => $query->whereNull('variants'))
            ->get()
            ->filter(fn (Media $item) => $variants->supports($item));

        if ($media->isEmpty()) {
            $this->components->info('Every image already has its resized copies.');

            return self::SUCCESS;
        }

        $this->withProgressBar($media, fn (Media $item) => $variants->generate($item));
        $this->newLine(2);
        $this->components->info("Processed {$media->count()} images.");

        return self::SUCCESS;
    }
}
