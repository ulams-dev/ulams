<?php

namespace Database\Seeders\Demo;

use Ulams\LiaScript\Services\LiaScriptService;
use Carbon\Carbon;
use Database\Seeders\Demo\Support\AssetFactory;
use Database\Seeders\Demo\Support\Canvas;
use Database\Seeders\Demo\Support\H5PPackageBuilder;
use Database\Seeders\Demo\Support\TopicFactoryHelper;
use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use Ulams\Auth\Dtos\UserSaveDto;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\Contracts\UserServiceContract;
use Ulams\Cart\Models\Product;
use Ulams\TemplatesPdf\Pdfme\CertificateTemplates;
use Ulams\Cart\Services\Contracts\ProductServiceContract;
use Ulams\Categories\Dtos\CategoryDto;
use Ulams\Categories\Models\Category;
use Ulams\Categories\Services\Contracts\CategoryServiceContracts;
use Ulams\Cmi5\Services\Contracts\Cmi5UploadServiceContract;
use Ulams\Consultations\Dto\ConsultationDto;
use Ulams\Consultations\Models\Consultation;
use Ulams\Consultations\Services\Contracts\ConsultationServiceContract;
use Ulams\Core\Models\User as CoreUser;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Events\CourseFinished;
use App\Models\Course as ProductableCourse;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Repositories\Contracts\CourseRepositoryContract;
use Ulams\Courses\Repositories\Contracts\LessonRepositoryContract;
use Ulams\Courses\Services\Contracts\CourseServiceContract;
use Ulams\H5P\Exceptions\H5PServiceException;
use Ulams\H5P\Services\Contracts\H5PServiceClientContract;
use Ulams\Scorm\Services\Contracts\ScormServiceContract;
use Ulams\StationaryEvents\Models\StationaryEvent;
use Ulams\StationaryEvents\Services\Contracts\StationaryEventServiceContract;
use Ulams\Templates\Models\Template;
use Ulams\Templates\Services\Contracts\TemplateServiceContract;
use Ulams\TemplatesPdf\Core\PdfChannel;
use Ulams\TemplatesPdf\Courses\UserFinishedCourseVariables;
use Ulams\TopicTypes\Models\TopicContent\H5P;
use Ulams\Vouchers\Models\Coupon;
use Ulams\Vouchers\Services\Contracts\CouponServiceContract;
use Ulams\Webinar\Dto\WebinarDto;
use Ulams\Webinar\Models\Webinar;
use Ulams\Webinar\Services\Contracts\WebinarServiceContract;

/**
 * One demo course with everything around it (tutors, students, categories,
 * products, events, voucher, certificate). Subclasses describe the content;
 * this class creates it through the domain services, the way the admin panel
 * does, so events and side effects fire as for a human author.
 */
abstract class DemoExperience
{
    protected AssetFactory $assets;
    protected TopicFactoryHelper $topics;
    protected ?Command $command;
    protected Course $course;
    /** @var array<string, User> */
    protected array $users = [];
    /** @var array<string, mixed> */
    protected array $report = ['lessons' => 0, 'topics' => [], 'questions' => [], 'skipped' => [], 'extras' => []];
    private ?bool $h5pAvailable = null;

    public function __construct(?Command $command = null)
    {
        $this->command = $command;
        $this->assets = new AssetFactory($this->key());
        $this->topics = app(TopicFactoryHelper::class);
    }

    /** Short key used by DEMO_EXPERIENCE (coffee, oncall, nightsky). */
    abstract public function key(): string;

    /** Course fields: title, subtitle, summary, description, level, language, ... */
    abstract protected function courseFields(): array;

    /**
     * Users created for the experience, keyed by a handle.
     *
     * @return array<string, array{first_name: string, last_name: string, email: string, role: string, color?: string}>
     */
    abstract protected function people(): array;

    /** @return array<int, array{parent: ?string, name: string}> */
    abstract protected function categories(): array;

    /** @return array<int, string> */
    abstract protected function tags(): array;

    /** @return array{image: string, poster: string, teaser: ?string} local paths */
    abstract protected function courseMedia(): array;

    /**
     * Lessons with their topics. Each topic: type, title, flags and either
     * static `fields`/`files` or a `make` closure returning them (lazy, so
     * media is generated only when the course is built).
     *
     * @return array<int, array<string, mixed>>
     */
    abstract protected function program(): array;

    /** Products, events, consultations, vouchers around the course. */
    abstract protected function commerce(): void;

    /** Certificate name for the templates-pdf CourseFinished template. */
    abstract protected function certificateName(): string;

    public function title(): string
    {
        return $this->courseFields()['title'];
    }

    /** @return array<string, mixed> */
    public function run(bool $refresh): array
    {
        $this->info('');
        $this->info('<info>' . $this->title() . '</info>');
        $this->ensurePeople();

        $existing = Course::query()->where('title', $this->title())->first();
        // a draft is what an interrupted run leaves behind: rebuild it
        if ($existing && !$refresh && $existing->status !== CourseStatusEnum::DRAFT) {
            $this->course = $existing;
            $this->updateCourseMeta();
            $lessonIds = $existing->lessons()->pluck('id');
            $this->report['kept'] = [$lessonIds->count(), Topic::query()->whereIn('lesson_id', $lessonIds)->count()];
            $this->report['skipped'][] = 'program already seeded (course #' . $existing->getKey() . '); set DEMO_REFRESH=1 to rebuild it';
            $this->info('  course exists (#' . $existing->getKey() . '), program kept; metadata, products and events refreshed');
        } else {
            if ($existing) {
                $this->deleteCourse($existing);
            }
            $this->createCourse();
            $this->buildProgram();
            app(CourseRepositoryContract::class)->update(['status' => CourseStatusEnum::PUBLISHED], $this->course->getKey());
            $this->course->refresh();
            $this->info('  published course #' . $this->course->getKey());
        }

        $this->guard('certificate', fn () => $this->certificate());
        $this->guard('commerce', fn () => $this->commerce());
        $this->report['course_id'] = $this->course->getKey();

        return $this->report;
    }

    // ---------------------------------------------------------------- people

    private function ensurePeople(): void
    {
        $service = app(UserServiceContract::class);
        $tenantTutor = $this->tenantTutor();
        foreach ($this->people() as $handle => $person) {
            $user = User::query()->where('email', $person['email'])->first();
            if (!$user && $tenantTutor && $person['role'] === 'tutor') {
                // a tenant comes with tutor@<slug>.ulams.app: it becomes the lead tutor
                $user = $tenantTutor;
                $tenantTutor = null;
                if ($user->first_name !== $person['first_name'] || $user->last_name !== $person['last_name']) {
                    $service->update($user, new UserSaveDto($person['first_name'], $person['last_name'], true, ['tutor'], $user->email));
                }
                $this->report['extras']['tenant_tutor'] = $user->email . ' as ' . $person['first_name'] . ' ' . $person['last_name'];
            }
            if (!$user) {
                $user = $service->create(new UserSaveDto(
                    $person['first_name'],
                    $person['last_name'],
                    true,
                    [$person['role']],
                    $person['email'],
                    null,
                    'secret',
                    true
                ));
            }
            if (empty($user->path_avatar)) {
                $avatar = $this->assets->image('avatar-' . $handle . '.png', fn () => $this->avatar($person));
                $service->uploadAvatar($user, $this->assets->upload($avatar));
            }
            $this->users[$handle] = $user->fresh();
        }
    }

    /**
     * The tutor account a tenant is provisioned with (tutor@<slug>.ulams.app,
     * slug = first label of APP_URL's host).
     */
    private function tenantTutor(): ?User
    {
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $slug = explode('.', $host)[0] ?? '';
        if ($slug === '' || $slug === 'api') {
            return null;
        }
        $user = User::query()->where('email', 'tutor@' . $slug . '.ulams.app')->first();

        return $user && $user->hasRole('tutor') ? $user : null;
    }

    /** @param array<string, string> $person */
    private function avatar(array $person): Canvas
    {
        $palette = $this->palette();
        $c = new Canvas(256, 256, $person['color'] ?? $palette['accent']);
        $initials = mb_substr($person['first_name'], 0, 1) . mb_substr($person['last_name'], 0, 1);
        $c->circle(128, 128, 220, $palette['background'], 100);
        $c->centeredText($initials, 128, 82, 64, $palette['onAccent'] ?? '#FFFFFF', $palette['displayFont'] ?? 'sans', true);

        return $c;
    }

    /** @return array<int, int> */
    protected function tutorIds(): array
    {
        return array_values(array_map(
            fn (User $u) => $u->getKey(),
            array_filter($this->users, fn (User $u) => $u->hasRole('tutor'))
        ));
    }

    protected function user(string $handle): User
    {
        return $this->users[$handle];
    }

    /**
     * Visual palette used by generated media: background, text, accent,
     * secondary, muted, onAccent, displayFont.
     *
     * @return array<string, string>
     */
    abstract protected function palette(): array;

    // ---------------------------------------------------------------- course

    private function createCourse(): void
    {
        $media = $this->courseMedia();
        $fields = $this->courseFields();
        $input = array_merge($fields, [
            'status' => CourseStatusEnum::DRAFT,
            'findable' => true,
            'image' => $this->assets->upload($media['image']),
            'poster' => $this->assets->upload($media['poster']),
        ]);
        if (!empty($media['teaser'])) {
            $input['video'] = $this->assets->upload($media['teaser']);
        }
        $repository = app(CourseRepositoryContract::class);
        /** @var Course $course */
        $course = $repository->create($input);
        $repository->update([
            'categories' => $this->categoryIds(),
            'tags' => $this->tags(),
            'authors' => $this->tutorIds(),
        ], $course->getKey());
        $this->course = $course->fresh();
        $this->info('  created course #' . $this->course->getKey() . ' (draft)');
    }

    private function updateCourseMeta(): void
    {
        $fields = $this->courseFields();
        unset($fields['title']);
        app(CourseRepositoryContract::class)->update(array_merge($fields, [
            'categories' => $this->categoryIds(),
            'tags' => $this->tags(),
            'authors' => $this->tutorIds(),
        ]), $this->course->getKey());
        $this->course->refresh();
    }

    private function deleteCourse(Course $course): void
    {
        $this->info('  DEMO_REFRESH: removing course #' . $course->getKey() . ' and its H5P contents');
        $h5pIds = Topic::query()
            ->whereIn('lesson_id', $course->lessons()->pluck('id'))
            ->where('topicable_type', H5P::class)
            ->get()
            ->map(fn (Topic $t) => (int) optional($t->topicable)->value)
            ->filter();
        $repository = app(CourseRepositoryContract::class);
        $repository->update(['status' => CourseStatusEnum::ARCHIVED], $course->getKey());
        $repository->deleteModel($course->fresh());
        $builder = app(H5PPackageBuilder::class);
        foreach ($h5pIds as $id) {
            $builder->delete($id);
        }
    }

    /** @return array<int, int> */
    private function categoryIds(): array
    {
        $service = app(CategoryServiceContracts::class);
        $ids = [];
        foreach ($this->categories() as $definition) {
            $parentId = null;
            if ($definition['parent']) {
                $parent = Category::query()->where('name', $definition['parent'])->whereNull('parent_id')->first()
                    ?? $service->store(new CategoryDto(['name' => $definition['parent'], 'is_active' => true]));
                $parentId = $parent->getKey();
            }
            $query = Category::query()->where('name', $definition['name']);
            $parentId ? $query->where('parent_id', $parentId) : $query->whereNull('parent_id');
            $data = ['name' => $definition['name'], 'is_active' => true];
            if ($parentId) {
                $data['parent_id'] = $parentId;
            }
            $category = $query->first() ?? $service->store(new CategoryDto($data));
            $ids[] = $category->getKey();
        }

        return $ids;
    }

    // --------------------------------------------------------------- program

    private function buildProgram(): void
    {
        $lessons = app(LessonRepositoryContract::class);
        $lessonOrders = [];
        foreach (array_values($this->program()) as $lessonIndex => $lessonSpec) {
            /** @var Lesson $lesson */
            $lesson = $lessons->create([
                'title' => $lessonSpec['title'],
                'summary' => $lessonSpec['summary'] ?? null,
                'duration' => $lessonSpec['duration'] ?? null,
                'order' => $lessonIndex + 1,
                'course_id' => $this->course->getKey(),
                'active' => true,
            ]);
            $lessonOrders[] = [$lesson->getKey(), $lessonIndex + 1];
            $this->report['lessons']++;
            $this->info('  lesson ' . ($lessonIndex + 1) . ': ' . $lesson->title);

            $topicOrders = [];
            $order = 0;
            foreach ($lessonSpec['topics'] as $topicSpec) {
                $topic = $this->createTopic($lesson, $topicSpec, $order + 1);
                if ($topic) {
                    $order++;
                    $topicOrders[] = [$topic->getKey(), $order];
                }
            }
            app(CourseServiceContract::class)->sort('Topic', $topicOrders);
        }
        app(CourseServiceContract::class)->sort('Lesson', $lessonOrders);
    }

    /** @param array<string, mixed> $spec */
    private function createTopic(Lesson $lesson, array $spec, int $order): ?Topic
    {
        $type = $spec['type'];
        try {
            $made = isset($spec['make']) ? ($spec['make'])($this->course) : ['fields' => $spec['fields'] ?? [], 'files' => $spec['files'] ?? []];
        } catch (Throwable $e) {
            $made = null;
            $this->report['skipped'][] = sprintf('%s topic "%s": %s', $type, $spec['title'], $e->getMessage());
            $this->warn(sprintf('  ! skipped %s topic "%s": %s', $type, $spec['title'], $e->getMessage()));
        }
        if ($made === null) {
            return null;
        }
        $fields = array_merge(
            array_intersect_key($spec, array_flip(['title', 'preview', 'can_skip', 'duration', 'summary', 'introduction', 'description', 'json'])),
            $made['fields'] ?? [],
            ['order' => $order]
        );
        $topic = $this->topics->create($lesson, $type, $fields, $made['files'] ?? []);
        $this->report['topics'][$type] = ($this->report['topics'][$type] ?? 0) + 1;

        foreach ($spec['resources'] ?? [] as $resource) {
            [$path, $name] = is_callable($resource) ? $resource() : $resource;
            $this->topics->addResource($topic, $path, $name);
            $this->report['extras']['resources'] = ($this->report['extras']['resources'] ?? 0) + 1;
        }
        if ($type === 'gift') {
            foreach ($this->topics->addGiftQuestions($topic->fresh(), $spec['questions'] ?? []) as $questionType => $count) {
                $this->report['questions'][$questionType] = ($this->report['questions'][$questionType] ?? 0) + $count;
            }
        }
        $this->info(sprintf('    %-8s %s%s', $type, $topic->title, $topic->preview ? ' (free preview)' : ''));

        return $topic;
    }

    // ------------------------------------------------------ content helpers

    /**
     * Reads Demo/content/<key>/<name>.md. {{asset:file.png}} placeholders are
     * replaced with the public URL of a generated asset, uploaded next to the
     * course files.
     *
     * @param array<string, string> $assets file name => local path
     */
    protected function markdown(string $name, array $assets = []): string
    {
        $file = __DIR__ . '/content/' . $this->key() . '/' . $name . '.md';
        $text = (string) file_get_contents($file);
        foreach ($assets as $asset => $path) {
            $text = str_replace('{{asset:' . $asset . '}}', $this->publicUrl($path), $text);
        }
        if (preg_match('/\{\{asset:([^}]+)\}\}/', $text, $m)) {
            throw new RuntimeException("Unresolved asset {$m[1]} in $name.md");
        }

        return trim($text) . "\n";
    }

    protected function publicUrl(string $path): string
    {
        $stored = Storage::putFileAs('course/' . $this->course->getKey() . '/assets', new File($path), basename($path), 'public');

        return Storage::url((string) $stored);
    }

    protected function h5pAvailable(): bool
    {
        if ($this->h5pAvailable === null) {
            try {
                app(H5PServiceClientContract::class)->show(0);
                $this->h5pAvailable = true;
            } catch (H5PServiceException $e) {
                // an HTTP status (404 for content 0) means the service answered
                $this->h5pAvailable = $e->getStatus() !== null && $e->getStatus() < 500;
            } catch (Throwable $e) {
                $this->h5pAvailable = false;
            }
            if (!$this->h5pAvailable) {
                $this->warn('  ! H5P service unreachable at ' . config('h5p.service_url') . ': H5P topics are skipped');
            }
        }

        return $this->h5pAvailable;
    }

    /**
     * @param array<string, mixed>  $params
     * @param array<string, string> $media
     * @return array{fields: array<string, mixed>, files: array<string, string>}
     */
    protected function h5p(string $base, string $title, array $params, array $media = []): array
    {
        if (!$this->h5pAvailable()) {
            throw new RuntimeException('H5P service unreachable (' . config('h5p.service_url') . ')');
        }
        $builder = app(H5PPackageBuilder::class);
        $package = $builder->build($base, $title, $params, $media, $this->assets->path('h5p-' . md5($title) . '.h5p'));
        $id = $builder->upload($package);
        $this->report['extras']['h5p_contents'][] = $id;

        return ['fields' => ['value' => $id], 'files' => []];
    }

    /**
     * A LiaScript topic: the Markdown becomes a versioned LiaScript document (through the
     * LiaScript service, like the admin API), played on the tenant content origin.
     *
     * @return array{fields: array<string, mixed>, files: array<string, string>}
     */
    protected function liascript(string $title, string $markdown): array
    {
        $document = app(LiaScriptService::class)->create($title, $markdown, null, Auth::id());

        return ['fields' => ['value' => $document->getKey()], 'files' => []];
    }

    /** @return array{fields: array<string, mixed>, files: array<string, string>} */
    protected function scorm(string $zipName, string $template, array $replacements = []): array
    {
        $zip = $this->assets->zipTemplate($zipName, 'scorm/' . $template, $replacements, ['scorm12.js' => AssetFactory::assetsPath('scorm/scorm12.js')]);
        $result = app(ScormServiceContract::class)->uploadScormArchive($this->assets->upload($zip));
        $scorm = $result['model'];
        $sco = $scorm->scos()->whereNotNull('entry_url')->orderBy('id')->first() ?? $scorm->scos()->first();
        if (!$sco) {
            throw new RuntimeException('SCORM package has no SCO');
        }

        return ['fields' => ['value' => $sco->getKey()], 'files' => []];
    }

    /** @return array{fields: array<string, mixed>, files: array<string, string>} */
    protected function cmi5(string $zipName, string $template, array $replacements = []): array
    {
        $zip = $this->assets->zipTemplate($zipName, 'cmi5/' . $template, $replacements, ['cmi5-lite.js' => AssetFactory::assetsPath('cmi5/cmi5-lite.js')]);
        $cmi5 = app(Cmi5UploadServiceContract::class)->upload($this->assets->upload($zip));
        $au = $cmi5->aus()->orderBy('id')->first();
        if (!$au) {
            throw new RuntimeException('cmi5 package has no AU');
        }

        return ['fields' => ['value' => $au->getKey()], 'files' => []];
    }

    // --------------------------------------------------------------- commerce

    /** VAT of the demo products (Poland, standard rate). */
    public const TAX_RATE = 23;

    /**
     * Creates or updates a product by name through the cart ProductService.
     *
     * `price` and `price_old` are the gross amounts a buyer sees (the prices in
     * front/docs/design/experiences.md, e.g. 8900 = €89). The cart stores the net
     * price and adds `tax_rate` on top (Product::getGrossPrice(), the front's
     * formatPrice()), so they are converted with {@see netPrice()}.
     *
     * @param array<string, mixed> $data
     */
    protected function product(string $name, array $data): Product
    {
        $service = app(ProductServiceContract::class);
        $data = array_merge(['name' => $name, 'purchasable' => true, 'tax_rate' => self::TAX_RATE], $data);
        foreach (['price', 'price_old'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = self::netPrice((int) $data[$field], (float) $data['tax_rate']);
            }
        }
        $existing = Product::query()->where('name', $name)->first();
        $product = $existing ? $service->update($existing, $data) : $service->create($data);
        $this->report['extras']['products'][] = sprintf('%s (%s, %s EUR gross, %s net + %s %% VAT)', $name, $product->type, number_format($product->getGrossPrice() / 100, 2), number_format($product->price / 100, 2), $product->tax_rate);

        return $product;
    }

    /**
     * Net price (minor units) whose gross, as the cart computes it
     * (net + round(net * rate / 100)), is exactly $gross: 8900 at 23 % → 7236
     * (tax 1664). Falls back to the nearest net when no net hits $gross exactly.
     */
    public static function netPrice(int $gross, float $taxRate): int
    {
        if ($gross <= 0 || $taxRate <= 0) {
            return $gross;
        }
        $estimate = (int) round($gross / (1 + $taxRate / 100));
        foreach ([0, -1, 1, -2, 2] as $delta) {
            $net = $estimate + $delta;
            if ($net + (int) round($net * $taxRate / 100) === $gross) {
                return $net;
            }
        }

        return $estimate;
    }

    protected function courseProductable(int $quantity = 1): array
    {
        return ['id' => $this->course->getKey(), 'class' => ProductableCourse::class, 'quantity' => $quantity];
    }

    protected function grantAccess(Product $product, string $handle): void
    {
        $user = CoreUser::query()->findOrFail($this->user($handle)->getKey());
        $service = app(ProductServiceContract::class);
        if (!$service->productIsOwnedByUser($product, $user)) {
            $service->attachProductToUser($product, $user);
        }
        $this->report['extras']['enrolled'][] = $this->user($handle)->email . ' via "' . $product->name . '"';
    }

    /** @param array<string, mixed> $data */
    protected function webinar(string $name, array $data): Webinar
    {
        $existing = Webinar::query()->where('name', $name)->first();
        if ($existing) {
            $this->report['extras']['webinars'][] = $name . ' (exists)';

            return $existing;
        }
        $data = array_merge(['name' => $name, 'status' => 'published'], $data);
        if (isset($data['image'])) {
            $data['image'] = $this->assets->upload($data['image']);
        }
        $webinar = app(WebinarServiceContract::class)->store(new WebinarDto($data));
        $this->report['extras']['webinars'][] = $name;

        return $webinar;
    }

    /** @param array<string, mixed> $data */
    protected function stationaryEvent(string $name, array $data): StationaryEvent
    {
        $existing = StationaryEvent::query()->where('name', $name)->first();
        if ($existing) {
            $this->report['extras']['stationary_events'][] = $name . ' (exists)';

            return $existing;
        }
        $data = array_merge(['name' => $name, 'status' => 'published'], $data);
        if (isset($data['image'])) {
            $data['image'] = $this->assets->upload($data['image']);
        }
        $event = app(StationaryEventServiceContract::class)->create($data);
        $this->report['extras']['stationary_events'][] = $name;

        return $event;
    }

    /** @param array<string, mixed> $data */
    protected function consultation(string $name, array $data): Consultation
    {
        $existing = Consultation::query()->where('name', $name)->first();
        if ($existing) {
            $this->report['extras']['consultations'][] = $name . ' (exists)';

            return $existing;
        }
        $data = array_merge(['name' => $name, 'status' => 'published'], $data);
        if (isset($data['image'])) {
            $data['image'] = $this->assets->upload($data['image']);
        }
        $consultation = app(ConsultationServiceContract::class)->store(new ConsultationDto($data));
        $this->report['extras']['consultations'][] = $name;

        return $consultation;
    }

    /** @param array<string, mixed> $data */
    protected function coupon(string $code, array $data): Coupon
    {
        $existing = Coupon::query()->where('code', $code)->first();
        if ($existing) {
            $this->report['extras']['vouchers'][] = $code . ' (exists)';

            return $existing;
        }
        $coupon = app(CouponServiceContract::class)->createCoupon(array_merge(['code' => $code, 'active' => true], $data));
        $this->report['extras']['vouchers'][] = $code;

        return $coupon;
    }

    private function certificate(): void
    {
        $service = app(TemplateServiceContract::class);
        $name = $this->certificateName();
        // The title section must reference the course title variable to be valid.
        $sections = [
            ['key' => 'title', 'content' => UserFinishedCourseVariables::defaultSectionsContent()['title']],
            ['key' => 'content', 'content' => CertificateTemplates::content($this->key())],
        ];
        $template = Template::query()->where('name', $name)->first();
        if ($template) {
            // refresh the layout so re-runs pick up the themed pdfme template
            $template = $service->update($template->getKey(), ['sections' => $sections]);
        } else {
            $template = $service->insert([
                'name' => $name,
                'event' => CourseFinished::class,
                'channel' => PdfChannel::class,
                'default' => false,
                'sections' => $sections,
            ]);
        }
        $service->assignTemplateToModel($template, $this->course->getKey());
        $this->report['extras']['certificate'] = $name . ' (template #' . $template->getKey() . ')';
    }

    // ------------------------------------------------------------------ utils

    protected function date(string $value): Carbon
    {
        return Carbon::parse($value);
    }

    protected function guard(string $what, callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            $this->report['skipped'][] = "$what: " . $e->getMessage();
            $this->warn("  ! $what failed: " . $e->getMessage());
        }
    }

    protected function info(string $message): void
    {
        $this->command ? $this->command->getOutput()->writeln($message) : null;
    }

    protected function warn(string $message): void
    {
        $this->command ? $this->command->getOutput()->writeln('<comment>' . $message . '</comment>') : null;
    }

    protected function uploaded(string $path): UploadedFile
    {
        return $this->assets->upload($path);
    }
}
