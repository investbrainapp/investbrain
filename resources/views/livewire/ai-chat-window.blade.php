<?php

use Mary\Traits\Toast;
use App\Models\AiChat;
use App\Models\Holding;
use Illuminate\Database\Eloquent\Model;
use Livewire\Volt\Component;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\StreamResponse;

new class extends Component {

    use Toast;

    // props
    public Model $chatable;
    public string $system_prompt = 'You are an investment portfolio assistant providing advice to an investor.  Use the following information to provide relevant recommendations.  Use the words \'likely\' or \'may\' instead of concrete statements (except for obvious statements of fact or common sense). Use github style markdown for any formatting.';
    public array $suggested_prompts = [];

    public array $messages = [];
    public ?string $prompt = null;
    public ?string $answer = null;
    public bool $streaming = false;
    
    // methods
    public function mount()
    {
        $this->messages = $this->chatable->chats()->orderByRaw('created_at, id')->limit(25)->get(['role', 'content'])->toArray();
    }

    public function startCompletion($suggestedPrompt = null)
    {
        // prevent spam
        if ($this->isRateLimited() || $this->streaming) {
            array_push($this->messages, [
                'role' => 'assistant', 
                'content' => __('Hang on! You\'re doing that too much.')
            ]);
            $this->js('scrollChatWindow(250)');
            return;
        }

        if ($suggestedPrompt) {
            $this->prompt = $suggestedPrompt;
        }

        if (empty(trim($this->prompt))) {
            $this->resetPrompt();

            array_push($this->messages, ['role' => 'assistant', 'content' => __('Feel free to ask me a question!')]);
            $this->js('scrollChatWindow(250)');

            return;
        } 

        $this->chatable->chats()->save(new AiChat(['role' => 'user', 'content' => $this->prompt]));
        array_push($this->messages, ['role' => 'user', 'content' => $this->prompt]);
        $this->js('scrollChatWindow(250)');

        $this->resetPrompt();

        $this->streaming = true;
        $this->js('$wire.generate()');
    }

    public function generate(): void
    {
    
        try {
            $stream = OpenAI::chat()->createStreamed([
                'model' => config('openai.model'),
                'messages' => [
                    ['role' => 'system', 'content' => $this->system_prompt],
                    ...array_slice($this->messages, -10)
                ],
            ]);
        } catch (\Exception $e) {

            $this->chatable->chats()->save(new AiChat(['role' => 'assistant', 'content' => $e->getMessage()]));
            array_push($this->messages, ['role' => 'assistant', 'content' => $e->getMessage()]);
            $this->resetPrompt();
            return;
        }

        $this->stream(to: "answer", content: '', replace: true);
        
        foreach($stream as $response){
            
            if(!empty($response->choices[0]->delta->content)) {
                $this->stream(to: 'answer', content: $response->choices[0]->delta->content, replace: false);
                $this->answer .= $response->choices[0]->delta->content;
            }
            $this->js('scrollChatWindow()');
        }

        $this->chatable->chats()->save(new AiChat(['role' => 'assistant', 'content' => $this->answer]));
        array_push($this->messages, ['role' => 'assistant', 'content' => $this->answer]);
        $this->resetPrompt();
    }

    public function resetPrompt(): void
    {
        $this->answer = null;
        $this->prompt = null;
        $this->streaming = false;
    }

    public function isRateLimited(): bool
    {
        $rateLimitKey = auth()->id() . '/' . $this->chatable->id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 20)) {
            
            return true;
        }

        RateLimiter::hit($rateLimitKey, 60);

        return false;
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
    class=""
>
    <x-button 
        @click="$dispatch('toggle-ai-chat')"
        class="btn btn-circle btn-lg btn-primary fixed bottom-10 right-10" 
    >
        <x-slot:label>
            <x-icon name="o-sparkles" class="w-8 h-8"></x-icon>
        </x-slot:label>
    </x-button>

    <div 
        x-on:toggle-ai-chat.window="open = !open"
        x-show="open"
        x-trap="open" 
        x-bind:inert="!open"
        x-transition.opacity
        x-cloak
        key="ai-chat" 
        class="fixed 
            bottom-0 right-0 w-full h-screen
            md:bottom-[7rem] md:right-10 md:w-[35rem] md:h-auto" 
    >
    
        <x-card class="h-screen md:h-auto shadow-2xl" title="{{ __('AI Chat') }}" x-intersect="scrollChatWindow()">
            {{-- close button --}}
            <x-button 
                icon="o-x-mark" 
                class="absolute top-5 right-4 btn-ghost btn-circle btn-sm" 
                title="{{ __('Close') }}"
                @click="open = false" 
            />

            {{-- chat window --}}
            <div class="h-[25rem] overflow-y-scroll ai-chat" x-ref="chatWindow">

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
                        <x-icon name="o-sparkles" class="h-auto p-1 w-10" />
                    </span>
                    <p class="leading-relaxed w-full">
                        <span class="block font-bold">AI</span> {{ __('Hi, how can I help?') }}
                        
                    </p>
                </div>
        
                @foreach($messages as $message) 

                    @if ($message['role'] == 'user')
                        <div class="flex gap-3 mb-5 flex-1">
                            <span class="relative flex shrink-0 overflow-hidden rounded-full w-10 h-10">
                
                                <x-avatar :image="auth()->user()->profile_photo_url" class="!w-10" />
                
                            </span>
                            <p class="leading-relaxed">
                                <span class="block font-bold ">{{ __('You') }} </span> {{ $message['content'] }}
                            </p>
                        </div>
        
                    @else
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
                                <x-icon name="o-sparkles" class="h-auto p-1 w-10" />
                            </span>
                            <div class="leading-relaxed" >
                                <span class="block font-bold ">AI </span> {!! Str::markdown($message['content']) !!}
                            </div>
                        </div>
                    @endif
        
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
                            <x-icon name="o-sparkles" class="h-auto p-1 w-10" />
                        </span>
                        <p class="leading-relaxed" >
                            <span class="block font-bold ">AI </span> <span wire:stream="answer">{{ $answer }}</span>
                        </p>
                    </div>
                @endif
            </div>
        
            {{-- prompt input --}}
            <form submit="startCompletion" class="mt-3">       
                <div class="">
                    @foreach($suggested_prompts as $prompt)
                    <x-button 
                        class="btn-xs btn-primary btn-outline mr-1 mb-2" 
                        label="{{ $prompt['text'] }}" 
                        wire:click="startCompletion('{{ $prompt['value'] }}')" 
                    />
                    @endforeach
                    
                </div>
            
                <div class="flex justify-between align-bottom space-x-2 mt-1">
                    
                    <div class="w-full">
                        
                        <x-textarea
                            wire:model="prompt"
                            class="h-24 resize-none "
                            placeholder="{{ __('Have a question? AI might be able to help...') }}"
                            wire:keydown.enter.prevent="startCompletion"
                            autofocus
                        ></x-textarea>
                    </div>
                    <x-button
                        spinner="generate"
                        wire:click="startCompletion"
                        class="btn btn-ghost h-24"
                        icon="o-paper-airplane"
                    ></x-button>
                    
                </div>
                
                <div class="w-full mt-2">
                    <p class="text-xs text-secondary leading-tight">{{ __('Advice generated by AI may contain errors. Use at your own risk. Always consult a licensed investment advisor.') }} </p>
                </div>
            </form>
        </x-card>
    </div>
</div>
