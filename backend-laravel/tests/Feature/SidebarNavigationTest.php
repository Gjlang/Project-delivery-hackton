<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $company = Company::create(['name' => 'Test Co']);

        return User::factory()->create(['company_id' => $company->id]);
    }

    public function test_dashboard_shows_company_and_projects_sections_without_current_project_section(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Project Planner');
        $response->assertSee('New Project');
        $response->assertDontSee('Current Project');
    }

    public function test_project_scoped_page_shows_your_projects_section_with_project_id_in_links(): void
    {
        $user = $this->makeUser();
        $project = Project::create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'name' => 'HR Portal',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)->get(route('projects.company-knowledge.index', $project));

        $response->assertOk();
        $response->assertSee('Your Projects');
        $response->assertSee('HR Portal');
        $response->assertSee(route('projects.phases.index', $project), false);
        $response->assertSee(route('projects.testing.create', $project), false);
    }
}
