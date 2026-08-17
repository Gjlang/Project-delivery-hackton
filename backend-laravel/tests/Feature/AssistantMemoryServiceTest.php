<?php

namespace Tests\Feature;

use App\Models\AssistantMemory;
use App\Models\Company;
use App\Models\User;
use App\Services\Assistant\AssistantMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantMemoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_thread_for_user_creates_exactly_one_thread(): void
    {
        $user = User::factory()->create();
        $service = new AssistantMemoryService;

        $first = $service->ensureThreadForUser($user);
        $second = $service->ensureThreadForUser($user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $user->fresh()->assistantThread()->count());
    }

    public function test_remember_persists_a_valid_fact(): void
    {
        $user = User::factory()->create();
        $service = new AssistantMemoryService;
        $thread = $service->ensureThreadForUser($user);

        $service->remember($thread, 'Prefers concise answers.', 'test');

        $this->assertSame(1, AssistantMemory::where('assistant_thread_id', $thread->id)->count());
    }

    public function test_remember_rejects_empty_or_placeholder_content(): void
    {
        $user = User::factory()->create();
        $service = new AssistantMemoryService;
        $thread = $service->ensureThreadForUser($user);

        $service->remember($thread, '', 'test');
        $service->remember($thread, 'NONE', 'test');
        $service->remember($thread, '   ', 'test');

        $this->assertSame(0, AssistantMemory::where('assistant_thread_id', $thread->id)->count());
    }

    public function test_remember_rejects_a_near_duplicate_of_an_existing_memory(): void
    {
        $user = User::factory()->create();
        $service = new AssistantMemoryService;
        $thread = $service->ensureThreadForUser($user);

        $service->remember($thread, 'Usually builds internal HR tools.', 'test');
        $service->remember($thread, 'Usually builds internal HR tools.', 'test');

        $this->assertSame(1, AssistantMemory::where('assistant_thread_id', $thread->id)->count());
    }

    public function test_recall_ranks_by_keyword_overlap(): void
    {
        $user = User::factory()->create();
        $service = new AssistantMemoryService;
        $thread = $service->ensureThreadForUser($user);

        $service->remember($thread, 'Prefers concise answers over long explanations.', 'test');
        $service->remember($thread, 'Company builds internal HR and payroll systems.', 'test');

        $results = $service->recall($thread, 'what kind of systems does this company build');

        $this->assertNotEmpty($results);
        $this->assertStringContainsString('HR', $results[0]);
    }

    public function test_recall_returns_empty_for_a_thread_with_no_memories(): void
    {
        $user = User::factory()->create();
        $service = new AssistantMemoryService;
        $thread = $service->ensureThreadForUser($user);

        $this->assertSame([], $service->recall($thread, 'anything'));
    }
}
