<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyRule;
use App\Models\RuleCategory;
use App\Models\RuleChunk;
use App\Services\CompanyRules\CompanyRuleReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyRuleReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function makeRule(Company $company, array $overrides = []): CompanyRule
    {
        $category = RuleCategory::firstOrCreate(['code' => $overrides['category_code'] ?? 'BR'], ['name' => 'Business Rules']);

        return CompanyRule::create([
            'company_id' => $company->id,
            'rule_category_id' => $category->id,
            'rule_code' => $overrides['rule_code'] ?? 'BR-001',
            'title' => $overrides['title'] ?? 'Business Objective Required',
            'rule_text' => 'Every project must have a stated business objective.',
            'version' => '1.0',
            'status' => $overrides['status'] ?? 'active',
            'is_active' => $overrides['is_active'] ?? true,
        ]);
    }

    public function test_it_reports_not_configured_when_company_has_zero_active_rules(): void
    {
        $company = Company::create(['name' => 'Empty Co']);

        $result = (new CompanyRuleReadinessService)->evaluate($company->id);

        $this->assertSame(CompanyRuleReadinessService::NOT_CONFIGURED, $result['status']);
        $this->assertSame(0, $result['active_rule_count']);
        $this->assertTrue((new CompanyRuleReadinessService)->isBlocking($company->id));
    }

    public function test_it_reports_processing_when_chunks_are_not_yet_indexed(): void
    {
        $company = Company::create(['name' => 'Processing Co']);
        $rule = $this->makeRule($company);
        RuleChunk::create([
            'company_rule_id' => $rule->id,
            'chunk_index' => 0,
            'chunk_text' => 'chunk text',
            'embedding_status' => 'pending',
        ]);

        $service = new CompanyRuleReadinessService;
        $result = $service->evaluate($company->id);

        $this->assertSame(CompanyRuleReadinessService::PROCESSING, $result['status']);
        $this->assertTrue($service->isBlocking($company->id));
    }

    public function test_it_reports_ready_when_all_active_rules_have_indexed_chunks(): void
    {
        $company = Company::create(['name' => 'Ready Co']);
        $rule = $this->makeRule($company);
        RuleChunk::create([
            'company_rule_id' => $rule->id,
            'chunk_index' => 0,
            'chunk_text' => 'chunk text',
            'embedding_status' => 'embedded',
        ]);

        $service = new CompanyRuleReadinessService;
        $result = $service->evaluate($company->id);

        $this->assertSame(CompanyRuleReadinessService::READY, $result['status']);
        $this->assertFalse($service->isBlocking($company->id));
    }

    public function test_it_reports_ready_with_warnings_when_no_active_business_rules_exist(): void
    {
        $company = Company::create(['name' => 'No BR Co']);
        $rule = $this->makeRule($company, ['category_code' => 'SC', 'rule_code' => 'SC-001']);
        RuleChunk::create([
            'company_rule_id' => $rule->id,
            'chunk_index' => 0,
            'chunk_text' => 'chunk text',
            'embedding_status' => 'embedded',
        ]);

        $service = new CompanyRuleReadinessService;
        $result = $service->evaluate($company->id);

        $this->assertSame(CompanyRuleReadinessService::READY_WITH_WARNINGS, $result['status']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertFalse($service->isBlocking($company->id));
    }

    public function test_it_ignores_other_companies_rules_when_computing_readiness(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $ruleB = $this->makeRule($companyB);
        RuleChunk::create([
            'company_rule_id' => $ruleB->id,
            'chunk_index' => 0,
            'chunk_text' => 'chunk text',
            'embedding_status' => 'embedded',
        ]);

        $service = new CompanyRuleReadinessService;

        $this->assertSame(CompanyRuleReadinessService::NOT_CONFIGURED, $service->evaluate($companyA->id)['status']);
        $this->assertSame(CompanyRuleReadinessService::READY, $service->evaluate($companyB->id)['status']);
    }
}
