<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyRule;
use App\Models\KnowledgeDocument;
use App\Models\RuleCategory;
use Illuminate\Database\Seeder;

class ProjectFlowRuleSeeder extends Seeder
{
    /**
     * Seeds the 158 default ProjectFlow AI Knowledge Base rules (BR/CP/EW/
     * SC/TS/AG), verbatim from the source document, for the first company
     * found in the system.
     *
     * Idempotent: re-running updates existing rows (keyed by company_id +
     * rule_code + version) instead of duplicating them.
     */
    public function run(): void
    {
        $company = Company::first();

        if (! $company) {
            $this->command?->warn('No company found -- skipping ProjectFlowRuleSeeder. Create a company first.');

            return;
        }

        $userId = $company->users()->value('id');

        $sourceDocument = KnowledgeDocument::updateOrCreate(
            [
                'company_id' => $company->id,
                'title' => 'ProjectFlow AI Knowledge Base Rules',
                'version' => '1.0',
            ],
            [
                'document_type' => 'mixed_rules',
                'original_filename' => null,
                'stored_path' => null,
                'mime_type' => null,
                'file_size' => null,
                'status' => 'processed',
                'uploaded_by' => $userId,
                'uploaded_at' => now(),
            ]
        );

        $categoryIds = RuleCategory::pluck('id', 'code');

        $data = require __DIR__.'/data/projectflow_rules.php';

        $counts = [];

        foreach ($data as $categoryCode => $rules) {
            $categoryId = $categoryIds[$categoryCode] ?? null;

            if (! $categoryId) {
                $this->command?->warn("Rule category [{$categoryCode}] not found -- run RuleCategorySeeder first. Skipping its rules.");

                continue;
            }

            $counts[$categoryCode] = 0;

            foreach ($rules as $rule) {
                $companyRule = CompanyRule::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'rule_code' => $rule['code'],
                        'version' => '1.0',
                    ],
                    [
                        'rule_category_id' => $categoryId,
                        'source_document_id' => $sourceDocument->id,
                        'title' => $rule['title'],
                        'rule_text' => $rule['text'],
                        'section' => $rule['section'] ?? null,
                        'subsection' => null,
                        'applicable_condition' => $rule['applicable_condition'] ?? null,
                        'required_behavior' => $rule['required_behavior'] ?? null,
                        'expected_outcome' => $rule['expected_outcome'] ?? null,
                        'evaluation_type' => $rule['evaluation_type'] ?? 'other',
                        'is_mandatory' => $rule['is_mandatory'] ?? false,
                        'is_active' => true,
                        'status' => 'active',
                        'metadata' => $rule['metadata'] ?? null,
                        'source_reference' => 'ProjectFlow AI Knowledge Base Rules',
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]
                );

                // Idempotent refresh: replace structured children rather than
                // accumulating duplicates on repeated seeder runs.
                $companyRule->parameters()->delete();
                foreach ($rule['parameters'] ?? [] as $parameter) {
                    $companyRule->parameters()->create([
                        'parameter_key' => $parameter['key'],
                        'parameter_value' => $parameter['value'],
                        'value_type' => $parameter['value_type'] ?? 'string',
                        'unit' => $parameter['unit'] ?? null,
                    ]);
                }

                $companyRule->conditions()->delete();
                foreach ($rule['conditions'] ?? [] as $index => $condition) {
                    $companyRule->conditions()->create([
                        'field' => $condition['field'],
                        'operator' => $condition['operator'],
                        'value' => is_array($condition['value']) ? json_encode($condition['value']) : $condition['value'],
                        'value_type' => is_array($condition['value']) ? 'array' : 'string',
                        'sort_order' => $index,
                    ]);
                }

                $counts[$categoryCode]++;
            }
        }

        $total = array_sum($counts);

        $this->command?->info('ProjectFlow rule import for company: '.$company->name);
        foreach ($counts as $code => $count) {
            $this->command?->info("  {$code}: {$count}");
        }
        $this->command?->info("  Total: {$total}");
    }
}
