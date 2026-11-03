<?php

declare(strict_types=1);

namespace Ulams\LearnerInsights\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ulams\LearnerInsights\Enums\LearnerInsightsPermissionsEnum;

class LearnerInsightsPermissionsEnumQwenTest extends TestCase
{
    public function testAllReturnsCorrectPermissions()
    {
        $expectedPermissions = [
            LearnerInsightsPermissionsEnum::LEARNER_INSIGHTS_VIEW,
            LearnerInsightsPermissionsEnum::LEARNER_INSIGHTS_VIEW_INDIVIDUALS,
            LearnerInsightsPermissionsEnum::LEARNER_INSIGHTS_MANAGE,
        ];

        $this->assertEquals($expectedPermissions, LearnerInsightsPermissionsEnum::all());
    }

    public function testLEARNER_INSIGHTS_VIEWConstantValue()
    {
        $this->assertEquals('learner_insights_view', LearnerInsightsPermissionsEnum::LEARNER_INSIGHTS_VIEW);
    }

    public function testLEARNER_INSIGHTS_VIEW_INDIVIDUALSConstantValue()
    {
        $this->assertEquals('learner_insights_view_individuals', LearnerInsightsPermissionsEnum::LEARNER_INSIGHTS_VIEW_INDIVIDUALS);
    }

    public function testLEARNER_INSIGHTS_MANAGEConstantValue()
    {
        $this->assertEquals('learner_insights_manage', LearnerInsightsPermissionsEnum::LEARNER_INSIGHTS_MANAGE);
    }
}
