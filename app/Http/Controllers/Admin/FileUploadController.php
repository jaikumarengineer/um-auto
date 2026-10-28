<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\FileUploadHelper;
use App\Http\Controllers\Controller;
use App\Models\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FileUploadController extends Controller
{
    /**
     * Allowed MIME types by folder category.
     *
     * @var array<string, list<string>>
     */
    private const MIME_MAP = [
        'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/x-icon'],
        'document' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
        ],
        'video' => ['video/mp4', 'video/quicktime'],
        'default' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
    ];

    /**
     * Map folders to their MIME category.
     *
     * @var array<string, string>
     */
    private const FOLDER_MAP = [
        'avatars' => 'image',
        'logos' => 'image',
        'images' => 'image',
        'media' => 'image',
        'settings' => 'image',
        'documents' => 'document',
        'uploads' => 'default',
    ];

    public function upload(Request $request): JsonResponse
    {
        $folder = (string) ($request->header('folder') ?: $request->input('folder', 'uploads'));
        $folder = trim(preg_replace('#[^a-zA-Z0-9_/\-]#', '', $folder) ?? '', '/');
        $folder = $folder !== '' ? $folder : 'uploads';
        $rootFolder = explode('/', $folder)[0] ?: 'uploads';

        $category = self::FOLDER_MAP[$rootFolder] ?? 'default';
        $allowedMimes = self::MIME_MAP[$category] ?? self::MIME_MAP['default'];

        $validator = Validator::make($request->all(), [
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimetypes:'.implode(',', $allowedMimes),
            ],
        ], [
            'file.mimetypes' => __('The file type is not allowed for this upload location.'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('file') ?: __('Upload validation failed.'),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $actualMime = $finfo->file($file->getRealPath());

            if (! in_array($actualMime, $allowedMimes, true)) {
                return response()->json([
                    'success' => false,
                    'message' => __('File MIME type does not match allowed types.'),
                ], 422);
            }

            $media = FileUploadHelper::StoreMedia($file, $folder);

            return response()->json([
                'success' => true,
                'message' => __('File uploaded successfully.'),
                'data' => [
                    'id' => $media->id,
                    'name' => $media->name,
                    'extension' => $media->extension,
                    'path' => $media->path,
                    'url' => $media->url,
                    'public_url' => $media->publicUrl(),
                    'disk' => $media->disk,
                ],
            ]);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => __('Upload failed.'),
            ], 500);
        }
    }

    public function revert(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated.'),
            ], 401);
        }

        $mediaId = trim((string) $request->getContent());

        if ($mediaId === '' || ! ctype_digit($mediaId)) {
            $mediaId = (string) $request->input('id', '');
        }

        if ($mediaId === '' || ! ctype_digit($mediaId)) {
            return response()->json([
                'success' => false,
                'message' => __('No valid media ID provided.'),
            ], 400);
        }

        $media = MediaStorage::query()->find((int) $mediaId);

        if (! $media) {
            return response()->json([
                'success' => false,
                'message' => __('Media not found.'),
            ], 404);
        }

        $ownerId = $media->user_id;
        if ($ownerId !== null && (int) $ownerId !== (int) $request->user()->id && ! $request->user()->isMaster()) {
            return response()->json([
                'success' => false,
                'message' => __('Forbidden.'),
            ], 403);
        }

        try {
            $result = FileUploadHelper::revert_media($media->id);

            if ($result === true) {
                return response()->json([
                    'success' => true,
                    'message' => __('File removed.'),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => is_string($result) ? $result : __('Delete failed.'),
            ], 400);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => __('Delete failed.'),
            ], 500);
        }
    }
}
