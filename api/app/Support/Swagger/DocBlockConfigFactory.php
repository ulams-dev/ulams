<?php

namespace App\Support\Swagger;

use L5Swagger\ConfigFactory;
use OpenApi\Analysers\AttributeAnnotationFactory;
use OpenApi\Analysers\DocBlockAnnotationFactory;
use OpenApi\Analysers\ReflectionAnalyser;

/**
 * swagger-php 6 (l5-swagger 11) reads only PHP attributes by default. The API is documented
 * with `@OA\` docblock annotations, which need the DocBlock factory (and doctrine/annotations).
 *
 * The analyser is an object, so it cannot live in config (`config:cache` serialises config):
 * it is added here, only when a documentation config is built, i.e. when docs are generated.
 */
class DocBlockConfigFactory extends ConfigFactory
{
    public function documentationConfig(?string $documentation = null): array
    {
        $config = parent::documentationConfig($documentation);
        if (empty($config['scanOptions']['analyser'])) {
            $config['scanOptions']['analyser'] = new ReflectionAnalyser([
                new DocBlockAnnotationFactory(),
                new AttributeAnnotationFactory(),
            ]);
        }

        return $config;
    }
}
