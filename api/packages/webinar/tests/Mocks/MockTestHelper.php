<?php

namespace Ulams\Webinar\Tests\Mocks;

use Illuminate\Foundation\Testing\WithFaker;

abstract class MockTestHelper
{
    use WithFaker;

    public function __construct()
    {
        $this->faker = $this->makeFaker();
    }
}
