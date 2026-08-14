<?php

namespace App\Services\Requirements;

/**
 * Builds the Requirement Analysis prompt. Kept deliberately separate from
 * RequirementAnalysisService so the prompt text can be iterated on without
 * touching orchestration logic.
 *
 * business_objective / project_description / start_date are deliberately
 * NOT asked of the model -- their presence is checked directly against the
 * project row in the backend (RequirementAnalysisService), which is more
 * reliable than trusting a small local model to "notice" a field either
 * exists or doesn't. The model's job is narrower: turn the free-text
 * requirements list into structured, categorized requirements.
 */
class RequirementAnalysisPromptBuilder
{
    public function build(string $description, string $requirementsRaw, array $relevantRuleSummaries = []): string
    {
        $rulesBlock = '';
        if (! empty($relevantRuleSummaries)) {
            $rulesBlock = "Relevant company rules for context (do not copy these into the output, they only describe what \"good\" project information looks like):\n";
            foreach ($relevantRuleSummaries as $rule) {
                $rulesBlock .= "- {$rule}\n";
            }
            $rulesBlock .= "\n";
        }

        return <<<PROMPT
You are a requirement analysis component inside a project planning system.

Your ONLY job is language understanding: read the project description and
requirements list below, and organize them into structured categories.

Strict rules:
- Extract information ONLY from the text given below. Do not invent requirements, features, integrations, or roles that are not implied by the text.
- Do NOT generate a project plan, phases, or development tasks.
- Do NOT classify the project as Small, Medium, or Large.
- Do NOT assign employees or estimate durations.
- If a requirement is vague (e.g. "make it secure", "integrate with our system") without saying what specifically is needed, put it in clarifications_required instead of guessing the detail.
- If the user did not state a priority or mandatory flag for something, use null / false. Never invent a priority.
- Preserve the original wording of each requirement in its "source_text" field.
- Return ONLY valid JSON. No prose, no markdown code fences, no explanation before or after the JSON.

{$rulesBlock}Project description:
{$description}

Requirements list (as provided by the user):
{$requirementsRaw}

Return JSON matching EXACTLY this structure (omit nothing, use empty arrays [] when a category has nothing):

{
  "major_features": [{"name": "string", "source_text": "string"}],
  "functional_requirements": [{"name": "string", "description": "string", "source_text": "string", "priority": null, "mandatory": false}],
  "non_functional_requirements": [{"name": "string", "description": "string", "source_text": "string", "priority": null, "mandatory": false}],
  "business_constraints": [{"name": "string", "source_text": "string"}],
  "integrations": [{"name": "string", "purpose": "string", "source_text": "string"}],
  "data_requirements": [{"name": "string", "description": "string"}],
  "user_roles": ["string"],
  "platforms": ["string"],
  "clarifications_required": [{"source_text": "string", "reason": "string", "clarification_question": "string"}],
  "assumptions": ["string"]
}

The words "string", "true"/"false" and "null" above are placeholders showing
the expected JSON type for each field -- they are NOT example content. Never
write the literal word "string" as a value. Every "name", "description", and
"source_text" value you return must be built from the actual project
description and requirements list given above, not copied from this
instruction block.

Now return the JSON for the actual project description and requirements given above. JSON only:
PROMPT;
    }
}
