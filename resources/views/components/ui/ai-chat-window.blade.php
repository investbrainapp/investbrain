<?php

use App\Ai\Agents\ChatWithHoldingAgent;
use App\Ai\Agents\ChatWithPortfolioAgent;
use App\Ai\Agents\ChatWithSuggestedPromptsAgent;
use App\Models\ChatWithConversation;
use App\Models\Holding;
use App\Models\Portfolio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Streaming\Events\TextDelta;
use Livewire\Attributes\Async;
use Livewire\Volt\Component;

new class extends Component
{
    // props
    public Model $chatable;

    public array $suggested_prompts = [];

    public array $messages = [];

    public ?string $prompt = null;

    public ?string $answer = null;

    public bool $streaming = false;

    public ?string $agent_conversation_id = null;

    // methods
    public function mount(): void
    {
        $chatWith = ChatWithConversation::firstOrCreate(
            [
                'chatable_type' => $this->chatable::class,
                'chatable_id' => $this->chatable->id,
                'user_id' => auth()->id(),
            ],
            ['title' => 'Chat with investments']
        );

        $this->agent_conversation_id = $chatWith->id;

        $this->messages = $chatWith->messages()
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get(['role', 'content', 'created_at'])
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content, 'created_at' => $m->created_at])
            ->reverse()
            ->values()
            ->toArray();
    }

    public function startCompletion(?string $suggestedPrompt = null): void
    {
        if ($this->streaming) {
            return;
        }

        // prevent spam
        if ($this->isRateLimited()) {
            array_push($this->messages, [
                'role' => 'assistant',
                'content' => __('Hang on! You\'re doing that too much.'),
                'created_at' => now(),
            ]);
            $this->js('scrollChatWindow(250)');

            return;
        }

        if ($suggestedPrompt) {
            $this->prompt = $suggestedPrompt;
            $this->suggested_prompts = [];
        }

        if (empty(trim($this->prompt ?? ''))) {
            $this->resetPrompt();

            array_push($this->messages, ['role' => 'assistant', 'content' => __('Feel free to ask me a question!'), 'created_at' => now()]);
            $this->js('scrollChatWindow(250)');

            return;
        }

        array_push($this->messages, ['role' => 'user', 'content' => $this->prompt, 'created_at' => now()]);
        $this->js('scrollChatWindow(250)');

        $this->resetPrompt();

        $this->streaming = true;
        $this->js('$wire.generateCompletion()');
    }

    public function generateCompletion(): void
    {
        $userPrompt = end($this->messages)['content'] ?? '';

        try {
            $agent = $this->makeAgent()->continue($this->agent_conversation_id, auth()->user());
            $stream = $agent->stream($userPrompt);
        } catch (Exception $e) {
            array_push($this->messages, ['role' => 'assistant', 'content' => $e->getMessage(), 'created_at' => now()]);
            $this->resetPrompt();

            return;
        }

        $this->stream(to: 'answer', content: '', replace: true);

        foreach ($stream as $event) {
            if ($event instanceof TextDelta) {
                $this->stream(to: 'answer', content: $event->delta, replace: false);
                $this->answer .= $event->delta;
            }
            $this->js('scrollChatWindow()');
        }

        array_push($this->messages, ['role' => 'assistant', 'content' => $this->answer, 'created_at' => now()]);

        $this->resetPrompt();
        $this->js('$wire.generateSuggestedPrompts()');
    }

    #[Async]
    public function generateSuggestedPrompts(): void
    {

        try {
            $response = ChatWithSuggestedPromptsAgent::make(messages: array_slice($this->messages, -3))->prompt('');

            $this->suggested_prompts = $response->toArray()['suggested_prompts'] ?? [];
        } catch (Exception $e) {
            $this->suggested_prompts = [];
            $this->error($e->getMessage());
        }
    }

    public function resetPrompt(): void
    {
        $this->answer = null;
        $this->prompt = null;
        $this->streaming = false;
    }

    public function isRateLimited(): bool
    {
        $rateLimitKey = auth()->id().'/'.$this->chatable->id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 20)) {
            return true;
        }

        RateLimiter::hit($rateLimitKey, 60);

        return false;
    }

    private function makeAgent(): Agent
    {
        return match (true) {
            $this->chatable instanceof Portfolio => new ChatWithPortfolioAgent($this->chatable),
            $this->chatable instanceof Holding => new ChatWithHoldingAgent($this->chatable),
        };
    }
}; ?>

<div
    x-data="{
        open: false,
        async scrollChatWindow(delay = 0) {
            await new Promise(resolve => setTimeout(resolve, delay));
            this.$refs.chatWindow.scrollBy({
                top: this.$refs.chatWindow.scrollHeight,
                behavior: 'smooth'
            });
        }
    }"
    class="fixed z-50 bottom-8 right-8"
>
    {{-- toggle button --}}
    <x-ui.button
        x-show="!open"
        @click="$dispatch('toggle-ai-chat')"
        @keyup.escape.window="open = false"
        class="flex btn btn-circle md:btn-lg btn-primary"
    >
        <x-slot:label>
            <x-ui.icon name="o-sparkles" class="w-6 h-6 md:w-8 md:h-8"></x-ui.icon>
        </x-slot:label>
    </x-ui.button>

    {{-- popup --}}
    <div
        x-on:toggle-ai-chat.window="open = !open"
        x-show="open"
        x-trap="open"
        x-bind:inert="!open"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-full"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-full"
        x-cloak
        key="ai-chat"
        class="fixed bg-base-300 shadow-2xl rounded-none md:rounded-lg
                inset-0 h-screen w-full md:inset-auto md:right-6
                md:bottom-6 md:w-[32rem] md:h-[46rem]"
    >
        <div
            class="absolute inset-0 flex flex-col overflow-hidden p-4"
            x-intersect="scrollChatWindow()"
        >
            <div class="flex grow-0 justify-between items-center pb-4 ">
                <h2 class="text-lg text-bold select-none">{{ __('AI Chat') }}</h2>
                <x-ui.button
                    icon="o-x-mark"
                    class="absolute top-5 right-4 btn-ghost btn-circle btn-sm"
                    title="{{ __('Close') }}"
                    @click="open = false"
                />
            </div>

            {{-- chat window --}}
            <div class="grow overflow-hidden overflow-y-scroll ai-chat" x-ref="chatWindow">

                <div class="flex gap-3 mb-5 flex-1">
                    <span class="
                        flex
                        rounded-full
                        w-10 h-10
                        border border-gray-600
                        dark:border-gray-400
                        text-gray-600
                        dark:text-gray-400
                        bg-slate-200
                        dark:bg-slate-800
                    ">
                        <x-ui.icon name="o-sparkles" class="h-auto p-1 w-10" />
                    </span>
                    <p class="leading-relaxed w-full">
                        <span class="block font-bold">AI</span> {{ __('Hi, how can I help?') }}

                    </p>
                </div>

                @foreach($messages as $message)

                    <div class="flex gap-3 mb-5 flex-1">

                    @if ($message['role'] == 'user')
                        
                        <span class="relative flex shrink-0 overflow-hidden rounded-full w-10 h-10">

                            <x-ui.avatar :image="auth()->user()->profile_photo_url" class="!w-10" />

                        </span>
                        <p class="leading-relaxed">
                            <span class="block font-bold" title="{{ $message['created_at'] }}">{{ __('You') }} </span> {{ $message['content'] }}
                        </p>
                        
                    @else
                        
                        <span class="
                            flex
                            rounded-full
                            w-10 h-10
                            border border-gray-600
                            dark:border-gray-400
                            text-gray-600
                            dark:text-gray-400
                            bg-slate-200
                            dark:bg-slate-800
                        ">
                            <x-ui.icon name="o-sparkles" class="h-auto p-1 w-10" />
                        </span>
                        <div class="leading-relaxed" >
                            <span class="block font-bold" title="{{ $message['created_at'] }}">AI </span> {!! Str::markdown($message['content']) !!}
                        </div>
                        
                    @endif

                    </div>

                @endforeach

                @if($streaming)
                    <div class="flex gap-3 mb-10 flex-1">
                        <span class="
                            flex
                            rounded-full
                            w-10 h-10
                            border border-gray-600
                            dark:border-gray-400
                            text-gray-600
                            dark:text-gray-400
                            bg-slate-200
                            dark:bg-slate-800
                        ">
                            <x-ui.icon name="o-sparkles" class="h-auto p-1 w-10" />
                        </span>
                        <p class="leading-relaxed" >
                            <span class="block font-bold ">AI </span> <span wire:stream="answer">{{ $answer }}</span>
                        </p>
                    </div>
                @endif
            </div>

            {{-- prompt input --}}
            <div class="mt-3 grow-0">
                <form wire:submit.prevent>
                    
                    <div class="">
                        @foreach($suggested_prompts as $prompt)
                        <x-ui.button
                            class="btn-xs btn-primary btn-outline mr-1 mb-2"
                            wire:click="startCompletion('{{ addslashes($prompt['value']) }}')"
                        >{{ $prompt['text'] }}</x-ui.button>
                        @endforeach

                    </div>
                    

                    <div class="flex justify-between align-bottom space-x-2 mt-1">

                        <div class="w-full">

                            <x-ui.textarea
                                wire:model="prompt"
                                class="h-18 resize-none bg-base-200"
                                placeholder="{{ __('Have a question? AI might be able to help...') }}"
                                wire:keydown.enter.prevent="startCompletion"
                                autofocus
                                @toggle-ai-chat.window="setTimeout(() => $el.focus(), 250)"
                                x-trap="true"
                            ></x-ui.textarea>
                            
                        </div>
                        <x-ui.button
                            spinner="startCompletion, generateCompletion"
                            wire:click.prevent="startCompletion"
                            class="btn btn-ghost h-32"
                            icon="o-paper-airplane"
                        ></x-ui.button>

                    </div>

                    <div class="w-full mt-2">
                        <p class="text-xs text-secondary leading-tight select-none">{{ __('Advice generated by AI may contain errors. Use at your own risk. Always consult a licensed investment advisor.') }} </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>