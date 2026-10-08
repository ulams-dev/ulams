<?php

namespace Ulams\Questionnaire\Tests\Api;

use Ulams\Questionnaire\Database\Seeders\QuestionnairePermissionsSeeder;
use Ulams\Questionnaire\Enums\QuestionTypeEnum;
use Ulams\Questionnaire\Models\Question;
use Ulams\Questionnaire\Models\QuestionAnswer;
use Ulams\Questionnaire\Models\Questionnaire;
use Ulams\Questionnaire\Models\QuestionnaireModel;
use Ulams\Questionnaire\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class QuestionnaireReportTest extends TestCase
{
    use DatabaseTransactions;

    public Questionnaire $questionnaire;
    public QuestionnaireModel $questionnaireModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(QuestionnairePermissionsSeeder::class);
        $this->authenticateAsAdmin();

        $this->questionnaire = Questionnaire::factory()->createOne();
        $this->questionnaireModel = QuestionnaireModel::factory()->createOne();

        Question::factory()
            ->count(20)
            ->create(['type' => QuestionTypeEnum::REVIEW]);

        QuestionAnswer::factory()
            ->count(200)
            ->create();
    }

    public function testCanReadQuestionnaireReport(): void
    {
        $response = $this->actingAs($this->user, 'api')->getJson(
            sprintf('/api/admin/questionnaire/report/%d', $this->questionnaire->id)
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data',
            'message'
        ]);
        $response->assertJsonCount(20, 'data');
    }

    public function testCanReadQuestionnaireReportWithAllParams(): void
    {
        $response = $this->actingAs($this->user, 'api')->getJson(
            sprintf(
                '/api/admin/questionnaire/report/%d/%d/%d',
                $this->questionnaire->id,
                $this->questionnaireModel->model_type_id,
                $this->questionnaireModel->model_id
            )
        );

        $response->assertOk();
    }
}
