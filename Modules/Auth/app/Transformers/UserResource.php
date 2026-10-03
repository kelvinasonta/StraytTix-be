<?php

namespace Modules\Auth\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'email' => $this->email,
            'isEmailVerified' => $this->email_verified_at ? true : false,
            'accountType' => $this->account_type,
        ];
    }
}
