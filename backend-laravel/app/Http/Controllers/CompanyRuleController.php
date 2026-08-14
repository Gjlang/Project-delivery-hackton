<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRuleRequest;
use App\Http\Requests\UpdateCompanyRuleRequest;
use App\Models\CompanyRule;
use App\Services\Rules\RuleRetrievalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyRuleController extends Controller
{
    /**
     * GET /company-rules
     *
     * Filters: category, status, rule_code, is_active, version, source_document, search.
     */
    public function index(Request $request)
    {
        $query = CompanyRule::with(['category', 'sourceDocument', 'conditions', 'parameters'])
            ->forCompany($request->user()->company_id);

        if ($category = $request->query('category')) {
            $query->whereHas('category', fn ($q) => $q->where('code', $category));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($ruleCode = $request->query('rule_code')) {
            $query->where('rule_code', 'like', "%{$ruleCode}%");
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($version = $request->query('version')) {
            $query->where('version', $version);
        }

        if ($sourceDocumentId = $request->query('source_document')) {
            $query->where('source_document_id', $sourceDocumentId);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('rule_text', 'like', "%{$search}%")
                    ->orWhere('rule_code', 'like', "%{$search}%");
            });
        }

        $rules = $query->orderBy('rule_code')->paginate(50)->withQueryString();

        return response()->json($rules);
    }

    /**
     * POST /company-rules
     */
    public function store(StoreCompanyRuleRequest $request)
    {
        $validated = $request->validated();
        $conditions = $validated['conditions'] ?? [];
        $parameters = $validated['parameters'] ?? [];
        unset($validated['conditions'], $validated['parameters']);

        $companyRule = CompanyRule::create([
            ...$validated,
            'company_id' => $request->user()->company_id,
            'version' => $validated['version'] ?? '1.0',
            'status' => $validated['status'] ?? 'active',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        foreach ($conditions as $index => $condition) {
            $companyRule->conditions()->create([
                ...$condition,
                'value' => is_array($condition['value'] ?? null) ? json_encode($condition['value']) : ($condition['value'] ?? null),
                'sort_order' => $condition['sort_order'] ?? $index,
            ]);
        }

        foreach ($parameters as $parameter) {
            $companyRule->parameters()->create($parameter);
        }

        return response()->json(
            $companyRule->load(['category', 'sourceDocument', 'conditions', 'parameters']),
            201
        );
    }

    /**
     * GET /company-rules/{company_rule}
     */
    public function show(Request $request, CompanyRule $companyRule)
    {
        $this->authorizeCompanyRule($request, $companyRule);

        return response()->json(
            $companyRule->load(['category', 'sourceDocument', 'conditions', 'parameters', 'chunks', 'supersedesRule', 'supersededBy'])
        );
    }

    /**
     * PUT /company-rules/{company_rule}
     */
    public function update(UpdateCompanyRuleRequest $request, CompanyRule $companyRule)
    {
        $this->authorizeCompanyRule($request, $companyRule);

        $companyRule->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json($companyRule->load(['category', 'sourceDocument', 'conditions', 'parameters']));
    }

    /**
     * PATCH /company-rules/{company_rule}/status
     */
    public function updateStatus(Request $request, CompanyRule $companyRule)
    {
        $this->authorizeCompanyRule($request, $companyRule);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'active', 'inactive', 'superseded', 'archived'])],
        ]);

        $companyRule->update([
            'status' => $validated['status'],
            'is_active' => $validated['status'] === 'active',
            'updated_by' => $request->user()->id,
        ]);

        return response()->json($companyRule->fresh());
    }

    /**
     * DELETE /company-rules/{company_rule}
     *
     * Soft delete only -- historical rule data is never hard-deleted by this
     * endpoint, matching the versioning/history requirement.
     */
    public function destroy(Request $request, CompanyRule $companyRule)
    {
        $this->authorizeCompanyRule($request, $companyRule);

        $companyRule->update([
            'status' => 'archived',
            'is_active' => false,
            'updated_by' => $request->user()->id,
        ]);

        $companyRule->delete();

        return response()->json(['status' => 'archived']);
    }

    /**
     * POST /company-rules/retrieve
     *
     * Semantic search over this company's active rules. Qdrant only
     * identifies candidates; every returned rule is loaded fresh from MySQL.
     */
    public function retrieve(Request $request, RuleRetrievalService $retrieval)
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:1000'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'max:10'],
            'top_k' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $result = $retrieval->retrieve(
            $request->user()->company_id,
            $validated['query'],
            $validated['categories'] ?? [],
            $validated['top_k'] ?? null,
        );

        return response()->json($result);
    }

    /**
     * A rule belongs to exactly one company; block cross-company access even
     * if someone guesses another company's rule id.
     */
    private function authorizeCompanyRule(Request $request, CompanyRule $companyRule): void
    {
        abort_unless($companyRule->company_id === $request->user()->company_id, 404);
    }
}
