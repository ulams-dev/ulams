<?php

namespace EscolaLms\Consultations\Dto;

class ConsultationUserResourceDto extends BaseDto
{
    public ?int $id = null;
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?bool $isActive = null;
    public ?string $emailVerifiedAt = null;
    public ?string $pathAvatar = null;
    public ?string $gender = null;
    public ?string $age = null;
    public ?string $country = null;
    public ?string $city = null;
    public ?string $street = null;
    public ?string $postcode = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
    public ?int $points = null;
    public ?string $notificationChannels = null;
    public ?string $accessToDirectories = null;
    public ?string $currentTimezone = null;
    public ?string $deletedAt = null;
    public ?string $deleteUserToken = null;
    public ?string $avatarUrl = null;
    public ?array $categories = null;
    public ?string $executedStatus = null;
}
