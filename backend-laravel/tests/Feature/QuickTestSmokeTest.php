<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Models\WebsiteTestResult;
use App\Models\WebsiteTestRun;
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

    public function test_results_page_renders_steps_and_reason(): void
    {
        $company = Company::create(['name' => 'Test Co']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $project = Project::create(['company_id' => $company->id, 'created_by' => $user->id, 'name' => 'P', 'status' => 'draft']);
        $run = WebsiteTestRun::create(['company_id' => $company->id, 'project_id' => $project->id, 'website_url' => 'https://example.com', 'status' => 'completed']);
        WebsiteTestResult::create([
            'website_test_run_id' => $run->id,
            'rule_code' => 'SC-001',
            'category' => 'SC',
            'applicability_status' => 'applicable',
            'status' => 'PASS',
            'reason' => 'The website satisfied SC-001 -- served over HTTPS.',
            'steps' => ['Loaded https://example.com in chromium.', 'Checked against SC-001.'],
        ]);

        $response = $this->actingAs($user)->get(route('projects.testing.show', [$project, $run]));

        $response->assertOk();
        $response->assertSee('Why this result');
        $response->assertSee('Test scenario / steps performed');
        $response->assertSee('served over HTTPS');
    }
}
