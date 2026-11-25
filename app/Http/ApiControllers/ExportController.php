<?php

declare(strict_types=1);

namespace App\Http\ApiControllers;

use App\Actions\Export\StartBackupExport;
use App\Http\ApiControllers\Controller as ApiController;
use App\Http\Resources\BackupExportResource;
use App\Models\BackupExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends ApiController
{
    /**
     * Start building a backup in the background, or return the one still being prepared.
     */
    public function store(Request $request, StartBackupExport $action): JsonResponse
    {
        return BackupExportResource::make($action->handle($request->user()))
            ->response()
            ->setStatusCode(202);
    }

    public function show(Request $request, BackupExport $backupExport): BackupExportResource
    {
        $this->ensureOwnedBy($request, $backupExport);

        return BackupExportResource::make($backupExport);
    }

    public function download(Request $request, BackupExport $backupExport): StreamedResponse
    {
        $this->ensureOwnedBy($request, $backupExport);

        abort_unless($backupExport->isDownloadable(), 404);

        return Storage::disk()->download($backupExport->path, $backupExport->filename());
    }

    /**
     * Only the user who created an export can see it.
     */
    private function ensureOwnedBy(Request $request, BackupExport $backupExport): void
    {
        abort_unless($backupExport->user_id == $request->user()->id, 404);
    }
}
