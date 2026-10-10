<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class MediaController extends Controller
{
    public function index(Request $request): View
    {
        $query = Media::query()->latest();

        if ($search = $request->string('q')->toString()) {
            $query->where('original_name', 'like', "%{$search}%");
        }

        if ($type = $request->string('type')->toString()) {
            if ($type === 'image') {
                $query->where('mime_type', 'like', 'image/%');
            } elseif ($type === 'document') {
                $query->where('mime_type', 'not like', 'image/%');
            }
        }

        $media = $query->paginate(24);

        return view('admin.media.index', compact('media'));
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'required|file|max:10240',
        ]);

        $uploaded = [];
        foreach ($request->file('files') as $file) {
            $record = Media::createFromUploadedFile(
                $file,
                $request->input('directory', '/'),
                null,
                $request->user()?->id
            );
            $uploaded[] = [
                'id' => $record->id,
                'url' => $record->url,
                'original_name' => $record->original_name,
            ];
        }

        return response()->json(['ok' => true, 'files' => $uploaded]);
    }

    public function show(Media $media): View
    {
        return view('admin.media.show', compact('media'));
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        $request->validate([
            'alt' => 'nullable|string|max:500',
        ]);

        $media->update(['alt' => $request->input('alt')]);

        return back()->with('success', 'رسانه به‌روزرسانی شد.');
    }

    public function crop(Request $request, Media $media): JsonResponse
    {
        $request->validate([
            'x' => 'required|numeric|min:0',
            'y' => 'required|numeric|min:0',
            'width' => 'required|numeric|min:1',
            'height' => 'required|numeric|min:1',
        ]);

        if (!$media->is_image) {
            return response()->json(['ok' => false, 'error' => 'فایل تصویر نیست'], 422);
        }

        try {
            $img = Image::read($media->absolutePath());
            $img->crop((int) $request->width, (int) $request->height, (int) $request->x, (int) $request->y);
            $img->save($media->absolutePath());

            [$w, $h] = getimagesize($media->absolutePath());
            $media->update(['width' => $w, 'height' => $h]);

            return response()->json(['ok' => true, 'url' => $media->url, 'width' => $w, 'height' => $h]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function resize(Request $request, Media $media): JsonResponse
    {
        $request->validate([
            'width' => 'required|numeric|min:1|max:10000',
            'height' => 'nullable|numeric|min:1|max:10000',
        ]);

        if (!$media->is_image) {
            return response()->json(['ok' => false, 'error' => 'فایل تصویر نیست'], 422);
        }

        try {
            $img = Image::read($media->absolutePath());
            $w = (int) $request->width;
            $h = $request->height ? (int) $request->height : null;
            $h ? $img->resize($w, $h) : $img->scale($w);
            $img->save($media->absolutePath());

            [$nw, $nh] = getimagesize($media->absolutePath());
            $media->update(['width' => $nw, 'height' => $nh]);

            return response()->json(['ok' => true, 'url' => $media->url, 'width' => $nw, 'height' => $nh]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function rotate(Request $request, Media $media): JsonResponse
    {
        $request->validate([
            'degrees' => 'required|numeric|min:-360|max:360',
        ]);

        if (!$media->is_image) {
            return response()->json(['ok' => false, 'error' => 'فایل تصویر نیست'], 422);
        }

        try {
            $img = Image::read($media->absolutePath());
            $img->rotate((float) $request->degrees);
            $img->save($media->absolutePath());

            [$w, $h] = getimagesize($media->absolutePath());
            $media->update(['width' => $w, 'height' => $h]);

            return response()->json(['ok' => true, 'url' => $media->url, 'width' => $w, 'height' => $h]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, Media $media): JsonResponse
    {
        $refs = $media->getReferences();

        $force = $request->boolean('force');

        if (!empty($refs) && !$force) {
            return response()->json([
                'ok' => false,
                'references' => $refs,
                'message' => 'این فایل در موارد زیر استفاده شده است. برای حذف مجدداً تأیید کنید.',
            ]);
        }

        $this->deleteFile($media);
        $media->delete();

        return response()->json(['ok' => true]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            return response()->json(['ok' => false, 'error' => 'هیچ فایلی انتخاب نشده'], 422);
        }

        $force = $request->boolean('force');
        $allRefs = [];
        $deleted = [];

        foreach (Media::whereIn('id', $ids)->get() as $media) {
            $refs = $media->getReferences();
            if (!empty($refs) && !$force) {
                $allRefs = array_merge($allRefs, $refs);
                continue;
            }
            $this->deleteFile($media);
            $media->delete();
            $deleted[] = $media->id;
        }

        if (!empty($allRefs)) {
            return response()->json([
                'ok' => false,
                'references' => $allRefs,
                'deleted' => $deleted,
                'message' => 'برخی فایل‌ها در حال استفاده هستند. برای حذف مجدداً تأیید کنید.',
            ]);
        }

        return response()->json(['ok' => true, 'deleted' => $deleted]);
    }

    private function deleteFile(Media $media): void
    {
        try {
            if (Storage::disk($media->disk)->exists($media->path)) {
                Storage::disk($media->disk)->delete($media->path);
            }
        } catch (\Throwable) {
        }
    }
}
