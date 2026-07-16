<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The only real UI surface is the Filament panel at /admin (see
     * CLAUDE.md) — the bare root just hands off to it.
     */
    public function test_the_root_url_redirects_to_the_admin_panel(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/admin');
    }
}
