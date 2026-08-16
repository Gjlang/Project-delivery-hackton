<?php

namespace App\Services\ProjectCreation;

/**
 * Builds one short, focused prompt per decision (never the full rulebook +
 * full chat history in one call -- the local 1B model can't reliably handle
 * that). Kept separate from ProjectCreationChatService so prompt wording can
 * be iterated on without touching orchestration.
 */
class ProjectCreationPromptBuilder
{
    /**
     * @param  array<int, array{role: string, content: string}>  $chatSummary  Last few turns only.
     * @param  array<string, mixed>  $currentDraft
     * @param  array<int, array{rule_code: string, title: string, category: ?string, rule_text: ?string, applicable_condition: ?string}>  $ruleSummaries
     */
    public function build(string $decisionKey, string $label, array $chatSummary, array $currentDraft, array $ruleSummaries): string
    {
        return $decisionKey === 'project_type'
            ? $this->buildProjectTypePrompt($chatSummary, $currentDraft, $ruleSummaries)
            : $this->buildGenericDecisionPrompt($decisionKey, $label, $chatSummary, $currentDraft, $ruleSummaries);
    }

    private function buildGenericDecisionPrompt(string $decisionKey, string $label, array $chatSummary, array $currentDraft, array $ruleSummaries): string
    {
        $historyBlock = $this->renderHistory($chatSummary);
        $draftBlock = $this->renderDraft($currentDraft);

        $rulesBlock = '';
        if (! empty($ruleSummaries)) {
            $rulesBlock = "Relevant company rules for context (do not copy these into the output, they only describe what \"good\" looks like for this decision):\n";
            foreach ($ruleSummaries as $rule) {
                $rulesBlock .= "- {$rule['rule_code']}: {$rule['title']}\n";
            }
            $rulesBlock .= "\n";
        }

        return <<<PROMPT
You are the AI assistant inside a project-creation chat. Your current focus
is ONLY this decision: "{$label}".

Strict rules:
- Extract information ONLY from the conversation given below. Never invent facts the user did not state.
- If something relevant to this decision is genuinely unclear or missing, ask ONE short clarification question about it instead of guessing.
- Do NOT generate a project plan, phases, tasks, staffing, or size classification.
- Only include fields in draft_patch that this decision is actually about -- do not repeat fields already confirmed in the current draft unless the user just corrected them.
- Return ONLY valid JSON. No prose, no markdown code fences, no explanation before or after the JSON.

{$rulesBlock}Current project draft so far:
{$draftBlock}

Recent conversation:
{$historyBlock}

Return JSON matching EXACTLY this structure:

{
  "assistant_message": "string",
  "draft_patch": {},
  "clarifications": [{"field": "string", "question": "string", "reason": "string"}],
  "analysis_status": "gathering"
}

analysis_status must be one of: "gathering", "needs_clarification", "ready".
draft_patch keys may only be drawn from: name, description, business_objective, requirements_raw, start_date, has_authentication, protected_routes, roles, platforms, integrations.
The word "string" above is a placeholder for the expected JSON type, not example content -- never write the literal word "string" as a value.

Now return the JSON for the actual conversation given above. JSON only:
PROMPT;
    }

    private function buildProjectTypePrompt(array $chatSummary, array $currentDraft, array $ruleSummaries): string
    {
        $historyBlock = $this->renderHistory($chatSummary);
        $draftBlock = $this->renderDraft($currentDraft);

        $candidatesBlock = "No candidate project-type rules were found.\n";
        if (! empty($ruleSummaries)) {
            $candidatesBlock = '';
            foreach ($ruleSummaries as $rule) {
                $candidatesBlock .= "- [{$rule['rule_code']}] {$rule['title']} (category: {$rule['category']})\n";
                if (! empty($rule['rule_text'])) {
                    $candidatesBlock .= "  Rule text: {$rule['rule_text']}\n";
                }
                if (! empty($rule['applicable_condition'])) {
                    $candidatesBlock .= "  Applies when: {$rule['applicable_condition']}\n";
                }
            }
        }

        return <<<PROMPT
You are classifying what KIND of project this is, using ONLY the company's
own rule definitions below as the list of valid project types. Do not invent
a project type that isn't represented by one of these candidate rules.

Strict rules:
- Choose the primary type from the candidate rules below whose "Applies when" / rule text best matches the project draft. You may list additional secondary types if more than one candidate genuinely applies.
- Every rule code you name in source_rules MUST be one of the candidate rule codes listed below -- never invent a rule code.
- If no candidate is a clear, confident match, set primary_project_type to null and confidence to "low" rather than guessing.
- Do NOT generate a project plan, phases, tasks, staffing, or size classification.
- Return ONLY valid JSON. No prose, no markdown code fences, no explanation before or after the JSON.

Candidate project-type rules (the ONLY valid types to choose from):
{$candidatesBlock}

Current project draft:
{$draftBlock}

Recent conversation:
{$historyBlock}

Return JSON matching EXACTLY this structure:

{
  "assistant_message": "string",
  "primary_project_type": "string or null",
  "secondary_project_types": ["string"],
  "source_rules": ["RULE-CODE"],
  "confidence": "low|medium|high",
  "clarifications": [{"field": "string", "question": "string", "reason": "string"}],
  "analysis_status": "gathering"
}

analysis_status must be one of: "gathering", "needs_clarification", "ready".
The word "string" above is a placeholder for the expected JSON type, not example content.

Now return the JSON for the actual project draft and candidates given above. JSON only:
PROMPT;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $chatSummary
     */
    private function renderHistory(array $chatSummary): string
    {
        if (empty($chatSummary)) {
            return '(no prior conversation)';
        }

        $lines = [];
        foreach ($chatSummary as $turn) {
            $speaker = $turn['role'] === 'user' ? 'User' : 'Assistant';
            $lines[] = "{$speaker}: {$turn['content']}";
        }

        return implode("\n", $lines);
    }

    private function renderDraft(array $currentDraft): string
    {
        $filtered = array_filter($currentDraft, fn ($v) => $v !== null && $v !== '' && $v !== []);

        return empty($filtered) ? '(nothing captured yet)' : json_encode($filtered, JSON_PRETTY_PRINT);
    }
}
