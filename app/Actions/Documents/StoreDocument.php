<?php

namespace App\Actions\Documents;

use App\Audit\AuditLogger;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Exceptions\DomainRuleViolation;
use App\Models\Document;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * The only way a file enters the system. Files go to the private disk under the agency's own folder,
 * get a random name (the original name is kept as text only), and are checked by real content type and size.
 */
final class StoreDocument
{
    public function __construct(private readonly TenantContext $tenant, private readonly AuditLogger $audit) {}

    public function handle(Model $owner, UploadedFile $file, DocumentType $type, ?string $title = null, bool $visibleToFreelancer = false, ?string $uploadedBy = null, ?string $expiresOn = null): Document
    {
        if (! $file->isValid()) {
            throw new DomainRuleViolation(__('The upload did not complete. Please try again.'));
        }

        $mime = (string) $file->getMimeType(); // sniffed from the content, not the browser's claim

        if (! in_array($mime, $type->mimeTypes(), true)) {
            throw new DomainRuleViolation(__('This file type is not accepted for :type.', ['type' => $type->getLabel()]));
        }

        if ($file->getSize() > $type->maxKilobytes() * 1024) {
            throw new DomainRuleViolation(__('This file is too large. The limit is :mb MB.', ['mb' => (int) ($type->maxKilobytes() / 1024)]));
        }

        $sha256 = (string) hash_file('sha256', (string) $file->getRealPath());
        $extension = $file->guessExtension() ?: 'bin';
        $directory = $this->tenant->require()->getKey().'/'.$owner->getMorphClass().'/'.$owner->getKey();
        $path = $file->storeAs($directory, Str::uuid7().'.'.$extension, ['disk' => 'private']);

        if ($path === false) {
            throw new DomainRuleViolation(__('The file could not be saved. Please try again.'));
        }

        $document = new Document([
            'type' => $type,
            'title' => $title,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 200, ''),
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'visible_to_freelancer' => $visibleToFreelancer,
            'expires_on' => $expiresOn,
        ]);
        $document->forceFill([
            'documentable_type' => $owner->getMorphClass(),
            'documentable_id' => $owner->getKey(),
            'disk' => 'private',
            'path' => $path,
            'sha256' => $sha256,
            'status' => DocumentStatus::Pending,
            'uploaded_by' => $uploadedBy,
        ])->save();

        $this->audit->log('document.uploaded', $document, ['type' => $type->value]);

        return $document;
    }
}
