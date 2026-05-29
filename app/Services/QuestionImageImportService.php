<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class QuestionImageImportService
{
    /**
     * Extract a ZIP of images and return a map: lowercase filename => storage path (relative to public disk).
     *
     * @return array<string, string>
     */
    public function extractZip(UploadedFile $zip): array
    {
        $map = [];
        $zipPath = $zip->getRealPath();

        if (! $zipPath || ! class_exists(ZipArchive::class)) {
            return $map;
        }

        $archive = new ZipArchive;
        if ($archive->open($zipPath) !== true) {
            return $map;
        }

        $destDir = 'questions/images/import-'.now()->format('YmdHis');
        Storage::disk('public')->makeDirectory($destDir);

        for ($i = 0; $i < $archive->numFiles; $i++) {
            $name = $archive->getNameIndex($i);
            if (! $name || str_ends_with($name, '/')) {
                continue;
            }

            $basename = basename($name);
            $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                continue;
            }

            $contents = $archive->getFromIndex($i);
            if ($contents === false) {
                continue;
            }

            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $basename) ?: 'image.'.$ext;
            $relativePath = $destDir.'/'.$safeName;

            Storage::disk('public')->put($relativePath, $contents);

            $key = strtolower($basename);
            $map[$key] = $relativePath;
            // Also map without extension variants for flexible CSV matching
            $map[pathinfo($key, PATHINFO_FILENAME)] = $relativePath;
        }

        $archive->close();

        return $map;
    }

    /**
     * Resolve image_path from CSV value using pre-extracted zip map.
     */
    public function resolveImagePath(?string $value, array $imageMap): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        // Already a stored relative path
        if (str_contains($value, 'questions/images/')) {
            return $value;
        }

        $basename = strtolower(basename($value));

        if (isset($imageMap[$basename])) {
            return $imageMap[$basename];
        }

        $stem = pathinfo($basename, PATHINFO_FILENAME);
        if ($stem !== '' && isset($imageMap[$stem])) {
            return $imageMap[$stem];
        }

        // If file exists on public disk as given path
        if (Storage::disk('public')->exists($value)) {
            return $value;
        }

        return null;
    }
}
