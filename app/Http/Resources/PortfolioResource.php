<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortfolioResource extends JsonResource
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
            'title' => $this->title,
            'wishlist' => $this->wishlist,
            'owner' => UserResource::make($this->owner),
            'transactions' => TransactionResource::collection($this->whenLoaded('transactions')),
            'holdings' => HoldingResource::collection($this->whenLoaded('holdings')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
