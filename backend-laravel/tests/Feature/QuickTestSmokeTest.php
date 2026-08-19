<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickTestSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_page_renders_with_quick_test_box(): void
    {
        $company = Company::create(['name' => 'Test Co']);
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->get('/testing');

        $response->assertOk();
        $response->assertSee('Quick Test');
        $response->assertSee(route('testing.quick'), false);
    }
}
