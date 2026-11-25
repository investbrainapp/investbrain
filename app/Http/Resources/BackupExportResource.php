<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackupExportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            'id' => $this->id,
            'status' => $this->status,
            'download_url' => $this->status == 'success'
                ? route('api.export.download', $this->resource)
                : null,
            'created_at' => $this->created_at,
            'completed_at' => $this->completed_at,
            'expires_at' => $this->expiresAt(),
        ];
    }
}
