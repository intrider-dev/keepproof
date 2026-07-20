<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

final class OpenApiContractTest extends TestCase
{
    public function test_openapi_document_is_served_locally_and_covers_every_versioned_route(): void
    {
        $response = $this->get('/docs/openapi.yaml');
        $response->assertOk()->assertHeader('content-type', 'application/yaml; charset=utf-8');
        $specification = file_get_contents(base_path('openapi/openapi.yaml'));
        self::assertIsString($specification);
        self::assertStringContainsString("openapi: 3.1.0\n", $specification);

        $documentedPaths = [];
        $matches = [];
        preg_match_all('/^  (?<path>\/[^:\r\n]+):$/m', $specification, $matches);
        foreach ($matches['path'] as $path) {
            $documentedPaths[$path] = true;
        }

        $missing = [];
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }

            $path = '/'.$route->uri();
            if (! isset($documentedPaths[$path])) {
                $missing[] = $path;
            }
        }

        self::assertSame([], array_values(array_unique($missing)), 'Every /api/v1 path must appear in OpenAPI.');
    }
}
