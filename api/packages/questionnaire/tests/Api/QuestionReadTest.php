<?php

namespace Ulams\Questionnaire\Tests\Api;

use Ulams\Questionnaire\Database\Seeders\QuestionnairePermissionsSeeder;
use Ulams\Questionnaire\Models\Question;
use Ulams\Questionnaire\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class QuestionReadTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(QuestionnairePermissionsSeeder::class);
    }

    public function testAdminCanReadExistingQuestionById(): void
    {
        $this->authenticateAsAdmin();

        $question = Question::factory()->createOne();

        $response = $this->actingAs($this->user, 'api')->getJson('/api/admin/question/' . $question->getKey());
        $response->assertOk();
        $response->assertJsonFragment(collect($question->getAttributes())->except('id')->toArray());
    }
}
