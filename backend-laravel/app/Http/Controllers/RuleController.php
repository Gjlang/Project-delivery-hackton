<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RuleController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $categories = config('knowledge_rules');

        $rulesByCategory = [];
        foreach ($categories as $prefix => $meta) {
            $rulesByCategory[$prefix] = $meta['model']::where('created_by', $userId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        return view('rules.index', [
            'categories' => $categories,
            'rulesByCategory' => $rulesByCategory,
        ]);
    }

    public function store(Request $request)
    {
        $categories = config('knowledge_rules');

        $validated = $request->validate([
            'category' => 'required|string|in:'.implode(',', array_keys($categories)),
            'rule_code' => 'required|string|max:50',
            'section' => 'nullable|string|max:255',
            'title' => 'required|string|max:255',
            'rule_text' => 'required|string|max:5000',
        ]);

        $userId = $request->user()->id;
        $model = $categories[$validated['category']]['model'];

        $nextSortOrder = ((int) $model::where('created_by', $userId)->max('sort_order')) + 1;

        $rule = $model::create([
            'created_by' => $userId,
            'rule_code' => $validated['rule_code'],
            'section' => $validated['section'] ?? null,
            'title' => $validated['title'],
            'rule_text' => $validated['rule_text'],
            'sort_order' => $nextSortOrder,
        ]);

        $this->syncToQdrant($validated['category'], $rule->id);

        return back()->with('status', "\"{$validated['rule_code']}\" added.");
    }

    public function update(Request $request, string $category, int $id)
    {
        $model = $this->resolveModel($category);

        $validated = $request->validate([
            'section' => 'nullable|string|max:255',
            'title' => 'required|string|max:255',
            'rule_text' => 'required|string|max:5000',
        ]);

        $rule = $model::where('id', $id)->where('created_by', $request->user()->id)->firstOrFail();
        $rule->update($validated);

        $this->syncToQdrant($category, $rule->id);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok']);
        }

        return back()->with('status', "\"{$rule->rule_code}\" updated.");
    }

    public function destroy(Request $request, string $category, int $id)
    {
        $model = $this->resolveModel($category);

        $rule = $model::where('id', $id)->where('created_by', $request->user()->id)->firstOrFail();
        $rule->delete();

        $this->deleteFromQdrant($category, $id);

        return back()->with('status', "\"{$rule->rule_code}\" removed.");
    }

    private function resolveModel(string $category): string
    {
        $categories = config('knowledge_rules');

        abort_unless(isset($categories[$category]), 404);

        return $categories[$category]['model'];
    }

    /**
     * Rules Management writes straight to MySQL, but the AI chat's rule
     * retrieval reads from Qdrant -- without this, a hand-typed rule would
     * exist in the DB but never actually be found/checked. Best-effort: a
     * Qdrant hiccup shouldn't roll back or block the MySQL write the user
     * is waiting on.
     */
    private function syncToQdrant(string $category, int $ruleId): void
    {
        try {
            Http::timeout(30)
                ->withHeaders(['X-API-Key' => config('services.python_indexer.api_key')])
                ->post(config('services.python_indexer.base_url')."/rules/{$category}/{$ruleId}/index");
        } catch (\Throwable $e) {
            Log::warning('Rules Management: Qdrant index sync failed', ['category' => $category, 'rule_id' => $ruleId, 'error' => $e->getMessage()]);
        }
    }

    private function deleteFromQdrant(string $category, int $ruleId): void
    {
        try {
            Http::timeout(30)
                ->withHeaders(['X-API-Key' => config('services.python_indexer.api_key')])
                ->delete(config('services.python_indexer.base_url')."/rules/{$category}/{$ruleId}/index");
        } catch (\Throwable $e) {
            Log::warning('Rules Management: Qdrant delete sync failed', ['category' => $category, 'rule_id' => $ruleId, 'error' => $e->getMessage()]);
        }
    }
}
