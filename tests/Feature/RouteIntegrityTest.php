<?php

namespace Tests\Feature;

use ReflectionMethod;
use Tests\TestCase;

class RouteIntegrityTest extends TestCase
{
    public function test_all_controller_routes_reference_existing_actions(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            $action = $route->getActionName();

            if (! str_contains($action, '@')) {
                continue;
            }

            [$controller, $method] = explode('@', $action, 2);

            $this->assertTrue(
                method_exists($controller, $method),
                "Route [{$route->uri()}] references missing action [{$action}].",
            );

            $this->assertTrue(
                (new ReflectionMethod($controller, $method))->isPublic(),
                "Route [{$route->uri()}] references non-public action [{$action}].",
            );
        }
    }
}
