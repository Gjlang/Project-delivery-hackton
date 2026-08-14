<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyRule;
use App\Models\KnowledgeDocument;
use App\Models\RuleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyRuleTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyUser(?Company $company = null): User
    {
        $company ??= Company::create(['name' => 'Test Co']);

        return User::factory()->create([
            'company_id' => $company->id,
        ]);
    }

    private function makeCategory(string $code = 'BR'): RuleCategory
    {
        return RuleCategory::create([
            'code' => $code,
            'name' => $code.' Category',
            'is_active' => true,
        ]);
    }

    public function test_rule_creation(): void
    {
        $user = $this->makeCompanyUser();
        $category = $this->makeCategory();

        $response = $this->actingAs($user)->postJson('/company-rules', [
            'rule_category_id' => $category->id,
            'rule_code' => 'BR-999',
            'title' => 'Test Rule',
            'rule_text' => 'A test rule requires something.',
            'evaluation_type' => 'boolean',
            'is_mandatory' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('rule_code', 'BR-999');

        $this->assertDatabaseHas('company_rules', [
            'company_id' => $user->company_id,
            'rule_code' => 'BR-999',
            'title' => 'Test Rule',
        ]);
    }

    public function test_rule_creation_with_parameters_and_conditions(): void
    {
        $user = $this->makeCompanyUser();
        $category = $this->makeCategory('EW');

        $response = $this->actingAs($user)->postJson('/company-rules', [
            'rule_category_id' => $category->id,
            'rule_code' => 'EW-999',
            'title' => 'Concurrent Limit',
            'rule_text' => 'Maximum concurrent projects = 3.',
            'evaluation_type' => 'threshold',
            'parameters' => [
                ['parameter_key' => 'maximum_concurrent_projects', 'parameter_value' => '3', 'value_type' => 'integer', 'unit' => 'projects'],
            ],
            'conditions' => [
                ['field' => 'role', 'operator' => 'in', 'value' => ['Backend Developer']],
            ],
        ]);

        $response->assertStatus(201);

        $rule = CompanyRule::where('rule_code', 'EW-999')->first();
        $this->assertCount(1, $rule->parameters);
        $this->assertEquals('3', $rule->parameters->first()->parameter_value);
        $this->assertCount(1, $rule->conditions);
    }

    public function test_rule_update(): void
    {
        $user = $this->makeCompanyUser();
        $category = $this->makeCategory();

        $rule = CompanyRule::create([
            'company_id' => $user->company_id,
            'rule_category_id' => $category->id,
            'rule_code' => 'BR-998',
            'title' => 'Original Title',
            'rule_text' => 'Original text.',
            'version' => '1.0',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->putJson("/company-rules/{$rule->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('title', 'Updated Title');

        $this->assertDatabaseHas('company_rules', [
            'id' => $rule->id,
            'title' => 'Updated Title',
            'updated_by' => $user->id,
        ]);
    }

    public function test_rule_category_relationship(): void
    {
        $user = $this->makeCompanyUser();
        $category = $this->makeCategory('SC');

        $rule = CompanyRule::create([
            'company_id' => $user->company_id,
            'rule_category_id' => $category->id,
            'rule_code' => 'SC-998',
            'title' => 'Relationship Test',
            'rule_text' => 'Text.',
        ]);

        $this->assertTrue($rule->category->is($category));
        $this->assertTrue($category->companyRules->contains($rule));
    }

    public function test_rule_source_document_relationship(): void
    {
        $user = $this->makeCompanyUser();
        $company = Company::find($user->company_id);
        $category = $this->makeCategory();

        $document = KnowledgeDocument::create([
            'company_id' => $company->id,
            'title' => 'Source Doc',
            'document_type' => 'mixed_rules',
            'status' => 'processed',
        ]);

        $rule = CompanyRule::create([
            'company_id' => $company->id,
            'rule_category_id' => $category->id,
            'source_document_id' => $document->id,
            'rule_code' => 'BR-997',
            'title' => 'Traceable Rule',
            'rule_text' => 'Text.',
        ]);

        $this->assertTrue($rule->sourceDocument->is($document));
        $this->assertTrue($document->companyRules->contains($rule));
    }

    public function test_rule_version_handling(): void
    {
        $user = $this->makeCompanyUser();
        $category = $this->makeCategory();

        $v1 = CompanyRule::create([
            'company_id' => $user->company_id,
            'rule_category_id' => $category->id,
            'rule_code' => 'BR-996',
            'title' => 'Versioned Rule',
            'rule_text' => 'Version 1 text.',
            'version' => '1.0',
            'status' => 'superseded',
            'is_active' => false,
        ]);

        $v2 = CompanyRule::create([
            'company_id' => $user->company_id,
            'rule_category_id' => $category->id,
            'rule_code' => 'BR-996',
            'title' => 'Versioned Rule',
            'rule_text' => 'Version 2 text.',
            'version' => '2.0',
            'status' => 'active',
            'is_active' => true,
            'supersedes_rule_id' => $v1->id,
        ]);

        $this->assertEquals(2, CompanyRule::where('rule_code', 'BR-996')->count());
        $this->assertTrue($v2->supersedesRule->is($v1));
        $this->assertTrue($v1->supersededBy->is($v2));
        $this->assertDatabaseHas('company_rules', ['id' => $v1->id, 'status' => 'superseded']);
    }

    public function test_duplicate_rule_code_and_version_is_rejected(): void
    {
        $user = $this->makeCompanyUser();
        $category = $this->makeCategory();

        CompanyRule::create([
            'company_id' => $user->company_id,
            'rule_category_id' => $category->id,
            'rule_code' => 'BR-995',
            'title' => 'First',
            'rule_text' => 'Text.',
            'version' => '1.0',
        ]);

        $response = $this->actingAs($user)->postJson('/company-rules', [
            'rule_category_id' => $category->id,
            'rule_code' => 'BR-995',
            'title' => 'Duplicate',
            'rule_text' => 'Text.',
            'version' => '1.0',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('rule_code');
        $this->assertEquals(1, CompanyRule::where('rule_code', 'BR-995')->count());
    }

    public function test_duplicate_rule_code_allowed_across_different_versions(): void
    {
        $user = $this->makeCompanyUser();
        $category = $this->makeCategory();

        CompanyRule::create([
            'company_id' => $user->company_id,
            'rule_category_id' => $category->id,
            'rule_code' => 'BR-994',
            'title' => 'First',
            'rule_text' => 'Text.',
            'version' => '1.0',
        ]);

        $response = $this->actingAs($user)->postJson('/company-rules', [
            'rule_category_id' => $category->id,
            'rule_code' => 'BR-994',
            'title' => 'Second Version',
            'rule_text' => 'Text.',
            'version' => '1.1',
        ]);

        $response->assertStatus(201);
        $this->assertEquals(2, CompanyRule::where('rule_code', 'BR-994')->count());
    }

    public function test_status_change(): void
    {
        $user = $this->makeCompanyUser();
        $category = $this->makeCategory();

        $rule = CompanyRule::create([
            'company_id' => $user->company_id,
            'rule_category_id' => $category->id,
            'rule_code' => 'BR-993',
            'title' => 'Status Test',
            'rule_text' => 'Text.',
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->patchJson("/company-rules/{$rule->id}/status", [
            'status' => 'inactive',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'inactive');
        $response->assertJsonPath('is_active', false);
    }

    public function test_destroy_archives_instead_of_hard_deleting(): void
    {
        $user = $this->makeCompanyUser();
        $category = $this->makeCategory();

        $rule = CompanyRule::create([
            'company_id' => $user->company_id,
            'rule_category_id' => $category->id,
            'rule_code' => 'BR-992',
            'title' => 'Archive Test',
            'rule_text' => 'Text.',
        ]);

        $response = $this->actingAs($user)->deleteJson("/company-rules/{$rule->id}");

        $response->assertStatus(200);

        // Soft-deleted: gone from default queries, but the historical row remains.
        $this->assertNull(CompanyRule::find($rule->id));
        $this->assertNotNull(CompanyRule::withTrashed()->find($rule->id));
        $this->assertDatabaseHas('company_rules', ['id' => $rule->id, 'status' => 'archived']);
    }

    public function test_company_scoping_blocks_cross_company_access(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $userA = $this->makeCompanyUser($companyA);
        $userB = $this->makeCompanyUser($companyB);

        $category = $this->makeCategory();

        $ruleB = CompanyRule::create([
            'company_id' => $companyB->id,
            'rule_category_id' => $category->id,
            'rule_code' => 'BR-991',
            'title' => 'Company B Rule',
            'rule_text' => 'Text.',
        ]);

        $this->actingAs($userA)->getJson("/company-rules/{$ruleB->id}")->assertStatus(404);
        $this->actingAs($userA)->putJson("/company-rules/{$ruleB->id}", ['title' => 'Hacked'])->assertStatus(404);
        $this->actingAs($userA)->deleteJson("/company-rules/{$ruleB->id}")->assertStatus(404);

        // Owning company can still access its own rule.
        $this->actingAs($userB)->getJson("/company-rules/{$ruleB->id}")->assertStatus(200);
    }

    public function test_index_only_returns_rules_for_the_authenticated_users_company(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $userA = $this->makeCompanyUser($companyA);
        $category = $this->makeCategory();

        CompanyRule::create(['company_id' => $companyA->id, 'rule_category_id' => $category->id, 'rule_code' => 'BR-A1', 'title' => 'A1', 'rule_text' => 'Text.']);
        CompanyRule::create(['company_id' => $companyB->id, 'rule_category_id' => $category->id, 'rule_code' => 'BR-B1', 'title' => 'B1', 'rule_text' => 'Text.']);

        $response = $this->actingAs($userA)->getJson('/company-rules');

        $response->assertStatus(200);
        $codes = collect($response->json('data'))->pluck('rule_code');
        $this->assertContains('BR-A1', $codes);
        $this->assertNotContains('BR-B1', $codes);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->makeCompanyUser(Company::create(['name' => 'Seed Co']));

        $this->artisan('db:seed', ['--class' => 'RuleCategorySeeder']);
        $this->artisan('db:seed', ['--class' => 'ProjectFlowRuleSeeder']);

        $firstRunCount = CompanyRule::count();
        $this->assertEquals(158, $firstRunCount);

        $this->artisan('db:seed', ['--class' => 'RuleCategorySeeder']);
        $this->artisan('db:seed', ['--class' => 'ProjectFlowRuleSeeder']);

        $this->assertEquals($firstRunCount, CompanyRule::count());
        $this->assertEquals(6, RuleCategory::count());
    }
}
