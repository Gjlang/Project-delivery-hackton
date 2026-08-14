<?php

namespace App\Services\Testing;

use App\Models\CompanyRule;
use App\Models\WebsiteTestResult;
use App\Services\LLM\LLMException;
use App\Services\LLM\LLMService;
use Illuminate\Support\Facades\Log;

/**
 * Generates supplemental, grounded feedback for FAIL/WARNING results.
 * Never invents evidence and never changes the deterministic PASS/FAIL/
 * WARNING/NOT_TESTABLE result -- if the LLM fails or returns unusable
 * output, a deterministic template is used instead and the test result
 * itself is untouched either way.
 */
class WebsiteTestFeedbackService
{
    public function __construct(private readonly LLMService $llm)
    {
    }

    public function generate(WebsiteTestResult $result, CompanyRule $rule): void
    {
        $prompt = $this->buildPrompt($result, $rule);

        try {
            $raw = $this->llm->generate($prompt, jsonMode: true);
            $decoded = json_decode($raw, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                throw new LLMException('Feedback LLM returned invalid JSON.');
            }

            $explanation = $this->cleanString($decoded['explanation'] ?? null);
            $impact = $this->cleanString($decoded['impact'] ?? null);
            $recommendation = $this->cleanString($decoded['recommendation'] ?? null);

            if (! $explanation && ! $impact && ! $recommendation) {
                throw new LLMException('Feedback LLM returned no usable fields.');
            }

            $result->update([
                'ai_explanation' => $explanation ?? $this->defaultExplanation($result, $rule),
                'ai_impact' => $impact,
                'ai_recommendation' => $recommendation,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Website test feedback generation failed, using deterministic fallback', ['result_id' => $result->id, 'error' => $e->getMessage()]);

            $result->update([
                'ai_explanation' => $this->defaultExplanation($result, $rule),
                'ai_impact' => null,
                'ai_recommendation' => null,
            ]);
        }
    }

    private function buildPrompt(WebsiteTestResult $result, CompanyRule $rule): string
    {
        $ruleText = str_replace(["\n", '"'], [' ', "'"], (string) $rule->rule_text);
        $observed = str_replace(["\n", '"'], [' ', "'"], (string) $result->observed_behavior);
        $expected = str_replace(["\n", '"'], [' ', "'"], (string) $result->expected_behavior);

        return <<<PROMPT
You are a website-testing feedback assistant. You are given the result of an
already-completed, deterministic browser test. Your only job is to explain
it in plain language for a project manager.

Strict rules:
- Do not invent evidence beyond what is given below.
- Do not change or contradict the test result ({$result->status}).
- Do not claim backend/infrastructure problems -- only what a browser can observe.
- Do not invent company standards beyond the rule text given.
- Return ONLY valid JSON: {"explanation": "string", "impact": "string", "recommendation": "string"}

Rule: {$rule->rule_code} - {$rule->title}
Rule text: {$ruleText}

Test result: {$result->status}
Expected behaviour: {$expected}
Observed behaviour: {$observed}

Return the JSON now:
PROMPT;
    }

    private function cleanString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return ($value === '' || strtolower($value) === 'string') ? null : $value;
    }

    private function defaultExplanation(WebsiteTestResult $result, CompanyRule $rule): string
    {
        return "{$rule->rule_code} ({$rule->title}) returned {$result->status}. ".($result->observed_behavior ?: 'See evidence for details.');
    }
}
