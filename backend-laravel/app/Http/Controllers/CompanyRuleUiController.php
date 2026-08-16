<?php

namespace App\Http\Controllers;

use App\Services\CompanyRules\CompanyRuleReadinessService;
use Illuminate\Http\Request;

class CompanyRuleUiController extends Controller
{
    /**
     * Thin Blade shell around the existing JSON CompanyRuleController -- that
     * controller stays untouched; this page just consumes it client-side and
     * shows the readiness banner server-side.
     */
    public function index(Request $request, CompanyRuleReadinessService $readiness)
    {
        return view('company-rules.index', [
            'readiness' => $readiness->evaluate($request->user()->company_id),
        ]);
    }
}
