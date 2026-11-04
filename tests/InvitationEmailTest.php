<?php

declare(strict_types=1);

namespace Tests;

use App\Models\ConnectedAccount;
use App\Models\Portfolio;
use App\Models\User;
use App\Notifications\InvitedOnboardingNotification;
use App\Notifications\VerifyConnectedAccountNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InvitationEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_markdown_links_in_a_portfolio_title_are_shown_as_text(): void
    {
        $html = $this->renderInvitation(title: '[Confirm your account](https://attacker.example)');

        $this->assertStringNotContainsString('href="https://attacker.example"', $html);
        $this->assertStringContainsString('[Confirm your account](https://attacker.example)', $html);
    }

    public function test_markdown_and_html_in_the_senders_name_are_shown_as_text(): void
    {
        $html = $this->renderInvitation(senderName: '![logo](https://attacker.example/x.png) <b>Jane</b>');

        $this->assertStringNotContainsString('src="https://attacker.example/x.png"', $html);
        $this->assertStringNotContainsString('<b>Jane</b>', $html);
        $this->assertStringContainsString('See you there,<br>![logo](https://attacker.example/x.png) &lt;b&gt;Jane&lt;/b&gt;', $html);
    }

    public function test_invitation_formatting_still_renders(): void
    {
        $html = $this->renderInvitation(title: 'Retirement (2040) & Growth');

        $this->assertMatchesRegularExpression('/<strong[^>]*>Retirement \(2040\) &amp; Growth<\/strong>/', $html);
        $this->assertMatchesRegularExpression('/<a href="[^"]+\/invite\/[^"]+"[^>]*class="button button-primary"/', $html);
        $this->assertStringNotContainsString('\[', $html);
    }

    public function test_connect_account_email_renders_without_markdown_left_over(): void
    {
        config(['services.github.name' => 'GitHub']);
        $user = User::factory()->create();
        $account = ConnectedAccount::forceCreate(['user_id' => $user->id, 'provider' => 'github', 'provider_id' => '1', 'token' => 'token']);

        $html = (string) (new VerifyConnectedAccountNotification($account->id))->toMail($user)->render();

        $this->assertStringContainsString('we recommend changing your password from your profile settings', $html);
        $this->assertStringNotContainsString('](', $html);
    }

    private function renderInvitation(string $title = 'My Portfolio', string $senderName = 'Jane Doe'): string
    {
        $sender = User::factory()->create(['name' => $senderName]);
        $this->actingAs($sender);
        $portfolio = Portfolio::factory()->create(['title' => $title]);

        return (string) (new InvitedOnboardingNotification($portfolio, $sender))->toMail(User::factory()->create())->render();
    }
}
