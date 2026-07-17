<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * '/' is a marketing landing page (see routes/web.php) that links to
     * /admin/login — the real UI surface is the Filament panel at /admin
     * (see CLAUDE.md).
     */
    public function test_the_root_url_shows_the_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Sign In');
    }
}
