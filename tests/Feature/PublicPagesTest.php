<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_home_page_loads_with_correct_scope(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Barbar');
        $response->assertSee('Appointment Management');
        $response->assertSee('Flexible Service Scheduling');
        $response->assertSee('WhatsApp Booking Ready');
        $response->assertSee('399');
        $response->assertSee('TRY');
        $response->assertSee('First Month Free');

        // Verify out-of-scope concepts are NOT present
        $response->assertDontSee('Queue management');
        $response->assertDontSee('Point of Sale');
    }

    public function test_login_page_renders_successfully(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Log in to your account');
    }

    public function test_register_page_renders_successfully(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('Register Your Barber Salon');
        $response->assertSee('Manager Full Name');
        $response->assertSee('Salon / Company Name');
    }

    public function test_admin_login_page_renders_successfully(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertSee('System Admin Portal');
    }
}
