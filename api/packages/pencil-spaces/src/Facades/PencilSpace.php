<?php

namespace EscolaLms\PencilSpaces\Facades;

use Faker\Factory as Faker;
use EscolaLms\PencilSpaces\EscolaLmsPencilSpacesServiceProvider;
use EscolaLms\PencilSpaces\Resource\CreatePencilSpaceResource;
use EscolaLms\PencilSpaces\Services\Contracts\PencilSpacesServiceContract;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;

/**
 * @method static string getDirectLoginUrl(int $userId, string $redirectUrl = null)
 * @method static array createSpace(CreatePencilSpaceResource $createSpaceResource)
 *
* @see \EscolaLms\PencilSpaces\Services\PencilSpacesService
*/
class PencilSpace extends Facade
{
    public static function fake(): string
    {
        $faker = Faker::create();

        Config::set(EscolaLmsPencilSpacesServiceProvider::CONFIG_KEY . '.api_key', $faker->md5);
        Config::set(EscolaLmsPencilSpacesServiceProvider::CONFIG_KEY . '.api_url', $faker->url);

        Http::fake([
            'users/*/authorize*' => Http::response(['url' => $faker->url]),
            'spaces/create' => Http::response(['link' => $faker->url, 'spaceId' => $faker->uuid]),
            'users/createAPIUser' => Http::response(['userId' => $faker->uuid, 'email' => $faker->email])
        ]);

        return static::getFacadeAccessor();
    }

    protected static function getFacadeAccessor(): string
    {
        return PencilSpacesServiceContract::class;
    }
}
