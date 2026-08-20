<?php

namespace App\Services\Requirements;

use App\Models\BusinessRule;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\RequirementAnalysisRun;
use App\Services\LLM\LLMException;
use App\Services\LLM\LLMService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates requirement analysis for a project:
 *
 *   deterministic field checks (business_objective/description/start_date)
 *   -> targeted BR rule lookup (context only, not a decision maker)
 *   -> LLM structures the free-text requirements
 *   -> validate/normalize the LLM output
 *   -> persist requirements + an analysis run record
 *
 * This stops at structured, validated requirements -- it never classifies
 * project type/size, generates phases/tasks, or triggers any later agent.
 */
class RequirementAnalysisService
{
    public function __construct(
        private readonly LLMService $llm,
        private readonly RequirementAnalysisPromptBuilder $promptBuilder,
        private readonly RequirementAnalysisValidator $validator,
    ) {
    }

    /**
     * @return array{success: bool, project_id: int, analysis_status: string, summary: array, requirements: array, missing_required_information: array, clarifications_required: array, relevant_rules: array, run_id: int}
     */
    public function analyze(Project $project): array
    {
        $startedAt = now();
        Log::info('Requirement analysis started', ['project_id' => $project->id]);

        $missingRequiredInformation = $this->checkRequiredFields($project);

        $relevantRules = $this->retrieveRelevantRules($project->created_by);

        $run = RequirementAnalysisRun::create([
            'project_id' => $project->id,
            'status' => 'running',
            'model_provider' => config('llm.provider'),
            'model_name' => config('llm.ollama.model'),
            'raw_input_snapshot' => [
                'name' => $project->name,
                'business_objective' => $project->business_objective,
                'description' => $project->description,
                'requirements_raw' => $project->requirements_raw,
                'start_date' => $project->start_date?->toDateString(),
            ],
            'relevant_rules' => $relevantRules,
            'started_at' => $startedAt,
        ]);

        try {
            $normalized = $this->runLlmExtraction($project, $relevantRules);
        } catch (LLMException $e) {
            Log::error('Requirement analysis LLM extraction failed', ['project_id' => $project->id, 'error' => $e->getMessage()]);

            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            $project->update(['requirement_analysis_status' => 'failed']);

            return [
                'success' => false,
                'project_id' => $project->id,
                'analysis_status' => 'failed',
                'summary' => [],
                'requirements' => [],
                'missing_required_information' => $missingRequiredInformation,
                'clarifications_required' => [],
                'relevant_rules' => $relevantRules,
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ];
        }

        $analysisStatus = (! empty($missingRequiredInformation) || ! empty($normalized['clarifications_required']))
            ? 'needs_clarification'
            : 'complete';

        $requirements = $this->persistRequirements($project, $normalized);

        $run->update([
            'status' => $analysisStatus,
            'structured_output' => $normalized,
            'completed_at' => now(),
        ]);

        $project->update(['requirement_analysis_status' => $analysisStatus]);

        $summary = $this->buildSummary($normalized, $missingRequiredInformation);

        Log::info('Requirement analysis completed', [
            'project_id' => $project->id,
            'analysis_status' => $analysisStatus,
            'summary' => $summary,
        ]);

        return [
            'success' => true,
            'project_id' => $project->id,
            'analysis_status' => $analysisStatus,
            'summary' => $summary,
            'requirements' => $requirements,
            'missing_required_information' => $missingRequiredInformation,
            'clarifications_required' => $normalized['clarifications_required'],
            'relevant_rules' => $relevantRules,
            'run_id' => $run->id,
        ];
    }

    /**
     * Deterministic checks -- never delegated to the LLM (BR-001/002/003).
     *
     * @return array<int, array{field: string, rule_code: string, message: string}>
     */
    private function checkRequiredFields(Project $project): array
    {
        $missing = [];

        if (empty(trim((string) $project->business_objective))) {
            $missing[] = ['field' => 'business_objective', 'rule_code' => 'BR-001', 'message' => 'Business objective is required before project planning may begin.'];
        }

        if (empty(trim((string) $project->description))) {
            $missing[] = ['field' => 'project_description', 'rule_code' => 'BR-002', 'message' => 'Project description is required to support requirement analysis and planning.'];
        }

        if (empty($project->start_date)) {
            $missing[] = ['field' => 'start_date', 'rule_code' => 'BR-003', 'message' => 'Start date is required as the scheduling reference point.'];
        }

        return $missing;
    }

    /**
     * @return array<int, array{rule_code: string, title: string}>
     */
    private function retrieveRelevantRules(?int $createdBy): array
    {
        try {
            $rules = BusinessRule::where('created_by', $createdBy)->orderBy('sort_order')->take(5)->get(['rule_code', 'title']);
        } catch (\Throwable $e) {
            // Retrieval is context/traceability only -- never block analysis on it.
            Log::warning('Requirement analysis: rule lookup failed, continuing without context', ['error' => $e->getMessage()]);

            return [];
        }

        return $rules
            ->map(fn ($r) => ['rule_code' => $r->rule_code, 'title' => $r->title])
            ->all();
    }

    private function runLlmExtraction(Project $project, array $relevantRules): array
    {
        $ruleSummaries = collect($relevantRules)->map(fn ($r) => "{$r['rule_code']}: {$r['title']}")->all();

        $prompt = $this->promptBuilder->build(
            (string) $project->description,
            (string) $project->requirements_raw,
            $ruleSummaries
        );

        $lastError = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $raw = $this->llm->generate($prompt, jsonMode: true);
            } catch (LLMException $e) {
                $lastError = $e;

                continue;
            }

            $decoded = json_decode($raw, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Requirement analysis: LLM returned invalid JSON', ['attempt' => $attempt, 'project_id' => $project->id]);
                $lastError = new LLMException('LLM returned invalid JSON: '.json_last_error_msg());

                continue;
            }

            $result = $this->validator->validate($decoded);

            if (! $result['valid']) {
                Log::warning('Requirement analysis: LLM JSON failed schema validation', ['attempt' => $attempt, 'project_id' => $project->id, 'warnings' => $result['warnings']]);
                $lastError = new LLMException('LLM JSON did not match the expected schema.');

                continue;
            }

            return $result['normalized'];
        }

        throw $lastError ?? new LLMException('LLM extraction failed for an unknown reason.');
    }

    /**
     * Replace this project's derived requirements atomically so re-analysis
     * never accumulates duplicates.
     */
    private function persistRequirements(Project $project, array $normalized): array
    {
        return DB::transaction(function () use ($project, $normalized) {
            $project->requirements()->delete();

            $rows = [];

            $rows = [...$rows, ...$this->mapRows($normalized['major_features'], 'feature')];
            $rows = [...$rows, ...$this->mapRows($normalized['functional_requirements'], 'functional')];
            $rows = [...$rows, ...$this->mapRows($normalized['non_functional_requirements'], 'non_functional')];
            $rows = [...$rows, ...$this->mapRows($normalized['business_constraints'], 'business_constraint')];
            $rows = [...$rows, ...$this->mapIntegrations($normalized['integrations'])];
            $rows = [...$rows, ...$this->mapRows($normalized['data_requirements'], 'data')];
            $rows = [...$rows, ...$this->mapSimpleList($normalized['user_roles'], 'user_role')];
            $rows = [...$rows, ...$this->mapSimpleList($normalized['platforms'], 'platform')];
            $rows = [...$rows, ...$this->mapClarifications($normalized['clarifications_required'])];

            foreach ($rows as $row) {
                ProjectRequirement::create([...$row, 'project_id' => $project->id]);
            }

            return $project->requirements()->get()->groupBy('requirement_type')->map->values()->toArray();
        });
    }

    private function mapRows(array $items, string $type): array
    {
        return collect($items)->map(fn ($item) => [
            'requirement_type' => $type,
            'name' => $item['name'] ?? null,
            'description' => $item['description'] ?? null,
            'source_text' => $item['source_text'] ?? null,
            'priority' => $item['priority'] ?? null,
            'is_mandatory' => $item['mandatory'] ?? false,
            'status' => 'identified',
        ])->all();
    }

    private function mapIntegrations(array $items): array
    {
        return collect($items)->map(fn ($item) => [
            'requirement_type' => 'integration',
            'name' => $item['name'] ?? null,
            'description' => $item['purpose'] ?? null,
            'source_text' => $item['source_text'] ?? null,
            'priority' => $item['priority'] ?? null,
            'is_mandatory' => $item['mandatory'] ?? false,
            'status' => 'identified',
            'metadata' => isset($item['purpose']) ? ['purpose' => $item['purpose']] : null,
        ])->all();
    }

    private function mapSimpleList(array $items, string $type): array
    {
        return collect($items)->map(fn ($value) => [
            'requirement_type' => $type,
            'name' => $value,
            'status' => 'identified',
        ])->all();
    }

    private function mapClarifications(array $items): array
    {
        return collect($items)->map(fn ($item) => [
            'requirement_type' => 'functional',
            'name' => $item['name'] ?? null,
            'source_text' => $item['source_text'] ?? null,
            'status' => 'needs_clarification',
            'clarification_reason' => $item['reason'] ?? null,
            'clarification_question' => $item['clarification_question'] ?? null,
        ])->all();
    }

    private function buildSummary(array $normalized, array $missing): array
    {
        return [
            'major_features' => count($normalized['major_features']),
            'functional_requirements' => count($normalized['functional_requirements']),
            'non_functional_requirements' => count($normalized['non_functional_requirements']),
            'business_constraints' => count($normalized['business_constraints']),
            'integrations' => count($normalized['integrations']),
            'data_requirements' => count($normalized['data_requirements']),
            'user_roles' => count($normalized['user_roles']),
            'platforms' => count($normalized['platforms']),
            'clarifications_required' => count($normalized['clarifications_required']),
            'missing_required_information' => count($missing),
        ];
    }
}
