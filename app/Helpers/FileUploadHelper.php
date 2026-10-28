<?php

namespace App\Helpers;

use App\Models\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadHelper
{
    public static function StoreMedia(
        UploadedFile $file,
        string $folder = 'images/',
        string $diskType = 'public_uploads',
    ): MediaStorage {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $originalName = (string) $file->getClientOriginalName();
        $filename = md5((string) microtime(true).Str::random(8)).($extension !== '' ? '.'.$extension : '');
        $disk = Storage::disk($diskType);

        $folder = trim(str_replace('\\', '/', $folder), '/');
        $folder = $folder === '' ? 'uploads' : $folder;
        $relativePath = $folder.'/'.$filename;

        $disk->put($relativePath, fopen($file->getRealPath(), 'r'), 'public');

        // public_uploads root is public/uploads, so the public URL is /uploads/{folder}/{file}
        $url = $diskType === 'public_uploads'
            ? 'uploads/'.$relativePath
            : ltrim((string) $disk->url($relativePath), '/');

        return MediaStorage::query()->create([
            'user_id' => Auth::id(),
            'name' => pathinfo($originalName, PATHINFO_FILENAME) ?: $filename,
            'extension' => $extension !== '' ? $extension : null,
            'path' => $relativePath,
            'url' => $url,
            'disk' => $diskType,
        ]);
    }

    public static function revert_media(int|string|null $id = null): bool|string
    {
        if ($id === null || $id === '') {
            return false;
        }

        $media = MediaStorage::query()->find($id);

        if (! $media) {
            return false;
        }

        try {
            $disk = Storage::disk($media->disk);

            if ($disk->exists($media->path)) {
                $disk->delete($media->path);
            }

            $media->delete();

            return true;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }
}
