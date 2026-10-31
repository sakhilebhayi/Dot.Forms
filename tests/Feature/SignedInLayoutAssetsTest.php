<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * The signed-in pages that use the legacy layout (layouts/app.blade.php) must
 * load the Vite-built stylesheet and script, not the Tailwind Play CDN and a
 * second, CDN copy of Alpine (Livewire already ships its own: a second one made
 * every Alpine component on these pages fail). Tests run with Vite faked out,
 * so the dev-server "hot" file stands in for a build.
 */
class SignedInLayoutAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_loads_the_built_assets_and_no_cdn_tailwind_or_alpine(): void
    {
        $hot = tempnam(sys_get_temp_dir(), 'vite-hot');
        file_put_contents($hot, 'http://localhost:5173');

        try {
            $this->withVite();
            Vite::useHotFile($hot);

            $html = $this->actingAs(User::factory()->withPersonalTeam()->create())
                ->get(route('dashboard'))
                ->assertOk()
                ->getContent();
        } finally {
            unlink($hot);
        }

        $this->assertStringContainsString('resources/css/signed-in.css', $html);
        $this->assertStringContainsString('resources/js/app.js', $html);
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $html);
        $this->assertStringNotContainsString('unpkg.com', $html);
        $this->assertStringNotContainsString('alpinejs', $html);
        $this->assertStringNotContainsString('tailwind.config', $html);
    }

    public function test_the_legacy_layout_source_has_no_cdn_tailwind_or_alpine(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString("@vite(['resources/css/signed-in.css', 'resources/css/carbon.css', 'resources/js/app.js'])", $layout);
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $layout);
        $this->assertStringNotContainsString('unpkg.com', $layout);
        $this->assertStringNotContainsString('alpinejs', $layout);
    }
}
