<?php

namespace Ulams\Core\Tests\Features;

use Illuminate\Support\Facades\Route;
use Ulams\Core\Tests\TestCase;

class ProtectJsonResponsesTest extends TestCase
{
    public function testJsonAnswersCannotBeEmbeddedByOtherOrigins(): void
    {
        Route::get('api/test/json', fn () => response()->json(['secret' => 1]));

        $this->get('api/test/json')
            ->assertOk()
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function testFilesKeepTheirOwnPolicy(): void
    {
        Route::get('api/test/file', fn () => response('PNG', 200, ['Content-Type' => 'image/png']));
        Route::get('api/test/public-json', fn () => response()->json([], 200, ['Cross-Origin-Resource-Policy' => 'cross-origin']));

        $this->get('api/test/file')->assertHeaderMissing('Cross-Origin-Resource-Policy');
        $this->get('api/test/public-json')->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin');
    }
}
