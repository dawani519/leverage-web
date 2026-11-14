<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaTest extends TestCase
{
    private function manifest(): array
    {
        $json = file_get_contents(public_path('manifest.webmanifest'));
        $manifest = json_decode($json, true);
        $this->assertIsArray($manifest, 'manifest.webmanifest must be valid JSON');

        return $manifest;
    }

    public function test_the_manifest_has_everything_browsers_need_to_offer_installation(): void
    {
        $m = $this->manifest();

        foreach (['name', 'short_name', 'start_url', 'scope', 'display', 'background_color', 'theme_color', 'icons'] as $key) {
            $this->assertArrayHasKey($key, $m, "manifest is missing {$key}");
        }
        $this->assertSame('standalone', $m['display']);
        $this->assertStringStartsWith('/', $m['start_url']);

        $sizes = collect($m['icons'])->pluck('sizes')->all();
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->assertContains('maskable', collect($m['icons'])->pluck('purpose')->all(), 'a maskable icon is needed for Android');
    }

    public function test_every_icon_in_the_manifest_exists_and_is_a_real_png_of_the_declared_size(): void
    {
        foreach ($this->manifest()['icons'] as $icon) {
            $path = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($path, "{$icon['src']} is listed but missing");

            $info = getimagesize($path);
            $this->assertNotFalse($info, "{$icon['src']} is not a readable image");
            $this->assertSame(IMAGETYPE_PNG, $info[2], "{$icon['src']} is not a PNG");
            $this->assertSame($icon['sizes'], "{$info[0]}x{$info[1]}", "{$icon['src']} is not the size it claims");
        }
    }

    public function test_manifest_shortcuts_point_at_real_routes(): void
    {
        foreach ($this->manifest()['shortcuts'] as $shortcut) {
            $path = parse_url($shortcut['url'], PHP_URL_PATH);
            $response = $this->get($path);

            // Protected pages redirect guests to login; what must never happen is a 404 or an error.
            $this->assertContains($response->getStatusCode(), [200, 302], "{$shortcut['url']} returned {$response->getStatusCode()}");
        }
    }

    public function test_the_offline_page_is_self_contained(): void
    {
        $html = file_get_contents(public_path('offline.html'));

        $this->assertStringContainsString('offline', strtolower($html));
        $this->assertDoesNotMatchRegularExpression('/<link[^>]+stylesheet/i', $html, 'offline page must not depend on a stylesheet that may be uncached');
        $this->assertDoesNotMatchRegularExpression('/<script[^>]+src=/i', $html, 'offline page must not depend on an external script');
    }

    public function test_the_service_worker_precaches_the_offline_page_and_never_caches_pages(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("'/offline.html'", $sw);
        $this->assertStringContainsString("request.method !== 'GET'", $sw, 'must ignore non-GET (form posts, Livewire updates)');
        $this->assertStringContainsString("request.mode === 'navigate'", $sw);
        $this->assertStringContainsString("url.origin !== self.location.origin", $sw, 'must not touch cross-origin requests such as Paystack');

        // Page navigations must go to the network, never from a cache.
        $this->assertMatchesRegularExpression("/navigate'\)\s*\{\s*event\.respondWith\(fetch\(request\)\.catch/s", $sw);
        // Only these two path prefixes are ever cached.
        $this->assertSame(['/build/', '/storage/'], collect(['/build/', '/storage/', '/wallet', '/transactions', '/admin', '/livewire'])
            ->filter(fn ($p) => str_contains($sw, "startsWith('{$p}')"))->values()->all());
    }

    public function test_pages_link_the_manifest_and_register_the_service_worker(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('rel="manifest" href="/manifest.webmanifest"', $html);
        $this->assertStringContainsString('name="theme-color"', $html);
        $this->assertStringContainsString("register('/sw.js'", $html);
    }

    public function test_hardening_headers_are_sent_on_every_response(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('geolocation=()', $response->headers->get('Permissions-Policy'));
    }
}
