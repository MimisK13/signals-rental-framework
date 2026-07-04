<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Document;
use App\Services\Documents\DocumentTypeRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class FileService
{
    /**
     * Get the storage disk name for file operations.
     *
     * Uses S3 in production. Falls back to `public` disk for local development
     * so files are accessible via the `/storage` symlink.
     */
    private function disk(): string
    {
        $disk = config('filesystems.default', 'local');

        // The 'local' disk stores in storage/app/ which isn't web-accessible.
        // Use 'public' disk instead so icons/attachments can be served via /storage.
        if ($disk === 'local') {
            return 'public';
        }

        return $disk;
    }

    /**
     * Upload a file and create an attachment record.
     */
    public function upload(
        UploadedFile $file,
        Model $entity,
        ?string $category = null,
        ?string $description = null,
    ): Attachment {
        $uuid = (string) Str::uuid();
        $extension = $file->getClientOriginalExtension();
        $path = $this->storePath($uuid, $extension, $entity);
        $disk = $this->disk();

        $stored = Storage::disk($disk)->put($path, $file->getContent());

        if (! $stored) {
            throw new \RuntimeException("Failed to store file at path [{$path}] on disk [{$disk}].");
        }

        return Attachment::create([
            'uuid' => $uuid,
            'attachable_type' => $entity->getMorphClass(),
            'attachable_id' => $entity->getKey(),
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'disk' => $disk,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'category' => $category,
            'description' => $description,
            'uploaded_by' => auth()->id(),
        ]);
    }

    /**
     * Upload an icon image and generate a 150x150 thumbnail.
     *
     * @return array{icon_url: string, icon_thumb_url: string}
     */
    public function uploadIcon(UploadedFile $file, Model $entity): array
    {
        $uuid = (string) Str::uuid();
        $extension = $file->getClientOriginalExtension() ?: 'jpg';

        $entityDir = strtolower(class_basename($entity)).'s/'.$entity->getKey();
        $iconPath = "icons/{$entityDir}/{$uuid}.{$extension}";
        $thumbPath = "icons/{$entityDir}/thumbs/{$uuid}.{$extension}";
        $disk = $this->disk();

        // Store original
        $stored = Storage::disk($disk)->put($iconPath, $file->getContent());

        if (! $stored) {
            throw new \RuntimeException("Failed to store icon at path [{$iconPath}] on disk [{$disk}].");
        }

        // Generate and store 150x150 thumbnail per spec
        $thumbnail = $this->generateThumbnail($file, 150, 150);
        $thumbStored = Storage::disk($disk)->put($thumbPath, $thumbnail);

        if (! $thumbStored) {
            Storage::disk($disk)->delete($iconPath);
            throw new \RuntimeException("Failed to store thumbnail at path [{$thumbPath}] on disk [{$disk}].");
        }

        return [
            'icon_url' => $iconPath,
            'icon_thumb_url' => $thumbPath,
        ];
    }

    /**
     * Delete an attachment from storage and database.
     */
    public function delete(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->file_path);

        if ($attachment->thumb_path) {
            Storage::disk($attachment->disk)->delete($attachment->thumb_path);
        }

        $attachment->delete();
    }

    /**
     * Store binary content using UUID-based paths.
     */
    public function storeBinary(
        string $content,
        string $directory,
        string $extension,
        ?Model $entity = null,
    ): string {
        $uuid = (string) Str::uuid();
        $disk = $this->disk();

        if ($entity !== null) {
            $entityType = strtolower(class_basename($entity));
            $entityId = $entity->getKey();
            $path = "{$directory}/{$entityType}/{$entityId}/{$uuid}.{$extension}";
        } else {
            $path = "{$directory}/{$uuid}.{$extension}";
        }

        $stored = Storage::disk($disk)->put($path, $content);

        if (! $stored) {
            throw new \RuntimeException("Failed to store file at path [{$path}] on disk [{$disk}].");
        }

        return $path;
    }

    /**
     * Generate a signed temporary URL for a file path.
     */
    public function signedUrl(string $path, int $expiry = 60): string
    {
        $disk = $this->disk();

        if ($disk === 'local' || $disk === 'public') {
            return Storage::disk($disk)->url($path);
        }

        return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($expiry));
    }

    /**
     * Generate a signed temporary URL for a file path, returning null when the
     * path is empty or the underlying URL generation fails.
     *
     * Lets callers render a stored file's URL without duplicating the
     * null-path guard and try/catch fallback at each call site.
     */
    public function signedUrlOrNull(?string $path, int $expiry = 60): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        try {
            return $this->signedUrl($path, $expiry);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Read stored file contents from the configured disk.
     */
    public function getContents(string $path): string
    {
        $contents = Storage::disk($this->disk())->get($path);

        if ($contents === null) {
            throw new \RuntimeException("File not found at path [{$path}].");
        }

        return $contents;
    }

    /**
     * Infer a MIME type from a stored file path.
     */
    public function mimeTypeForPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'html', 'htm' => 'text/html; charset=utf-8',
            default => 'application/octet-stream',
        };
    }

    /**
     * Build a human-readable filename for a stored document.
     */
    public function documentFilename(Document $document, string $path): string
    {
        $number = trim($document->document_number);

        if ($number !== '') {
            $registry = app(DocumentTypeRegistry::class);
            $typeSlug = $registry->has($document->document_type)
                ? $registry->filenameSlug($document->document_type)
                : Str::slug(str_replace('_', '-', $document->document_type), '-');

            return $this->inlineFilename($number, $typeSlug, $path);
        }

        return $this->fallbackDocumentFilename($document->document_type, $document->id, $path);
    }

    /**
     * Build a safe inline filename for Content-Disposition headers.
     */
    public function inlineFilename(string $number, string $documentTypeLabel, string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $sanitizedNumber = preg_replace('/[^A-Za-z0-9._-]+/', '-', $number);
        $sanitizedLabel = preg_replace('/[^a-z0-9-]+/', '-', strtolower($documentTypeLabel));
        $sanitizedLabel = trim($sanitizedLabel, '-');

        return $sanitizedNumber.'-'.$sanitizedLabel.'.'.$extension;
    }

    /**
     * Build a fallback filename when a document has no assigned number.
     */
    public function fallbackDocumentFilename(string $documentType, int $documentId, string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $sanitizedType = preg_replace('/[^A-Za-z0-9._-]+/', '-', $documentType);

        return $sanitizedType.'-'.$documentId.'.'.$extension;
    }

    /**
     * Generate a signed temporary URL for a document download with a friendly filename.
     */
    public function signedDocumentUrlOrNull(?Document $document, int $expiry = 60): ?string
    {
        if ($document === null) {
            return null;
        }

        $path = $document->pdf_path ?? $document->html_snapshot_path;

        if ($path === null || $path === '') {
            return null;
        }

        try {
            return $this->signedDocumentUrl($document, $path, $expiry);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Generate a signed temporary URL for a document path with Content-Disposition metadata.
     */
    public function signedDocumentUrl(Document $document, string $path, int $expiry = 60): string
    {
        $filename = $this->documentFilename($document, $path);
        $disk = $this->disk();

        if ($disk === 'local' || $disk === 'public') {
            return URL::temporarySignedRoute(
                'documents.signed-download',
                now()->addMinutes($expiry),
                ['documentId' => $document->id],
            );
        }

        return Storage::disk($disk)->temporaryUrl(
            $path,
            now()->addMinutes($expiry),
            [
                'ResponseContentDisposition' => 'inline; filename="'.$filename.'"',
            ],
        );
    }

    /**
     * Generate a thumbnail image using Intervention Image.
     */
    private function generateThumbnail(UploadedFile $file, int $width, int $height): string
    {
        $image = Image::read($file->getContent());
        $image->cover($width, $height);

        return $image->toJpeg(quality: 92)->toString();
    }

    /**
     * Generate a UUID-based storage path organised by entity type and ID.
     */
    private function storePath(string $uuid, string $extension, ?Model $entity = null): string
    {
        if ($entity !== null) {
            $entityType = strtolower(class_basename($entity));
            $entityId = $entity->getKey();

            return "attachments/{$entityType}/{$entityId}/{$uuid}.{$extension}";
        }

        return "attachments/{$uuid}.{$extension}";
    }
}
