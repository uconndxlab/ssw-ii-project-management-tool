<?php

use Illuminate\Support\Facades\File;

it('keeps JavaScript in Vite modules instead of inline script blocks', function () {
    $inlineScripts = collect(File::allFiles(resource_path('views')))
        ->flatMap(function (SplFileInfo $file): array {
            preg_match_all('/<script\b(?![^>]*\bsrc=)(?![^>]*type="application\/json")[^>]*>/i', $file->getContents(), $matches);

            return array_map(fn (string $tag): string => $file->getRelativePathname().': '.$tag, $matches[0]);
        })
        ->values()
        ->all();

    // The layout sets the sidebar rail state before first paint, which a deferred module cannot do.
    expect($inlineScripts)->toBe(['layouts/app.blade.php: <script>']);
});
