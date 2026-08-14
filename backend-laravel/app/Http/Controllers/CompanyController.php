<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Document;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function create(Request $request)
    {
        if ($request->user()->company_id) {
            return redirect()->route('dashboard');
        }

        return view('company.create');
    }

    public function store(Request $request)
    {
        if ($request->user()->company_id) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);

        $company = Company::create($validated);

        $request->user()->update(['company_id' => $company->id]);

        // Any documents uploaded before a company existed (e.g. during early
        // setup/testing) are attached to the first company created.
        Document::whereNull('company_id')->update(['company_id' => $company->id]);

        return redirect()->route('dashboard')->with('status', "Welcome to {$company->name}!");
    }
}
