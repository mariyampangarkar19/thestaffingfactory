<?php

namespace App\Http\Controllers;

use App\Audit\AuditLogger;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only way a stored file leaves the system: signed-in user, same agency (tenant scope + RLS on the lookup),
 * permission check, download logged. Files are always sent as attachments so nothing uploaded can run in the browser.
 */
final class DocumentDownloadController extends Controller
{
    public function __invoke(Request $request, string $document, AuditLogger $audit): StreamedResponse
    {
        abort_unless($request->user() !== null, 403);

        $record = Document::query()->findOrFail($document);

        Gate::authorize('view', $record);

        abort_unless(Storage::disk($record->disk)->exists($record->path), 404);

        $audit->log('document.downloaded', $record);

        return Storage::disk($record->disk)->download($record->path, $record->original_name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
