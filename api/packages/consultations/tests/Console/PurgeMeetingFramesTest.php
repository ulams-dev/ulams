<?php

namespace Ulams\Consultations\Tests\Console;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Ulams\Consultations\Tests\TestCase;

class PurgeMeetingFramesTest extends TestCase
{
    private function seedFrames(): void
    {
        Storage::fake();
        Storage::put('consultation/4/1760000000/9/1760000001.jpg', 'x');
        Storage::put('consultation/4/1760000000/9/1760000002.jpg', 'x');
        Storage::put('consultation/4/images/logotype.png', 'x');
        Storage::put('webinar/7/1760000500/3/1760000501.jpg', 'x');
        Storage::put('webinar/7/images/cover.png', 'x');
        Storage::put('webinar/7/notes.txt', 'x');
        Storage::put('other/1/1760000000/1/a.jpg', 'x');
    }

    public function testDryRunListsAndDeletesNothing(): void
    {
        $this->seedFrames();

        $this->artisan('meetings:purge-frames', ['--dry-run' => true])
            ->expectsOutputToContain('would delete consultation/4/1760000000 (2 files)')
            ->expectsOutputToContain('would delete webinar/7/1760000500 (1 files)')
            ->assertSuccessful();

        Storage::assertExists('consultation/4/1760000000/9/1760000001.jpg');
        Storage::assertExists('webinar/7/1760000500/3/1760000501.jpg');
    }

    public function testItDeletesFramesAndKeepsImagesAndOtherFiles(): void
    {
        $this->seedFrames();

        $this->artisan('meetings:purge-frames')->assertSuccessful();

        Storage::assertMissing('consultation/4/1760000000/9/1760000001.jpg');
        Storage::assertMissing('consultation/4/1760000000/9/1760000002.jpg');
        Storage::assertMissing('webinar/7/1760000500/3/1760000501.jpg');
        Storage::assertExists('consultation/4/images/logotype.png');
        Storage::assertExists('webinar/7/images/cover.png');
        Storage::assertExists('webinar/7/notes.txt');
        Storage::assertExists('other/1/1760000000/1/a.jpg');

        // running it again is harmless
        $this->artisan('meetings:purge-frames')->expectsOutputToContain('0 term folder(s)')->assertSuccessful();
    }

    public function testTheAnalyzeEnabledColumnsAreGone(): void
    {
        $this->assertFalse(Schema::hasColumn('consultations', 'analyze_enabled'));
    }
}
