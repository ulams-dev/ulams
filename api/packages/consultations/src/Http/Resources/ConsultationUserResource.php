<?php

namespace EscolaLms\Consultations\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ConsultationUserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->resource->id,
            'first_name' => $this->resource->firstName,
            'last_name' => $this->resource->lastName,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'is_active' => $this->resource->isActive,
            'email_verified_at' => $this->resource->emailVerifiedAt,
            'path_avatar' => $this->resource->pathAvatar,
            'gender' => $this->resource->gender,
            'age' => $this->resource->age,
            'country' => $this->resource->country,
            'city' => $this->resource->city,
            'street' => $this->resource->street,
            'postcode' => $this->resource->postcode,
            'created_at' => $this->resource->createdAt,
            'updated_at' => $this->resource->updatedAt,
            'points' => $this->resource->points,
            'notification_channels' => $this->resource->notificationChannels,
            'access_to_directories' => $this->resource->accessToDirectories,
            'current_timezone' => $this->resource->currentTimezone,
            'deleted_at' => $this->resource->deletedAt,
            'delete_user_token' => $this->resource->deleteUserToken,
            'avatar_url' => $this->resource->avatarUrl,
            'categories' => $this->resource->categories,
            'executed_status' => $this->resource->executedStatus,
        ];
    }
}
