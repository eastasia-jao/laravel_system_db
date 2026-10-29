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

    public function test_collapsed_sidebar_uses_small_dots_for_all_notification_indicators(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $sidebar = file_get_contents(resource_path('views/layouts/sidebar.blade.php'));

        $this->assertStringContainsString(
            '#appSidebar:not(:hover):not(:focus-within) .sidebar-notification-indicator:not(.d-none)',
            $css
        );
        $this->assertStringContainsString('width: 8px !important;', $css);
        $this->assertStringContainsString('font-size: 0 !important;', $css);
        $this->assertSame(4, substr_count($sidebar, 'sidebar-notification-indicator'));
        $this->assertStringContainsString(
            'sidebar-notification-indicator badge rounded-pill bg-danger ms-auto',
            $sidebar
        );
    }
}
