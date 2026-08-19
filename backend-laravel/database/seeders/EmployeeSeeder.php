<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['name' => 'Alice Tan', 'role' => 'Backend Engineer', 'skills' => ['PHP', 'Laravel', 'MySQL'], 'skill_level' => 'Senior', 'active_project_count' => 1],
            ['name' => 'Budi Santoso', 'role' => 'Frontend Engineer', 'skills' => ['JavaScript', 'Vue', 'Tailwind'], 'skill_level' => 'Intermediate', 'active_project_count' => 2],
            ['name' => 'Chen Wei', 'role' => 'Backend Engineer', 'skills' => ['Python', 'FastAPI', 'PostgreSQL'], 'skill_level' => 'Junior', 'active_project_count' => 0],
        ];

        Company::all()->each(function (Company $company) use ($templates) {
            foreach ($templates as $template) {
                Employee::firstOrCreate(
                    ['company_id' => $company->id, 'name' => $template['name']],
                    [
                        'role' => $template['role'],
                        'skills' => $template['skills'],
                        'skill_level' => $template['skill_level'],
                        'active_project_count' => $template['active_project_count'],
                        'status' => 'active',
                    ]
                );
            }
        });
    }
}
