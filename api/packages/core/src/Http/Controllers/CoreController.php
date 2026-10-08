<?php

namespace Ulams\Core\Http\Controllers;

use Composer\InstalledVersions;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Illuminate\Support\Str;

/**
 * @OA\Info(title="Ulams", version="0.0.1")
 *
 * @OA\SecurityScheme(
 *      securityScheme="passport",
 *      in="header",
 *      name="bearerAuth",
 *      type="http",
 *      scheme="bearer",
 *      bearerFormat="JWT",
 * )
 */
class CoreController extends UlamsBaseController
{

    /**
     * @OA\Get(
     *      path="/api/core/packages",
     *      summary="List installed ulams packages",
     *      tags={"Core Admin"},
     *      security={
     *          {"passport": {}},
     *      },
     *      description="List of installed ulams packages with versions",
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\MediaType(
     *              mediaType="application/json"
     *          ),
     *          @OA\Schema(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(@OA\Schema(type="string"))
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function packages()
    {
        $ulamsPackagesWithVersions = array_reduce(
            array_filter(InstalledVersions::getInstalledPackages(), fn (string $package) => Str::startsWith($package, 'ulams/')),
            fn (array $accumulator, string $package) => array_merge($accumulator, [$package => InstalledVersions::getPrettyVersion($package)]),
            []
        );

        // Ulams modules vendored as source into api/packages/* are not composer packages any more;
        // their imported versions are listed in packages/versions.json.
        $vendoredManifest = dirname(__DIR__, 4) . '/versions.json';
        if (is_file($vendoredManifest)) {
            $vendored = json_decode((string) file_get_contents($vendoredManifest), true);
            if (is_array($vendored)) {
                $ulamsPackagesWithVersions = array_merge($vendored, $ulamsPackagesWithVersions);
            }
        }

        return $this->sendResponse($ulamsPackagesWithVersions);
    }
}
