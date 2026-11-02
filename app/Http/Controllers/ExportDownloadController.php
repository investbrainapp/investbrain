<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BackupExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportDownloadController extends Controller
{
    /**
     * Download a finished export. Only the user who created it can download it.
     */
    public function __invoke(Request $request, BackupExport $backupExport): StreamedResponse
    {
        abort_unless($backupExport->user_id == $request->user()->id, 404);

        abort_unless(
            $backupExport->status == 'success' && Storage::disk()->exists($backupExport->path),
            404
        );

        return Storage::disk()->download($backupExport->path, $backupExport->filename());
    }
}
