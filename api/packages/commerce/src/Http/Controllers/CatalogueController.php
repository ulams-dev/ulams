<?php

namespace Ulams\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Ulams\Commerce\CommerceManager;
use Ulams\Commerce\Enums\CommercePermissionsEnum;
use Ulams\Commerce\Jobs\SyncSellableJob;
use Ulams\Commerce\Models\AccessPass;
use Ulams\Commerce\Models\Bundle;
use Ulams\Commerce\Models\BundleCourse;
use Ulams\Commerce\Models\ProductLink;
use Ulams\Commerce\Models\SellablePrice;
use Ulams\Commerce\Services\CatalogueSync;
use Ulams\Commerce\Services\SellableCatalogue;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\SellableRef;
use Ulams\Courses\Models\Course;

/**
 * What this tenant sells and for how much (ADR 0130): prices of courses, bundles and access passes, and the state of
 * their products at the commerce provider. Needs the permission `commerce_prices_manage`. A price change is recorded
 * here and pushed to the provider by a queued job, so a provider outage never loses it; the list shows whether the
 * product is in step (`synced_at`, `sync_error`).
 *
 * @OA\Get(path="/api/admin/commerce/products", operationId="admin.commerce.products.list", summary="Prices and product state of everything this tenant sells", tags={"Commerce"}, security={{"passport": {}}},
 *     description="One row per sellable that has a price: its type (course, bundle or access_pass), name, price in minor units, whether it is for sale, whether it is published, and the product at the commerce provider (id, variant, last sync, last sync error). Needs the permission commerce_prices_manage.",
 *     @OA\Response(response=200, description="List of sellables with a price", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"))
 *
 * @OA\Put(path="/api/admin/commerce/products/{type}/{id}", operationId="admin.commerce.products.by-type.by-id.put", summary="Set the price of a sellable and put it on or off sale", tags={"Commerce"}, security={{"passport": {}}},
 *     description="Records the price (minor units and an ISO currency) and whether the sellable is for sale, then queues the sync to the commerce provider. The product is enabled at the provider only while it is for sale and published. Needs commerce_prices_manage.",
 *     @OA\Parameter(name="type", in="path", required=true, @OA\Schema(type="string", enum={"course","bundle","access_pass"})),
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"price_minor","currency"}, @OA\Property(property="price_minor", type="integer", minimum=1), @OA\Property(property="currency", type="string", example="EUR"), @OA\Property(property="for_sale", type="boolean"))),
 *     @OA\Response(response=200, description="The sellable with its price", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=404, description="No such sellable"), @OA\Response(response=422, description="Invalid price"))
 *
 * @OA\Post(path="/api/admin/commerce/bundles", operationId="admin.commerce.bundles.post", summary="Create a bundle of courses", tags={"Commerce"}, security={{"passport": {}}},
 *     description="A bundle is bought once and grants every course in it. It has no price until one is set with PUT /api/admin/commerce/products/bundle/{id}. Needs commerce_prices_manage.",
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"name","course_ids"}, @OA\Property(property="name", type="string"), @OA\Property(property="description", type="string"), @OA\Property(property="course_ids", type="array", @OA\Items(type="integer")))),
 *     @OA\Response(response=201, description="The bundle", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=422, description="Invalid bundle"))
 *
 * @OA\Post(path="/api/admin/commerce/access-passes", operationId="admin.commerce.access-passes.post", summary="Create an access pass", tags={"Commerce"}, security={{"passport": {}}},
 *     description="An access pass sells a course or a bundle for a number of days; the learner's access ends that many days after the purchase. It has no price until one is set with PUT /api/admin/commerce/products/access_pass/{id}. Needs commerce_prices_manage.",
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"name","subject_type","subject_id","days"}, @OA\Property(property="name", type="string"), @OA\Property(property="subject_type", type="string", enum={"course","bundle"}), @OA\Property(property="subject_id", type="integer"), @OA\Property(property="days", type="integer", minimum=1, maximum=3650))),
 *     @OA\Response(response=201, description="The access pass", @OA\JsonContent(ref="#/components/schemas/ApiEnvelope")), @OA\Response(response=403, description="Forbidden"), @OA\Response(response=422, description="Invalid pass"))
 */
class CatalogueController extends Controller
{
    public function __construct(private readonly CatalogueSync $sync, private readonly SellableCatalogue $catalogue, private readonly CommerceManager $commerce)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $provider = $this->commerce->provider()->key();
        $links = ProductLink::query()->where('provider', $provider)->get()->keyBy(fn (ProductLink $l) => $l->sellable_type . ':' . $l->sellable_id);
        $rows = SellablePrice::query()->orderBy('id')->get()->map(function (SellablePrice $p) use ($links, $provider) {
            $info = $this->describe($p->ref());
            $link = $links->get($p->ref()->key());

            return [
                'type' => $p->sellable_type, 'id' => $p->sellable_id, 'name' => $info?->name, 'price_minor' => $p->amount_minor, 'currency' => $p->currency,
                'for_sale' => $p->for_sale, 'published' => $info?->published ?? false,
                'product' => $link === null ? null : [
                    'provider' => $provider, 'external_id' => $link->external_id, 'variant_code' => $link->variant_code, 'enabled' => $link->active,
                    'synced_at' => $link->synced_at?->toIso8601String(), 'sync_error' => $link->sync_error,
                ],
            ];
        });

        return $this->ok($rows->all());
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $this->authorizeManage($request);
        abort_unless(in_array($type, SellableRef::TYPES, true) && $id > 0, 404);
        $data = $request->validate([
            'price_minor' => ['required', 'integer', 'min:1', 'max:100000000'],
            'currency' => ['required', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'for_sale' => ['sometimes', 'boolean'],
        ]);
        $ref = new SellableRef($type, $id);
        try {
            $price = $this->sync->setPrice($ref, new Price((int) $data['price_minor'], $data['currency']), (bool) ($data['for_sale'] ?? true));
        } catch (InvalidArgumentException) {
            abort(404);
        }
        SyncSellableJob::dispatch($type, $id)->afterCommit();

        return $this->ok(['type' => $type, 'id' => $id, 'price_minor' => $price->amount_minor, 'currency' => $price->currency, 'for_sale' => $price->for_sale]);
    }

    public function createBundle(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'course_ids' => ['required', 'array', 'min:1', 'max:100'],
            'course_ids.*' => ['integer', 'distinct', Rule::exists((new Course())->getTable(), 'id')],
        ]);
        $bundle = DB::transaction(function () use ($data) {
            $bundle = Bundle::query()->create(['name' => $data['name'], 'description' => $data['description'] ?? null]);
            foreach ($data['course_ids'] as $courseId) {
                BundleCourse::query()->create(['bundle_id' => $bundle->getKey(), 'course_id' => (int) $courseId]);
            }

            return $bundle;
        });

        return $this->ok(['id' => $bundle->getKey(), 'name' => $bundle->name, 'course_ids' => $bundle->courseIds()], 201);
    }

    public function createPass(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'subject_type' => ['required', Rule::in([SellableRef::COURSE, SellableRef::BUNDLE])],
            'subject_id' => ['required', 'integer', 'min:1'],
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);
        abort_unless($this->catalogue->exists(new SellableRef($data['subject_type'], (int) $data['subject_id'])), 422, 'The course or bundle does not exist.');
        $pass = AccessPass::query()->create($data);

        return $this->ok(['id' => $pass->getKey(), 'name' => $pass->name, 'subject_type' => $pass->subject_type, 'subject_id' => $pass->subject_id, 'days' => $pass->days], 201);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->can(CommercePermissionsEnum::PRICES_MANAGE, 'api'), 403);
    }

    private function describe(SellableRef $ref): ?\Ulams\Commerce\Support\SellableInfo
    {
        try {
            return $this->catalogue->describe($ref);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => 'OK'], $status);
    }
}
