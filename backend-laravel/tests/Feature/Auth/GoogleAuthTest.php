<?php

namespace Tests\Feature\Auth;

use App\Models\AssistantThread;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(string $id, string $email, string $name = 'Test User'): SocialiteUser
    {
        $user = new SocialiteUser;
        $user->id = $id;
        $user->email = $email;
        $user->name = $name;
        $user->avatar = 'https://example.com/avatar.png';

        return $user;
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Continue with Google');
    }

    public function test_new_google_user_is_created_and_logged_in(): void
    {
        Socialite::shouldReceive('driver->user')->andReturn($this->fakeGoogleUser('google-123', 'new@example.com'));

        $response = $this->get('/auth/google/callback');

        $this->assertAuthenticated();
        $user = User::where('email', 'new@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);

        // No company rules exist yet in a fresh test database, so a brand
        // new user lands on the merged Create-Project workspace (its
        // "Company Rules" tab is where they'd upload rules first).
        $response->assertRedirect(route('projects.new'));
    }

    public function test_existing_user_matched_by_email_gets_google_id_attached_instead_of_duplicated(): void
    {
        $existing = User::factory()->create(['email' => 'seeded@example.com', 'google_id' => null]);

        Socialite::shouldReceive('driver->user')->andReturn($this->fakeGoogleUser('google-456', 'seeded@example.com'));

        $this->get('/auth/google/callback');

        $this->assertSame(1, User::where('email', 'seeded@example.com')->count());
        $this->assertSame('google-456', $existing->fresh()->google_id);
    }

    public function test_a_thread_is_created_on_first_login(): void
    {
        Socialite::shouldReceive('driver->user')->andReturn($this->fakeGoogleUser('google-789', 'thread@example.com'));

        $this->get('/auth/google/callback');

        $user = User::where('email', 'thread@example.com')->first();
        $this->assertTrue(AssistantThread::where('user_id', $user->id)->exists());
    }

    public function test_user_lands_on_dashboard_once_company_rules_exist(): void
    {
        $company = Company::create(['name' => 'Test Co']);
        $user = User::factory()->create(['email' => 'member@example.com', 'google_id' => 'google-999', 'company_id' => $company->id]);
        \App\Models\BusinessRule::create(['created_by' => $user->id, 'rule_code' => 'BR-001', 'title' => 'Seeded Rule', 'rule_text' => 'x']);

        Socialite::shouldReceive('driver->user')->andReturn($this->fakeGoogleUser('google-999', 'member@example.com'));

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
