<?php

declare(strict_types=1);

namespace Tests;

use App\Ai\Agents\ChatWithPortfolioAgent;
use App\Ai\Agents\ChatWithSuggestedPromptsAgent;
use App\Models\ChatWithConversation;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Volt\Volt;

class ChatWithTest extends TestCase
{
    use RefreshDatabase;

    public function test_mount_creates_chat_with_conversation_record(): void
    {
        ChatWithPortfolioAgent::fake(['Test response']);
        ChatWithSuggestedPromptsAgent::fake([['suggested_prompts' => []]]);

        $this->actingAs($user = User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        $this->assertDatabaseCount('agent_conversations', 0);

        Volt::test('ui.ai-chat-window', ['chatable' => $portfolio]);

        $this->assertDatabaseHas('agent_conversations', [
            'chatable_type' => Portfolio::class,
            'chatable_id' => $portfolio->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_mount_reuses_existing_chat_with_conversation(): void
    {
        ChatWithPortfolioAgent::fake(['Test response']);
        ChatWithSuggestedPromptsAgent::fake([['suggested_prompts' => []]]);

        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        Volt::test('ui.ai-chat-window', ['chatable' => $portfolio]);
        Volt::test('ui.ai-chat-window', ['chatable' => $portfolio]);

        $this->assertDatabaseCount('agent_conversations', 1);
    }

    public function test_mount_loads_existing_messages(): void
    {
        ChatWithPortfolioAgent::fake(['Test response']);
        ChatWithSuggestedPromptsAgent::fake([['suggested_prompts' => []]]);

        $this->actingAs($user = User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        $conversation = ChatWithConversation::create([
            'chatable_type' => Portfolio::class,
            'chatable_id' => $portfolio->id,
            'user_id' => $user->id,
            'title' => 'Chat with investments',
        ]);

        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'agent' => ChatWithPortfolioAgent::class,
            'role' => 'user',
            'content' => 'Previous question',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $component = Volt::test('ui.ai-chat-window', ['chatable' => $portfolio]);

        $messages = $component->get('messages');
        $this->assertCount(1, $messages);
        $this->assertEquals('user', $messages[0]['role']);
        $this->assertEquals('Previous question', $messages[0]['content']);
    }

    public function test_start_completion_adds_user_message_and_sets_streaming(): void
    {
        ChatWithPortfolioAgent::fake(['The portfolio looks great!']);
        ChatWithSuggestedPromptsAgent::fake([['suggested_prompts' => []]]);

        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        Volt::test('ui.ai-chat-window', ['chatable' => $portfolio])
            ->set('prompt', 'How is my portfolio doing?')
            ->call('startCompletion')
            ->assertSet('streaming', true)
            ->assertSee('How is my portfolio doing?');
    }

    public function test_generate_completion_calls_agent_and_stores_response(): void
    {
        ChatWithPortfolioAgent::fake(['The portfolio looks great!']);
        ChatWithSuggestedPromptsAgent::fake([['suggested_prompts' => []]]);

        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        Volt::test('ui.ai-chat-window', ['chatable' => $portfolio])
            ->set('prompt', 'How is my portfolio doing?')
            ->call('startCompletion')
            ->call('generateCompletion')
            ->assertSet('streaming', false);

        ChatWithPortfolioAgent::assertPrompted('How is my portfolio doing?');

        $this->assertDatabaseHas('agent_conversation_messages', [
            'role' => 'assistant',
            'content' => 'The portfolio looks great!',
        ]);
    }

    public function test_empty_prompt_returns_guidance_without_calling_agent(): void
    {
        ChatWithPortfolioAgent::fake(['Test response']);
        ChatWithSuggestedPromptsAgent::fake([['suggested_prompts' => []]]);

        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        $component = Volt::test('ui.ai-chat-window', ['chatable' => $portfolio])
            ->set('prompt', '   ')
            ->call('startCompletion');

        $lastMessage = collect($component->get('messages'))->last()['content'];
        $this->assertStringContainsString('Feel free to ask me a question!', $lastMessage);
        ChatWithPortfolioAgent::assertNeverPrompted();
    }

    public function test_rate_limiting_blocks_excessive_requests(): void
    {
        ChatWithPortfolioAgent::fake(['Test response']);
        ChatWithSuggestedPromptsAgent::fake([['suggested_prompts' => []]]);

        $this->actingAs($user = User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        for ($i = 0; $i < 20; $i++) {
            RateLimiter::hit($user->id.'/'.$portfolio->id, 60);
        }

        $component = Volt::test('ui.ai-chat-window', ['chatable' => $portfolio])
            ->set('prompt', 'Am I rate limited?')
            ->call('startCompletion');

        $lastMessage = collect($component->get('messages'))->last()['content'];
        $this->assertStringContainsString("Hang on! You're doing that too much.", $lastMessage);
        ChatWithPortfolioAgent::assertNeverPrompted();
    }

    public function test_calling_generate_completion_directly_is_rate_limited(): void
    {
        ChatWithPortfolioAgent::fake(['Test response']);
        ChatWithSuggestedPromptsAgent::fake([['suggested_prompts' => []]]);

        $this->actingAs($user = User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        for ($i = 0; $i < 20; $i++) {
            RateLimiter::hit($user->id.'/'.$portfolio->id, 60);
        }

        $component = Volt::test('ui.ai-chat-window', ['chatable' => $portfolio])
            ->call('generateCompletion')
            ->assertSet('streaming', false);

        $lastMessage = collect($component->get('messages'))->last()['content'];
        $this->assertStringContainsString("Hang on! You're doing that too much.", $lastMessage);
        ChatWithPortfolioAgent::assertNeverPrompted();
    }

    public function test_calling_generate_suggested_prompts_directly_is_rate_limited(): void
    {
        ChatWithPortfolioAgent::fake(['Test response']);
        ChatWithSuggestedPromptsAgent::fake([['suggested_prompts' => []]]);

        $this->actingAs($user = User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        for ($i = 0; $i < 20; $i++) {
            RateLimiter::hit($user->id.'/'.$portfolio->id.'/suggestions', 60);
        }

        Volt::test('ui.ai-chat-window', ['chatable' => $portfolio])
            ->call('generateSuggestedPrompts')
            ->assertSet('suggested_prompts', []);

        ChatWithSuggestedPromptsAgent::assertNeverPrompted();
    }

    public function test_failed_suggested_prompts_show_an_error_instead_of_breaking_the_chat(): void
    {
        ChatWithPortfolioAgent::fake(['Test response']);
        ChatWithSuggestedPromptsAgent::fake(fn () => throw new \RuntimeException('Provider unavailable'));

        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        $component = Volt::test('ui.ai-chat-window', ['chatable' => $portfolio])
            ->call('generateSuggestedPrompts')
            ->assertSet('suggested_prompts', []);

        $this->assertStringContainsString('Provider unavailable', json_encode($component->effects['xjs'] ?? []));
    }

    public function test_suggested_prompt_is_used_as_prompt(): void
    {
        ChatWithPortfolioAgent::fake(['Here is the best holding!']);
        ChatWithSuggestedPromptsAgent::fake([['suggested_prompts' => []]]);

        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        Volt::test('ui.ai-chat-window', ['chatable' => $portfolio])
            ->call('startCompletion', 'Which holding is most successful in this portfolio?')
            ->assertSet('streaming', true)
            ->assertSee('Which holding is most successful in this portfolio?');
    }

    public function test_conversation_cannot_be_switched_to_another_users_from_the_browser(): void
    {
        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();
        $othersConversation = ChatWithConversation::create([
            'chatable_type' => Portfolio::class,
            'chatable_id' => $portfolio->id,
            'user_id' => User::factory()->create()->id,
            'title' => 'Chat with investments',
        ]);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Volt::test('ui.ai-chat-window', ['chatable' => $portfolio])
            ->set('agent_conversation_id', $othersConversation->id);
    }

    public function test_markdown_in_answers_still_renders(): void
    {
        ChatWithPortfolioAgent::fake(["Your **top holding** is AAPL.\n\n- first\n- second\n\n[Read more](https://example.com)"]);
        ChatWithSuggestedPromptsAgent::fake([['suggested_prompts' => []]]);

        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        Volt::test('ui.ai-chat-window', ['chatable' => $portfolio])
            ->set('prompt', 'What is my top holding?')
            ->call('startCompletion')
            ->call('generateCompletion')
            ->assertSeeHtml('<strong>top holding</strong>')
            ->assertSeeHtml('<li>first</li>')
            ->assertSeeHtml('<a href="https://example.com">Read more</a>');
    }

    public function test_html_in_answers_is_shown_as_text(): void
    {
        ChatWithPortfolioAgent::fake(['Hello <b data-test="raw">raw html</b> and [a link](javascript:void(0))']);
        ChatWithSuggestedPromptsAgent::fake([['suggested_prompts' => []]]);

        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        Volt::test('ui.ai-chat-window', ['chatable' => $portfolio])
            ->set('prompt', 'Say hello')
            ->call('startCompletion')
            ->call('generateCompletion')
            ->assertDontSeeHtml('<b data-test="raw">')
            ->assertSeeHtml('&lt;b data-test="raw"&gt;raw html&lt;/b&gt;')
            ->assertDontSeeHtml('href="javascript:');
    }

    public function test_streamed_answer_html_is_escaped(): void
    {
        ChatWithPortfolioAgent::fake(['Hello <b data-test="raw">raw html</b>']);
        ChatWithSuggestedPromptsAgent::fake([['suggested_prompts' => []]]);

        $this->actingAs(User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        $component = Volt::test('ui.ai-chat-window', ['chatable' => $portfolio])
            ->set('prompt', 'Say hello')
            ->call('startCompletion');

        // Livewire writes each streamed chunk to the output as JSON and flushes it, so collect it from the buffer
        $output = '';
        ob_start(function (string $buffer) use (&$output) {
            $output .= $buffer;

            return '';
        });
        $component->call('generateCompletion');
        ob_end_clean();

        preg_match_all('/\{"stream":true.*?"endStream":true\}/', $output, $chunks);
        $streamed = collect($chunks[0])->map(fn ($chunk) => json_decode($chunk, true)['body']['content'])->implode('');

        $this->assertSame(e('Hello <b data-test="raw">raw html</b>'), $streamed);
    }
}
