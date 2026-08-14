<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyRule;
use App\Models\RuleCategory;
use App\Models\RuleChunk;
use App\Models\User;
use App\Services\Rules\RuleChunkingService;
use App\Services\Rules\RuleRetrievalService;
use App\Services\Rules\RuleVectorSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RuleRetrievalTest extends TestCase
{
    use RefreshDatabase;

    private function makeRule(?Company $company = null, array $overrides = []): CompanyRule
    {
        $company ??= Company::create(['name' => 'Test Co']);
        $category = RuleCategory::firstOrCreate(['code' => $overrides['category_code'] ?? 'EW'], ['name' => 'Employee and Working Rules']);

        return CompanyRule::create([
            'company_id' => $company->id,
            'rule_category_id' => $category->id,
            'rule_code' => $overrides['rule_code'] ?? 'EW-008',
            'title' => $overrides['title'] ?? 'Software Developer Concurrent Project Limit',
            'rule_text' => $overrides['rule_text'] ?? 'An employee must not exceed 3 active projects.',
            'version' => '1.0',
            'status' => $overrides['status'] ?? 'active',
            'is_active' => $overrides['is_active'] ?? true,
        ]);
    }

    private function fakeOllama(int $vectorSize = 4): void
    {
        Http::fake([
            '*/api/embed' => function ($request) use ($vectorSize) {
                $count = count($request->data()['input'] ?? []);

                return Http::response(['model' => 'nomic-embed-text', 'embeddings' => array_fill(0, $count, array_fill(0, $vectorSize, 0.1))]);
            },
        ]);
    }

    private function fakeQdrantInfra(): void
    {
        Http::fake([
            '*/collections/*/points' => Http::response(['result' => true, 'status' => 'ok']),
            '*/collections/projectflow_company_rules' => Http::response(['result' => ['status' => 'green']]),
        ]);
    }

    // ---- Chunking ----

    public function test_chunk_generation_produces_self_contained_text(): void
    {
        $rule = $this->makeRule();
        $texts = app(RuleChunkingService::class)->buildChunkTexts($rule);

        $this->assertCount(1, $texts);
        $this->assertStringContainsString('EW-008', $texts[0]);
        $this->assertStringContainsString('Software Developer Concurrent Project Limit', $texts[0]);
        $this->assertStringContainsString('An employee must not exceed 3 active projects.', $texts[0]);
    }

    public function test_chunk_persistence(): void
    {
        $rule = $this->makeRule();
        $result = app(RuleChunkingService::class)->sync($rule);

        $this->assertCount(1, $result['created_ids']);
        $this->assertDatabaseHas('rule_chunks', [
            'company_rule_id' => $rule->id,
            'chunk_index' => 0,
            'embedding_status' => 'pending',
        ]);
    }

    public function test_unchanged_rule_produces_no_changes_on_second_sync(): void
    {
        $rule = $this->makeRule();
        $chunker = app(RuleChunkingService::class);

        $first = $chunker->sync($rule);
        $second = $chunker->sync($rule->fresh());

        $this->assertCount(1, $first['created_ids']);
        $this->assertCount(0, $second['created_ids']);
        $this->assertCount(0, $second['updated_ids']);
        $this->assertCount(0, $second['changed_ids']);
    }

    public function test_chunk_regeneration_on_rule_text_change_keeps_same_row_id(): void
    {
        $rule = $this->makeRule();
        $chunker = app(RuleChunkingService::class);

        $first = $chunker->sync($rule);
        $chunkId = $first['chunks']->first()->id;

        RuleChunk::whereIn('id', $first['changed_ids'])->update(['embedding_status' => 'embedded']);

        $rule->update(['rule_text' => 'An employee must not exceed 3 active projects. Amended.']);
        $second = $chunker->sync($rule->fresh());

        $this->assertCount(1, $second['updated_ids']);
        $this->assertEquals($chunkId, $second['chunks']->first()->id);
        $this->assertEquals('pending', $second['chunks']->first()->fresh()->embedding_status);
    }

    // ---- Sync service (mocked HTTP) ----

    public function test_sync_embeds_and_upserts_chunks(): void
    {
        $this->fakeOllama();
        $this->fakeQdrantInfra();

        $rule = $this->makeRule();

        $summary = app(RuleVectorSyncService::class)->syncRules(collect([$rule]));

        $this->assertEquals(1, $summary['chunks_embedded']);
        $this->assertEquals(0, $summary['chunks_failed']);

        $chunk = RuleChunk::where('company_rule_id', $rule->id)->first();
        $this->assertEquals('embedded', $chunk->embedding_status);
        $this->assertEquals((string) $chunk->id, $chunk->external_vector_id);

        Http::assertSent(function ($request) use ($chunk) {
            if (! str_contains($request->url(), '/collections/projectflow_company_rules/points') || $request->method() !== 'PUT') {
                return false;
            }

            $points = $request->data()['points'] ?? [];

            return count($points) === 1 && $points[0]['id'] === $chunk->id && $points[0]['payload']['rule_code'] === 'EW-008';
        });
    }

    public function test_sync_is_idempotent_and_does_not_reembed_unchanged_chunks(): void
    {
        $this->fakeOllama();
        $this->fakeQdrantInfra();

        $rule = $this->makeRule();
        $service = app(RuleVectorSyncService::class);

        $service->syncRules(collect([$rule]));
        Http::fake(); // reset the request log
        $this->fakeOllama();
        $this->fakeQdrantInfra();

        $summary = $service->syncRules(collect([$rule->fresh()]));

        $this->assertEquals(0, $summary['chunks_embedded']);
        $this->assertEquals(1, $summary['chunks_unchanged']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/embed'));
    }

    public function test_archived_rule_chunks_are_marked_outdated_and_removed_from_qdrant(): void
    {
        $this->fakeOllama();
        $this->fakeQdrantInfra();

        $rule = $this->makeRule();
        app(RuleVectorSyncService::class)->syncRules(collect([$rule]));

        $rule->update(['status' => 'archived', 'is_active' => false]);

        Http::fake();
        $this->fakeOllama();
        $this->fakeQdrantInfra();

        app(RuleVectorSyncService::class)->syncRules(collect()); // no active rules, but cleanup still runs

        $chunk = RuleChunk::where('company_rule_id', $rule->id)->first();
        $this->assertEquals('outdated', $chunk->embedding_status);
        $this->assertNull($chunk->external_vector_id);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/points/delete'));
    }

    // ---- Retrieval (mocked HTTP) ----

    private function fakeQdrantSearch(array $result): void
    {
        Http::fake([
            '*/api/embed' => Http::response(['model' => 'nomic-embed-text', 'embeddings' => [[0.1, 0.2, 0.3, 0.4]]]),
            '*/collections/*/points/search' => Http::response(['result' => $result]),
        ]);
    }

    public function test_retrieval_response_structure(): void
    {
        $company = Company::create(['name' => 'Retrieval Co']);
        $rule = $this->makeRule($company);

        $this->fakeQdrantSearch([
            ['id' => 1, 'score' => 0.9, 'payload' => ['company_rule_id' => $rule->id, 'category' => 'EW']],
        ]);

        RuleChunk::forceCreate(['company_rule_id' => $rule->id, 'id' => 1, 'chunk_index' => 0, 'chunk_text' => 'matched chunk text', 'embedding_status' => 'embedded', 'external_vector_id' => '1']);

        $result = app(RuleRetrievalService::class)->retrieve($company->id, 'max concurrent projects', [], 5);

        $this->assertEquals('max concurrent projects', $result['query']);
        $this->assertCount(1, $result['results']);
        $r = $result['results'][0];
        $this->assertEquals('EW-008', $r['rule_code']);
        $this->assertEquals(0.9, $r['similarity_score']);
        $this->assertEquals('matched chunk text', $r['matched_chunk']);
        $this->assertArrayHasKey('rule_text', $r['rule']);
        $this->assertArrayHasKey('parameters', $r['rule']);
    }

    public function test_retrieval_deduplicates_multiple_chunks_of_same_rule_by_best_score(): void
    {
        $company = Company::create(['name' => 'Dedup Co']);
        $rule = $this->makeRule($company);

        RuleChunk::forceCreate(['id' => 1, 'company_rule_id' => $rule->id, 'chunk_index' => 0, 'chunk_text' => 'chunk 0', 'embedding_status' => 'embedded']);
        RuleChunk::forceCreate(['id' => 2, 'company_rule_id' => $rule->id, 'chunk_index' => 1, 'chunk_text' => 'chunk 1', 'embedding_status' => 'embedded']);

        $this->fakeQdrantSearch([
            ['id' => 1, 'score' => 0.7, 'payload' => ['company_rule_id' => $rule->id, 'category' => 'EW']],
            ['id' => 2, 'score' => 0.95, 'payload' => ['company_rule_id' => $rule->id, 'category' => 'EW']],
        ]);

        $result = app(RuleRetrievalService::class)->retrieve($company->id, 'query', [], 5);

        $this->assertCount(1, $result['results']);
        $this->assertEquals(0.95, $result['results'][0]['similarity_score']);
        $this->assertEquals('chunk 1', $result['results'][0]['matched_chunk']);
    }

    public function test_retrieval_never_returns_another_companys_rule_even_if_index_is_stale(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $ruleA = $this->makeRule($companyA, ['rule_code' => 'EW-001']);
        $ruleB = $this->makeRule($companyB, ['rule_code' => 'EW-002']);

        RuleChunk::forceCreate(['id' => 1, 'company_rule_id' => $ruleA->id, 'chunk_index' => 0, 'chunk_text' => 'a', 'embedding_status' => 'embedded']);
        RuleChunk::forceCreate(['id' => 2, 'company_rule_id' => $ruleB->id, 'chunk_index' => 0, 'chunk_text' => 'b', 'embedding_status' => 'embedded']);

        // Simulate a misconfigured/stale index returning a point that
        // belongs to a different company than the one searching.
        $this->fakeQdrantSearch([
            ['id' => 1, 'score' => 0.8, 'payload' => ['company_rule_id' => $ruleA->id, 'category' => 'EW']],
            ['id' => 2, 'score' => 0.99, 'payload' => ['company_rule_id' => $ruleB->id, 'category' => 'EW']],
        ]);

        $result = app(RuleRetrievalService::class)->retrieve($companyA->id, 'query', [], 5);

        $codes = collect($result['results'])->pluck('rule_code');
        $this->assertContains('EW-001', $codes);
        $this->assertNotContains('EW-002', $codes);
    }

    public function test_retrieval_search_request_includes_company_and_category_filters(): void
    {
        $company = Company::create(['name' => 'Filter Co']);
        $this->fakeQdrantSearch([]);

        app(RuleRetrievalService::class)->retrieve($company->id, 'query', ['EW', 'BR'], 5);

        Http::assertSent(function ($request) use ($company) {
            if (! str_contains($request->url(), '/points/search')) {
                return false;
            }

            $filter = $request->data()['filter']['must'] ?? [];
            $hasCompany = collect($filter)->contains(fn ($f) => ($f['key'] ?? null) === 'company_id' && $f['match']['value'] === $company->id);
            $hasActive = collect($filter)->contains(fn ($f) => ($f['key'] ?? null) === 'is_active' && $f['match']['value'] === true);
            $hasCategory = collect($filter)->contains(fn ($f) => ($f['key'] ?? null) === 'category' && $f['match']['any'] === ['EW', 'BR']);

            return $hasCompany && $hasActive && $hasCategory;
        });
    }

    public function test_retrieval_loads_authoritative_text_from_mysql_not_from_index(): void
    {
        $company = Company::create(['name' => 'Authoritative Co']);
        $rule = $this->makeRule($company, ['rule_text' => 'Original text.']);

        RuleChunk::forceCreate(['id' => 1, 'company_rule_id' => $rule->id, 'chunk_index' => 0, 'chunk_text' => 'Original text (indexed).', 'embedding_status' => 'embedded']);

        $this->fakeQdrantSearch([
            ['id' => 1, 'score' => 0.8, 'payload' => ['company_rule_id' => $rule->id, 'category' => 'EW']],
        ]);

        // Rule changes in MySQL after it was indexed -- Qdrant's payload
        // never carried rule_text at all, so this proves the response can
        // only have come from a fresh MySQL read.
        $rule->update(['rule_text' => 'Updated text after indexing.']);

        $result = app(RuleRetrievalService::class)->retrieve($company->id, 'query', [], 5);

        $this->assertEquals('Updated text after indexing.', $result['results'][0]['rule']['rule_text']);
    }

    public function test_empty_query_returns_no_results_without_calling_embedding_provider(): void
    {
        $company = Company::create(['name' => 'Empty Query Co']);
        Http::fake();

        $result = app(RuleRetrievalService::class)->retrieve($company->id, '   ', [], 5);

        $this->assertEquals([], $result['results']);
        Http::assertNothingSent();
    }

    public function test_retrieval_endpoint_requires_authentication_and_company_scoping(): void
    {
        $company = Company::create(['name' => 'Endpoint Co']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $this->fakeQdrantSearch([]);

        $response = $this->actingAs($user)->postJson('/company-rules/retrieve', [
            'query' => 'maximum concurrent projects',
            'top_k' => 5,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['query', 'results']);
    }

    public function test_retrieval_endpoint_rejects_unauthenticated_requests(): void
    {
        $response = $this->postJson('/company-rules/retrieve', ['query' => 'test']);

        $response->assertStatus(401);
    }
}
