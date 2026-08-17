<?php

namespace Tests\Feature;

use App\Models\BusinessRule;
use App\Models\SecurityComplianceRule;
use App\Services\CompanyRules\CompanyRuleReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyRuleReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_not_configured_when_no_rules_exist_in_any_category(): void
    {
        $service = new CompanyRuleReadinessService;
        $result = $service->evaluate();

        $this->assertSame(CompanyRuleReadinessService::NOT_CONFIGURED, $result['status']);
        $this->assertSame(0, $result['active_rule_count']);
        $this->assertTrue($service->isBlocking());
    }

    public function test_it_reports_ready_with_warnings_when_only_some_categories_have_rules(): void
    {
        BusinessRule::create(['rule_code' => 'BR-001', 'title' => 'Business Objective Required', 'rule_text' => 'text']);

        $service = new CompanyRuleReadinessService;
        $result = $service->evaluate();

        $this->assertSame(CompanyRuleReadinessService::READY_WITH_WARNINGS, $result['status']);
        $this->assertSame(1, $result['active_rule_count']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertFalse($service->isBlocking());
    }

    public function test_it_reports_ready_when_every_category_has_at_least_one_rule(): void
    {
        foreach (config('knowledge_rules') as $prefix => $meta) {
            $meta['model']::create(['rule_code' => "{$prefix}-001", 'title' => "Sample {$prefix} rule", 'rule_text' => 'text']);
        }

        $service = new CompanyRuleReadinessService;
        $result = $service->evaluate();

        $this->assertSame(CompanyRuleReadinessService::READY, $result['status']);
        $this->assertEmpty($result['warnings']);
        $this->assertFalse($service->isBlocking());
    }

    public function test_it_warns_when_business_rules_are_missing_even_if_other_categories_are_populated(): void
    {
        SecurityComplianceRule::create(['rule_code' => 'SC-001', 'title' => 'HTTPS Requirement', 'rule_text' => 'text']);

        $service = new CompanyRuleReadinessService;
        $result = $service->evaluate();

        $this->assertSame(CompanyRuleReadinessService::READY_WITH_WARNINGS, $result['status']);
        $this->assertTrue(collect($result['warnings'])->contains(fn ($w) => str_contains($w, 'Business Rules')));
    }
}
