<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $employees = Employee::where('created_by', $request->user()->id)
            ->orderBy('name')
            ->get();

        return view('employees.index', [
            'employees' => $employees,
            'roles' => config('employee_roles'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'in:'.implode(',', config('employee_roles'))],
            'skills' => ['nullable', 'string', 'max:1000'],
            'skill_level' => ['required', 'string', 'in:Junior,Intermediate,Senior'],
        ]);

        Employee::create([
            'company_id' => $request->user()->company_id,
            'created_by' => $request->user()->id,
            'name' => $validated['name'],
            'role' => $validated['role'],
            'skills' => $this->parseSkills($validated['skills'] ?? null),
            'skill_level' => $validated['skill_level'],
            'status' => 'active',
        ]);

        return back()->with('status', "\"{$validated['name']}\" added.");
    }

    public function destroy(Request $request, Employee $employee)
    {
        abort_unless($employee->created_by === $request->user()->id, 404);

        $employee->delete();

        return back()->with('status', 'Employee removed.');
    }

    private function parseSkills(?string $skills): array
    {
        if (! $skills) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $skills))));
    }
}
