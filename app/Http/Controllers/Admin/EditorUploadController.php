<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class EditorUploadController extends Controller
{
    public function __invoke(Request $request): Response|\Illuminate\Http\JsonResponse
    {
        $request->validate([
            'upload' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp,svg'],
        ]);

        $path = $request->file('upload')->store('editor', 'public');
        $url = Storage::disk('public')->url($path);
        $fileName = basename($path);

        $funcNum = $request->input('CKEditorFuncNum');

        if ($funcNum !== null && $funcNum !== '') {
            $safeUrl = addslashes($url);
            $funcNum = (int) $funcNum;

            return response(
                "<script>window.parent.CKEDITOR.tools.callFunction({$funcNum}, '{$safeUrl}', '');</script>"
            )->header('Content-Type', 'text/html; charset=utf-8');
        }

        return response()->json([
            'uploaded' => 1,
            'fileName' => $fileName,
            'url' => $url,
        ]);
    }
}