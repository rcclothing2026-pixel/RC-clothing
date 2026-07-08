<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
class Media extends Model
{
    protected $fillable = [
        'original_name',
        'filename',
        'disk',
        'directory',
        'mime_type',
        'size',
        'width',
        'height',
        'alt',
        'uploaded_by',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getPathAttribute(): string
    {
        return $this->directory && $this->directory !== '/'
            ? $this->directory . '/' . $this->filename
            : $this->filename;
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!str_starts_with($this->mime_type ?? '', 'image/')) {
            return null;
        }
        return $this->url;
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->size ?? 0;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    public function getIsImageAttribute(): bool
    {
        return $this->mime_type && str_starts_with($this->mime_type, 'image/');
    }

    public function absolutePath(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    public static function createFromUploadedFile(
        UploadedFile $file,
        string $directory = '/',
        ?string $alt = null,
        ?int $uploadedBy = null,
        string $disk = 'public'
    ): self {
        $filename = $file->hashName();
        $file->store($directory, $disk);

        // Generate WebP siblings (+ 600w/1200w variants) so the front-end
        // can serve responsive srcsets. Failures are logged but never block.
        if ($disk === 'public' && str_starts_with($file->getMimeType() ?? '', 'image/')) {
            $rel = $directory && $directory !== '/' ? $directory.'/'.$filename : $filename;
            \App\Support\ImageOptimizer::optimize($rel, $disk);
        }

        [$width, $height] = static::getImageDimensions($file, $disk, $directory, $filename);

        return static::create([
            'original_name' => $file->getClientOriginalName(),
            'filename' => $filename,
            'disk' => $disk,
            'directory' => $directory,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'alt' => $alt,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    public static function createFromStoredFile(
        string $absolutePath,
        string $relativePath,
        string $directory = '/',
        string $disk = 'public'
    ): ?self {
        if (!file_exists($absolutePath)) {
            return null;
        }

        $filename = basename($relativePath);
        $mime = mime_content_type($absolutePath) ?: 'application/octet-stream';
        $size = filesize($absolutePath);

        [$width, $height] = static::getDimensionsFromPath($absolutePath, $mime);

        return static::create([
            'original_name' => $filename,
            'filename' => $filename,
            'disk' => $disk,
            'directory' => $directory,
            'mime_type' => $mime,
            'size' => $size,
            'width' => $width,
            'height' => $height,
        ]);
    }

    public function getReferences(): array
    {
        $refs = [];
        $url = $this->url;

        // Check product_images
        $images = \DB::table('product_images')
            ->join('products', 'products.id', '=', 'product_images.product_id')
            ->where('product_images.path', 'like', "%{$this->filename}")
            ->select('products.id', 'products.name')
            ->get();
        foreach ($images as $row) {
            $refs[] = ['type' => 'product', 'id' => $row->id, 'label' => $row->name, 'url' => route('admin.products.edit', $row->id)];
        }

        // Check categories
        $cats = \DB::table('categories')
            ->where('image_path', $url)
            ->orWhere('image_path', 'like', "%{$this->filename}")
            ->select('id', 'name')
            ->get();
        foreach ($cats as $row) {
            $refs[] = ['type' => 'category', 'id' => $row->id, 'label' => $row->name, 'url' => route('admin.categories.edit', $row->id)];
        }

        // Check collections
        $cols = \DB::table('collections')
            ->where('image_path', $url)
            ->orWhere('image_path', 'like', "%{$this->filename}")
            ->select('id', 'name')
            ->get();
        foreach ($cols as $row) {
            $refs[] = ['type' => 'collection', 'id' => $row->id, 'label' => $row->name, 'url' => route('admin.collections.edit', $row->id)];
        }

        // Check settings JSON values
        $settings = \DB::table('settings')->get();
        foreach ($settings as $setting) {
            if ($setting->value && str_contains($setting->value, $this->filename)) {
                $refs[] = ['type' => 'setting', 'id' => $setting->key, 'label' => "تنظیم: {$setting->key}", 'url' => route('admin.settings.site')];
            }
        }

        // Check pages blocks JSON
        $pages = \DB::table('pages')->where('blocks', 'like', "%{$this->filename}%")->select('id', 'title')->get();
        foreach ($pages as $row) {
            $refs[] = ['type' => 'page', 'id' => $row->id, 'label' => $row->title, 'url' => route('admin.pages.edit', $row->id)];
        }

        return $refs;
    }

    private static function getImageDimensions(UploadedFile $file, string $disk, string $directory, string $filename): array
    {
        if (!str_starts_with($file->getMimeType() ?? '', 'image/')) {
            return [null, null];
        }
        try {
            $path = Storage::disk($disk)->path($directory ? $directory . '/' . $filename : $filename);
            if (file_exists($path)) {
                [$w, $h] = getimagesize($path);
                return [$w, $h];
            }
        } catch (\Throwable) {
        }
        return [null, null];
    }

    private static function getDimensionsFromPath(string $absolutePath, string $mime): array
    {
        if (!str_starts_with($mime, 'image/')) {
            return [null, null];
        }
        try {
            [$w, $h] = getimagesize($absolutePath);
            return [$w, $h];
        } catch (\Throwable) {
            return [null, null];
        }
    }
}
