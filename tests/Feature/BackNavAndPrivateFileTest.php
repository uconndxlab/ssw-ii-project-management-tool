<?php

use App\Services\PrivateFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('program create breadcrumbs link back to the programs index', function () {
    $admin = createSystemAdmin();
    $indexUrl = route('programs.index');
    $indexCrumb = '<a href="'.$indexUrl.'" class="text-decoration-none">Programs</a>';

    $this->actingAs($admin)->get($indexUrl)->assertOk();

    $create = $this->actingAs($admin)->get(route('programs.create'));
    $create->assertOk();
    $create->assertSee($indexCrumb, false);
});

test('private file service stores an uploaded file', function () {
    Storage::fake(config('filesystems.default'));

    $path = app(PrivateFileService::class)->store(
        UploadedFile::fake()->create('memo.pdf', 20, 'application/pdf'),
        'agreement-attachments'
    );

    expect($path)->toBeString()->not->toBe('')
        ->and(Storage::exists($path))->toBeTrue();
});
