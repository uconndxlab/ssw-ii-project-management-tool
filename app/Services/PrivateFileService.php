<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class PrivateFileService
{
    public function diskName(): string
    {
        return (string) config('filesystems.default');
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, [
            'disk' => $this->diskName(),
        ]);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Failed to store the uploaded file.');
        }

        return $path;
    }

    public function exists(?string $path): bool
    {
        return is_string($path) && $path !== '' && $this->disk()->exists($path);
    }

    public function copy(string $from, string $to): void
    {
        $this->disk()->copy($from, $to);
    }

    public function deleteIfExists(?string $path): void
    {
        if ($this->exists($path)) {
            $this->disk()->delete($path);
        }
    }

    public function serve(string $path, string $downloadName, ?string $mimeType = null): Response
    {
        abort_unless($this->exists($path), 404);

        $disk = $this->disk();
        $ttlMinutes = max(1, (int) config('uploads.temporary_url_minutes', 5));
        $disposition = $this->contentDisposition($downloadName);

        if ($disk instanceof FilesystemAdapter && $disk->providesTemporaryUrls()) {
            $options = [
                'ResponseContentDisposition' => $disposition,
            ];

            if (is_string($mimeType) && $mimeType !== '') {
                $options['ResponseContentType'] = $mimeType;
            }

            return redirect()->away(
                $disk->temporaryUrl($path, now()->addMinutes($ttlMinutes), $options)
            );
        }

        if ($disk instanceof FilesystemAdapter) {
            return $disk->response($path, $downloadName, array_filter([
                'Content-Type' => $mimeType,
                'Content-Disposition' => $disposition,
            ]));
        }

        abort(404);
    }

    private function contentDisposition(string $filename): string
    {
        $fallback = str_replace(['\\', '"', "\r", "\n"], '_', $filename);
        $fallback = preg_replace('/[^\x20-\x7E]/', '_', $fallback) ?: 'download';

        return 'inline; filename="'.$fallback.'"; filename*=UTF-8\'\''.rawurlencode($filename);
    }
}
