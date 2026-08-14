<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'rule_category_id' => ['required', Rule::exists('rule_categories', 'id')],
            'source_document_id' => ['nullable', Rule::exists('knowledge_documents', 'id')->where('company_id', $companyId)],
            'supersedes_rule_id' => ['nullable', Rule::exists('company_rules', 'id')->where('company_id', $companyId)],

            'rule_code' => [
                'required', 'string', 'max:50',
                Rule::unique('company_rules', 'rule_code')
                    ->where('company_id', $companyId)
                    ->where('version', $this->input('version', '1.0')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'rule_text' => ['required', 'string'],

            'section' => ['nullable', 'string', 'max:255'],
            'subsection' => ['nullable', 'string', 'max:255'],

            'applicable_condition' => ['nullable', 'string'],
            'required_behavior' => ['nullable', 'string'],
            'expected_outcome' => ['nullable', 'string'],

            'rule_type' => ['nullable', 'string', 'max:50'],
            'evaluation_type' => ['nullable', Rule::in([
                'presence', 'boolean', 'numeric', 'threshold', 'classification',
                'range', 'list', 'workflow', 'policy', 'manual_review', 'semantic', 'other',
            ])],

            'severity' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'max:50'],

            'is_mandatory' => ['boolean'],
            'is_active' => ['boolean'],

            'version' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'inactive', 'superseded', 'archived'])],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],

            'metadata' => ['nullable', 'array'],

            'source_reference' => ['nullable', 'string', 'max:255'],
            'source_page' => ['nullable', 'integer', 'min:1'],

            'conditions' => ['nullable', 'array'],
            'conditions.*.field' => ['required_with:conditions', 'string', 'max:255'],
            'conditions.*.operator' => ['required_with:conditions', 'string', 'max:50'],
            'conditions.*.value' => ['nullable'],
            'conditions.*.value_type' => ['nullable', 'string', 'max:50'],
            'conditions.*.condition_group' => ['nullable', 'string', 'max:255'],
            'conditions.*.logical_operator' => ['nullable', 'string', 'max:10'],
            'conditions.*.description' => ['nullable', 'string'],

            'parameters' => ['nullable', 'array'],
            'parameters.*.parameter_key' => ['required_with:parameters', 'string', 'max:255'],
            'parameters.*.parameter_value' => ['required_with:parameters', 'string'],
            'parameters.*.value_type' => ['nullable', 'string', 'max:50'],
            'parameters.*.unit' => ['nullable', 'string', 'max:50'],
        ];
    }
}
