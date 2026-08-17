<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $company = Company::create(['name' => 'Test Co']);

        return User::factory()->create(['company_id' => $company->id]);
    }

    public function test_profile_page_is_displayed(): void
    {
        $response = $this
            ->actingAs($this->makeUser())
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_name_can_be_updated(): void
    {
        $user = $this->makeUser();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('Test User', $user->refresh()->name);
    }

    public function test_email_is_not_editable_from_the_profile_form(): void
    {
        $user = $this->makeUser();
        $originalEmail = $user->email;

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Test User',
            'email' => 'someone-else@example.com',
        ]);

        $this->assertSame($originalEmail, $user->refresh()->email);
    }

    public function test_user_can_delete_their_account_without_a_password(): void
    {
        $user = $this->makeUser();

        $response = $this
            ->actingAs($user)
            ->delete('/profile');

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }
}
