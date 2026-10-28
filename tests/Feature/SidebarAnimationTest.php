<?php

namespace Tests\Feature;

use Tests\TestCase;

class SidebarAnimationTest extends TestCase
{
    public function test_store_hub_dropdown_keeps_bootstrap_collapse_animation_available(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString(
            '#appSidebar #storeHubDropdown.collapsing { display: block !important;',
            $css
        );
        $this->assertStringContainsString('transition: height 220ms ease;', $css);
        $this->assertStringContainsString(
            '#appSidebar:not(:hover):not(:focus-within) #storeHubDropdown',
            $css
        );
        $this->assertStringNotContainsString(
            '#storeHubDropdown.show { display: block !important; height: auto !important;',
            $css
        );
    }
}
