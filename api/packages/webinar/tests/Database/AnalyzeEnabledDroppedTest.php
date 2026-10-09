<?php

namespace Ulams\Webinar\Tests\Database;

use Illuminate\Support\Facades\Schema;
use Ulams\Webinar\Tests\TestCase;

class AnalyzeEnabledDroppedTest extends TestCase
{
    public function testTheUnusedAnalyzeEnabledColumnIsGone(): void
    {
        $this->assertFalse(Schema::hasColumn('webinars', 'analyze_enabled'));
    }
}
