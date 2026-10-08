<?php

namespace Ulams\Templates\Tests\Feature;

use BadMethodCallException;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Settings\Models\Setting;
use Ulams\Templates\Core\SettingsVariables;
use Ulams\Templates\Enums\TemplateSectionTypeEnum;
use Ulams\Templates\Facades\Template;
use Ulams\Templates\Tests\Mock\TestChannel;
use Ulams\Templates\Tests\Mock\TestEventUnusable;
use Ulams\Templates\Tests\Mock\TestEventWithGetters;
use Ulams\Templates\Tests\Mock\TestEventWithGettersAndToArray;
use Ulams\Templates\Tests\Mock\TestEventWithNoAccessors;
use Ulams\Templates\Tests\Mock\TestEventWithToArray;
use Ulams\Templates\Tests\Mock\TestVariables;
use Ulams\Templates\Tests\Mock\TestVariablesWithMissingDefaultContent;
use Ulams\Templates\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class FacadeTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        SettingsVariables::clearSettings();
    }

    public function testRegistering()
    {
        Setting::query()->delete();
        Setting::create([
            'key' => 'setting',
            'group' => 'test',
            'value' => 'Test value',
            'public' => true,
            'enumerable' => true,
            'type' => 'text'
        ]);

        Template::register(TestEventWithGettersAndToArray::class, TestChannel::class, TestVariables::class);
        Template::register(TestEventWithGetters::class, TestChannel::class, TestVariables::class);
        Template::register(TestEventWithToArray::class, TestChannel::class, TestVariables::class);
        Template::register(TestEventWithNoAccessors::class, TestChannel::class, TestVariables::class);

        $this->assertEquals(
            [
                TestEventWithGettersAndToArray::class => [
                    TestChannel::class => TestVariables::class,
                ],
                TestEventWithGetters::class => [
                    TestChannel::class => TestVariables::class,
                ],
                TestEventWithToArray::class => [
                    TestChannel::class => TestVariables::class,

                ],
                TestEventWithNoAccessors::class => [
                    TestChannel::class => TestVariables::class,
                ]
            ],
            Template::getRegisteredEvents()
        );

        $registeredEvents = Template::getRegisteredEventsWithTokens();
        $this->assertEquals([
            "class" => "Ulams\Templates\Tests\Mock\TestVariables",
            'assignable_class' => null,
            "variables" =>  [
                0 => "@GlobalSettingsSettingText",
                1 => "@VarUserEmail",
                2 => "@VarFriendEmail"
            ],
            "required_variables" =>  [
                0 => "@VarUserEmail",
                1 => "@VarFriendEmail"
            ],
            "sections" => [
                "title" => [
                    "type" => TemplateSectionTypeEnum::SECTION_TEXT,
                    "required" => true,
                    "readonly" => false,
                    "default_content" => "New friend request",
                    "required_variables" => []
                ],
                "content" =>  [
                    "type" => TemplateSectionTypeEnum::SECTION_HTML,
                    "required" => true,
                    "readonly" => false,
                    "default_content" => '<h1>Hello @VarUserEmail!</h1><br/>' . PHP_EOL . '<p>You have new friend request from @VarFriendEmail</p>',
                    "required_variables" => [
                        0 => "@VarUserEmail",
                        1 => "@VarFriendEmail"
                    ]
                ],
                "url" => [
                    "type" => TemplateSectionTypeEnum::SECTION_URL,
                    "required" => false,
                    "readonly" => false,
                    "default_content" => "",
                    "required_variables" => []
                ]
            ]
        ], $registeredEvents[TestEventWithGettersAndToArray::class][TestChannel::class]);
        $this->assertEquals([
            "@GlobalSettingsSettingText" => "text",
        ], $registeredEvents['user_settings']);
    }

    public function testRegisteringError()
    {
        try {
            Template::register(TestEventWithGettersAndToArray::class, TestChannel::class, TestVariablesWithMissingDefaultContent::class);
        } catch (\Throwable $th) {
            $this->assertEquals('Variable class Ulams\Templates\Tests\Mock\TestVariablesWithMissingDefaultContent can not be used for channel Ulams\Templates\Tests\Mock\TestChannel.', $th->getMessage());
        }
    }

    // TODO: prevent registering Variables for Events that don't contain required data? (not sure if it's even possible, though)
    public function testHandlingUnusableEvent()
    {
        $user = $this->makeStudent();
        Template::register(TestEventUnusable::class, TestChannel::class, TestVariables::class);
        Template::createDefaultTemplatesForChannel(TestChannel::class);
        Template::fake();
        $this->expectException(BadMethodCallException::class);
        try {
            event(new TestEventUnusable($user));
        } catch (\Throwable $th) {
            $this->assertEquals('Call to undefined method Ulams\Templates\Events\EventWrapper::getFriend()', $th->getMessage());
            throw $th;
        }
    }

    public function testHandlingEventWithNoAccessors()
    {
        $user = $this->makeStudent();
        $friend = $this->makeStudent();
        Template::register(TestEventWithNoAccessors::class, TestChannel::class, TestVariables::class);
        Template::createDefaultTemplatesForChannel(TestChannel::class);
        Template::fake();
        event(new TestEventWithNoAccessors($user, $friend));
        Template::assertEventHandled(
            fn ($eventData) => $eventData['event']->eventClass() === TestEventWithNoAccessors::class
                && $eventData['variables'][TestVariables::VAR_USER_EMAIL] === $user->email
                && $eventData['variables'][TestVariables::VAR_FRIEND_EMAIL] === $friend->email
        );
    }

    public function testHandlingEventWithGetters()
    {
        $user = $this->makeStudent();
        $friend = $this->makeStudent();
        Template::register(TestEventWithGetters::class, TestChannel::class, TestVariables::class);
        Template::createDefaultTemplatesForChannel(TestChannel::class);
        Template::fake();
        event(new TestEventWithGetters($user, $friend));
        Template::assertEventHandled(
            fn ($eventData) => $eventData['event']->eventClass() === TestEventWithGetters::class
                && $eventData['variables'][TestVariables::VAR_USER_EMAIL] === $user->email
                && $eventData['variables'][TestVariables::VAR_FRIEND_EMAIL] === $friend->email
        );
    }

    public function testHandlingEventWithToArray()
    {
        $user = $this->makeStudent();
        $friend = $this->makeStudent();
        Template::register(TestEventWithToArray::class, TestChannel::class, TestVariables::class);
        Template::createDefaultTemplatesForChannel(TestChannel::class);
        Template::fake();
        event(new TestEventWithToArray($user, $friend));
        Template::assertEventHandled(
            fn ($eventData) => $eventData['event']->eventClass() === TestEventWithToArray::class
                && $eventData['variables'][TestVariables::VAR_USER_EMAIL] === $user->email
                && $eventData['variables'][TestVariables::VAR_FRIEND_EMAIL] === $friend->email
        );
    }

    public function testHandlingEventWithGettersAndToArray()
    {
        $user = $this->makeStudent();
        $friend = $this->makeStudent();
        Template::register(TestEventWithGettersAndToArray::class, TestChannel::class, TestVariables::class);
        Template::createDefaultTemplatesForChannel(TestChannel::class);
        Template::fake();
        event(new TestEventWithGettersAndToArray($user, $friend));
        Template::assertEventHandled(
            fn ($eventData) => $eventData['event']->eventClass() === TestEventWithGettersAndToArray::class
                && $eventData['variables'][TestVariables::VAR_USER_EMAIL] === $user->email
                && $eventData['variables'][TestVariables::VAR_FRIEND_EMAIL] === $friend->email
        );
    }
}
