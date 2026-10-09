<?php

namespace Database\Seeders;

use Ulams\AssignWithoutAccount\Database\Seeders\AssignWithoutAccountPermissionSeeder;
use Ulams\Auth\Database\Seeders\AuthPermissionSeeder;
use Ulams\Bookmarks\Database\Seeders\BookmarkPermissionSeeder;
use Ulams\BulkNotifications\Database\Seeders\BulkNotificationPermissionSeeder;
use Ulams\Cart\Database\Seeders\CartPermissionSeeder;
use Ulams\Categories\Database\Seeders\CategoriesPermissionSeeder;
use Ulams\Cmi5\Database\Seeders\Cmi5PermissionSeeder;
use Ulams\ConsultationAccess\Database\Seeders\ConsultationAccessPermissionSeeder;
use Ulams\Consultations\Database\Seeders\ConsultationsPermissionSeeder;
use Ulams\Core\Seeders\RoleTableSeeder;
use Ulams\CourseAccess\Database\Seeders\CourseAccessPermissionSeeder;
use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\CoursesImportExport\Database\Seeders\CoursesExportImportPermissionSeeder;
use Ulams\CsvUsers\Database\Seeders\CsvUsersPermissionSeeder;
use Ulams\Dictionaries\Database\Seeders\DictionariesPermissionSeeder;
use Ulams\Files\Database\Seeders\PermissionTableSeeder as FilePermissionTableSeeder;
use Ulams\H5P\Database\Seeders\H5PPermissionSeeder;
use Ulams\Lrs\Database\Seeders\LrsPermissionSeeder;
use Ulams\ModelFields\Database\Seeders\PermissionTableSeeder as ModelFieldsPermissionTableSeeder;
use Ulams\Adapt\Database\Seeders\AdaptPermissionSeeder;
use Ulams\LiaScript\Database\Seeders\LiaScriptPermissionSeeder;
use Ulams\CourseBuilder\Database\Seeders\CourseBuilderPermissionSeeder;
use Ulams\LivingCourse\Database\Seeders\LivingCoursePermissionSeeder;
use Ulams\Lti\Database\Seeders\LtiPermissionSeeder;
use Ulams\Notifications\Database\Seeders\NotificationsPermissionsSeeder;
use Ulams\Pages\Database\Seeders\PermissionTableSeeder as PagesPermissionTableSeeder;
use Ulams\Payments\Database\Seeders\PaymentsPermissionsSeeder;
use Ulams\Permissions\Database\Seeders\PermissionTableSeeder as PermissionsPermissionTableSeeder;
use Ulams\Questionnaire\Database\Seeders\QuestionnairePermissionsSeeder;
use Ulams\Reports\Database\Seeders\ReportsPermissionSeeder;
use Ulams\Scorm\Database\Seeders\PermissionTableSeeder as ScormPermissionTableSeeder;
use Ulams\Settings\Database\Seeders\PermissionTableSeeder as SettingsPermissionTableSeeder;
use Ulams\StationaryEvents\Database\Seeders\StationaryEventPermissionSeeder;
use Ulams\Tags\Database\Seeders\TagsPermissionSeeder;
use Ulams\Tasks\Database\Seeders\TaskPermissionSeeder;
use Ulams\Templates\Database\Seeders\PermissionTableSeeder as TemplatesPermissionTableSeeder;
use Ulams\TemplatesPdf\Database\Seeders\PermissionTableSeeder as TemplatesPdfPermissionTableSeeder;
use Ulams\TopicTypeGift\Database\Seeders\TopicTypeGiftPermissionSeeder;
use Ulams\TopicTypeProject\Database\Seeders\TopicTypeProjectPermissionSeeder;
use Ulams\Translations\Database\Seeders\TranslationsPermissionSeeder;
use Ulams\Video\Database\Seeders\VideoPermissionSeeder;
use Ulams\Vouchers\Database\Seeders\VoucherPermissionsSeeder;
use Ulams\Webinar\Database\Seeders\WebinarsPermissionSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // first populate roles & permissions
        $this->call(RoleTableSeeder::class);
        $this->call(AuthPermissionSeeder::class);
        $this->call(CartPermissionSeeder::class);
        $this->call(FilePermissionTableSeeder::class);
        $this->call(CoursesPermissionSeeder::class);
        $this->call(PaymentsPermissionsSeeder::class);
        $this->call(CategoriesPermissionSeeder::class);
        $this->call(PagesPermissionTableSeeder::class);
        $this->call(ScormPermissionTableSeeder::class);
        $this->call(SettingsPermissionTableSeeder::class);
        $this->call(ReportsPermissionSeeder::class);
        $this->call(CoursesExportImportPermissionSeeder::class);
        $this->call(PermissionsPermissionTableSeeder::class);
        $this->call(NotificationsPermissionsSeeder::class);
        $this->call(TemplatesPermissionTableSeeder::class);
        $this->call(TemplatesPdfPermissionTableSeeder::class);
        $this->call(CsvUsersPermissionSeeder::class);
        $this->call(TagsPermissionSeeder::class);
        $this->call(H5PPermissionSeeder::class);
        $this->call(QuestionnairePermissionsSeeder::class);
        $this->call(ConsultationsPermissionSeeder::class);
        $this->call(AssignWithoutAccountPermissionSeeder::class);
        $this->call(StationaryEventPermissionSeeder::class);
        $this->call(WebinarsPermissionSeeder::class);
        $this->call(ModelFieldsPermissionTableSeeder::class);
        $this->call(VoucherPermissionsSeeder::class);
        $this->call(LrsPermissionSeeder::class);
        $this->call(Cmi5PermissionSeeder::class);
        $this->call(TranslationsPermissionSeeder::class);
        $this->call(VideoPermissionSeeder::class);
        $this->call(TaskPermissionSeeder::class);
        $this->call(CourseAccessPermissionSeeder::class);
        $this->call(BookmarkPermissionSeeder::class);
        $this->call(ConsultationAccessPermissionSeeder::class);
        $this->call(TopicTypeProjectPermissionSeeder::class);
        $this->call(TopicTypeGiftPermissionSeeder::class);
        $this->call(BulkNotificationPermissionSeeder::class);
        $this->call(DictionariesPermissionSeeder::class);
        $this->call(LtiPermissionSeeder::class);
        $this->call(LiaScriptPermissionSeeder::class);
        $this->call(CourseBuilderPermissionSeeder::class);
        $this->call(LivingCoursePermissionSeeder::class);
        $this->call(AdaptPermissionSeeder::class);

        // if there are no users, we need to create first admin 

        $users = DB::table('users')->count();

        if ($users === 0) {
            echo "There are no users, we need to create first admin \n";
            if (env("INITIAL_USER_PASSWORD")) {
                $admin = User::firstOrCreate([
                    'first_name' => env("INITIAL_USER_FIRST_NAME", 'Root'),
                    'last_name' => env("INITIAL_USER_LAST_NAME", 'Admin'),
                    'email' => env("INITIAL_USER_EMAIL", 'admin@ulams.app'),
                    'password' => Hash::make(env("INITIAL_USER_PASSWORD")),
                    'is_active' => 1,
                    'email_verified_at' => Carbon::now(),
                ]);

                $admin->guard_name = 'api';
                $admin->assignRole('admin');
            }
        }
    }
}
