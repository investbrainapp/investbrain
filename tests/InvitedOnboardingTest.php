<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

class InvitedOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_invited_user_can_set_a_password_from_the_invite_link(): void
    {
        [$portfolio, $invitee] = $this->invite();

        $this->get(URL::signedRoute('invited_onboarding', ['portfolio' => $portfolio->id, 'user' => $invitee->id], now()->addDays(90)))
            ->assertOk()
            ->assertSeeLivewire('invited-onboarding-form');

        Livewire::test('invited-onboarding-form', ['portfolio' => $portfolio, 'user' => $invitee, 'expiresAt' => now()->addDays(90)->timestamp])
            ->set('name', 'Invited Friend')
            ->set('password', 'a-new-password')
            ->set('password_confirmation', 'a-new-password')
            ->call('updateUserInformation')
            ->assertRedirect(route('portfolio.show', ['portfolio' => $portfolio->id]));

        $this->assertTrue(Hash::check('a-new-password', $invitee->fresh()->password));
        $this->assertSame($invitee->id, Auth::id());
    }

    public function test_onboarding_form_cannot_be_replayed_after_the_password_is_set(): void
    {
        [$portfolio, $invitee] = $this->invite();

        $form = Livewire::test('invited-onboarding-form', ['portfolio' => $portfolio, 'user' => $invitee, 'expiresAt' => now()->addDays(90)->timestamp]);

        $invitee->forceFill(['password' => Hash::make('the-invitees-password')])->save();

        $form->set('name', 'Someone Else')
            ->set('password', 'replayed-password')
            ->set('password_confirmation', 'replayed-password')
            ->call('updateUserInformation')
            ->assertForbidden();

        $this->assertTrue(Hash::check('the-invitees-password', $invitee->fresh()->password));
        $this->assertGuest();
    }

    public function test_onboarding_form_cannot_be_used_after_the_invite_link_expires(): void
    {
        [$portfolio, $invitee] = $this->invite();

        $form = Livewire::test('invited-onboarding-form', ['portfolio' => $portfolio, 'user' => $invitee, 'expiresAt' => now()->addDays(90)->timestamp]);

        $this->travel(91)->days();

        $form->set('name', 'Someone Else')
            ->set('password', 'replayed-password')
            ->set('password_confirmation', 'replayed-password')
            ->call('updateUserInformation')
            ->assertForbidden();

        $this->assertNull($invitee->fresh()->password);
        $this->assertGuest();
    }

    public function test_invite_expiry_cannot_be_changed_from_the_browser(): void
    {
        [$portfolio, $invitee] = $this->invite();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test('invited-onboarding-form', ['portfolio' => $portfolio, 'user' => $invitee, 'expiresAt' => now()->subDay()->timestamp])
            ->set('expiresAt', now()->addYear()->timestamp);
    }

    /**
     * @return array{0: Portfolio, 1: User}
     */
    private function invite(): array
    {
        Notification::fake();

        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();
        $portfolio->share('friend@example.net');
        Auth::logout();

        return [$portfolio, User::where('email', 'friend@example.net')->firstOrFail()];
    }
}
