<?php

namespace Database\Seeders;

use App\Models\RuleCategory;
use Illuminate\Database\Seeder;

class RuleCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['code' => 'BR', 'name' => 'Business Rules', 'description' => 'Project information, classification, phase/task generation, complexity, duration, dependencies, and completion-date calculation.'],
            ['code' => 'CP', 'name' => 'Company Rules and Policies', 'description' => 'Procurement, communication, confidentiality, vendor, and general company-policy governance.'],
            ['code' => 'EW', 'name' => 'Employee and Working Rules', 'description' => 'Employee eligibility, concurrent-project capacity, team-size, and staffing recommendation rules.'],
            ['code' => 'SC', 'name' => 'Security and Compliance', 'description' => 'Browser-observable security and compliance standards for deployed web applications.'],
            ['code' => 'TS', 'name' => 'Technical Standards', 'description' => 'Website availability, UI, forms/workflows, browser reliability, accessibility, and testing-result standards.'],
            ['code' => 'AG', 'name' => 'Approval and Governance', 'description' => 'Project planning approval, progress/completion tracking, and website-testing governance.'],
        ];

        foreach ($categories as $category) {
            RuleCategory::updateOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
