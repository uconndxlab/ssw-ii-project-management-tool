<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class PrivateFileService
{
    private const BLOCKED_MIMES = [
        'text/html',
        'application/xhtml+xml',
        'image/svg+xml',
        'text/xml',
        'application/xml',
        'text/javascript',
        'application/javascript',
        'application/x-javascript',
    ];

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
        $mime = strtolower((string) $file->getMimeType());

        if ($mime !== '' && in_array($mime, self::BLOCKED_MIMES, true)) {
            throw ValidationException::withMessages([
                'file' => 'This file type is not allowed.',
            ]);
        }

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

        if ((! is_string($mimeType) || $mimeType === '') && $disk instanceof FilesystemAdapter) {
            $mimeType = $disk->mimeType($path) ?: null;
        }

        if (is_string($mimeType) && in_array(strtolower($mimeType), self::BLOCKED_MIMES, true)) {
            $mimeType = 'application/octet-stream';
        }

        $ttlMinutes = max(1, (int) config('uploads.temporary_url_minutes', 5));
        $inline = is_string($mimeType)
            && in_array(strtolower($mimeType), config('uploads.inline_mimes', []), true);
        $disposition = $this->contentDisposition($downloadName, $inline ? 'inline' : 'attachment');
        $responseMime = $inline ? $mimeType : 'application/octet-stream';

        if ($disk instanceof FilesystemAdapter && $disk->providesTemporaryUrls()) {
            $options = [
                'ResponseContentDisposition' => $disposition,
            ];

            if (is_string($responseMime) && $responseMime !== '') {
                $options['ResponseContentType'] = $responseMime;
            }

            return redirect()->away(
                $disk->temporaryUrl($path, now()->addMinutes($ttlMinutes), $options)
            );
        }

        if ($disk instanceof FilesystemAdapter) {
            return $disk->response($path, $downloadName, array_filter([
                'Content-Type' => $responseMime,
                'Content-Disposition' => $disposition,
            ]));
        }

        abort(404);
    }

    private function contentDisposition(string $filename, string $disposition = 'attachment'): string
    {
        $fallback = str_replace(['\\', '"', "\r", "\n"], '_', $filename);
        $fallback = preg_replace('/[^\x20-\x7E]/', '_', $fallback) ?: 'download';

        return $disposition.'; filename="'.$fallback.'"; filename*=UTF-8\'\''.rawurlencode($filename);
    }
}
