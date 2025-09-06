<?php

use App\Models\AiChat;
use Illuminate\Database\Eloquent\Model;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component
{
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
                'content' => __('Hang on! You\'re doing that too much.'),
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
        $this->js('$wire.generateCompletion()');
    }

    public function generateCompletion(): void
    {

        try {
            $client = $this->createOpenAiClient();

            $stream = $client->chat()->createStreamed([
                'model' => config('openai.model'),
                'messages' => [
                    ['role' => 'system', 'content' => "Today's date is "
                                                                    .now()->toDateString()
                                                                    .".\n\n".$this->system_prompt],
                    ...array_slice($this->messages, -10),
                ],
            ]);
        } catch (\Exception $e) {

            $this->chatable->chats()->save(new AiChat(['role' => 'assistant', 'content' => $e->getMessage()]));
            array_push($this->messages, ['role' => 'assistant', 'content' => $e->getMessage()]);
            $this->resetPrompt();

            return;
        }

        $this->stream(to: 'answer', content: '', replace: true);

        foreach ($stream as $response) {

            if (! empty($response->choices[0]->delta->content)) {
                $this->stream(to: 'answer', content: $response->choices[0]->delta->content, replace: false);
                $this->answer .= $response->choices[0]->delta->content;
            }
            $this->js('scrollChatWindow()');
        }

        $this->chatable->chats()->save(new AiChat(['role' => 'assistant', 'content' => $this->answer]));
        array_push($this->messages, ['role' => 'assistant', 'content' => $this->answer]);
        $this->resetPrompt();
        $this->js('$wire.generateSuggestedPrompts()');
    }

    public function generateSuggestedPrompts(): void
    {
        try {
            $client = $this->createOpenAiClient();

            $suggested_prompts = $client->chat()->create([
                'model' => config('openai.model'),
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'suggested_prompts_schema',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'suggested_prompts' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'text' => [
                                                'type' => 'string',
                                                'description' => 'The suggested prompt question (no more than 5 words)',
                                            ],
                                            'value' => [
                                                'type' => 'string',
                                                'description' => 'The detailed version of the question',
                                            ],
                                        ],
                                        'required' => ['text', 'value'],
                                        'additionalProperties' => false,
                                    ],
                                ],
                            ],
                            'required' => ['suggested_prompts'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'messages' => [
                    ['role' => 'system', 'content' => '
                        Your role is to assist investors in asking thoughtful questions of their investment advisors. 
                        
                        When you help investors ask good questions, you should ensure the you questions you recommend 
                        are based on the provided context. Be sure to keep the questions short! 

                        The questions you recommend might be based on natural follow up from the given context, requests 
                        to further refine a previous response, clarify undefined terms, common decision frameworks, 
                        possible risks or benefits, or commonly understood investing concepts that may require additional
                        explanation.

                        Your response should only include valid JSON.  
                    '],
                    ['role' => 'user', 'content' => "
                        Generate between 1 and 5 (no more than 5) follow up questions a savvy investor might ask their 
                        advisor based on the following conversation:
                        \n\n
                        ".json_encode(array_slice($this->messages, -4)),
                    ],
                ],
            ]);

            $this->suggested_prompts = json_decode($suggested_prompts->choices[0]->message->content, true)['suggested_prompts'];

        } catch (\Exception $e) {

            $this->suggested_prompts = [];
            $this->error($e->getMessage());

            return;
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

    private function createOpenAiClient()
    {
        $apiKey = config('openai.api_key');
        $organization = config('openai.organization');
        $baseUri = config('openai.base_uri');

        return OpenAI::factory()
            ->withApiKey($apiKey)
            ->withOrganization($organization)
            ->withHttpHeader('OpenAI-Beta', 'assistants=v2')
            ->withHttpClient(new \GuzzleHttp\Client(['timeout' => config('openai.request_timeout', 30)]))
            ->withBaseUri($baseUri)
            ->make();
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
    <x-ib-button 
        x-show="!open"
        @click="$dispatch('toggle-ai-chat')"
        class="flex btn btn-circle md:btn-lg btn-primary" 
    >
        <x-slot:label>
            <x-icon name="o-sparkles" class="w-6 h-6 md:w-8 md:h-8"></x-icon>
        </x-slot:label>
    </x-ib-button>

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
        class="fixed bg-base-100 shadow-2xl rounded-none md:rounded-lg
                inset-0 h-screen w-full 
                md:inset-auto md:right-6 md:bottom-6 md:w-[32rem] md:h-[46rem]"
    >
        <div 
            class="absolute inset-0 flex flex-col overflow-hidden p-4" 
            x-intersect="scrollChatWindow()"
        >
            <div class="flex grow-0 justify-between items-center pb-4 ">
                <h2 class="text-lg text-bold">{{ __('AI Chat') }}</h2>
                <x-ib-button 
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
            <div class="mt-3 grow-0">
                <form submit="startCompletion" >       
                    <div class="">
                        @foreach($suggested_prompts as $prompt)
                        <x-ib-button 
                            class="btn-xs btn-primary btn-outline mr-1 mb-2" 
                            wire:click="startCompletion('{{ addslashes($prompt['value']) }}')" 
                        >{{ $prompt['text'] }}</x-ib-button>
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
                        <x-ib-button
                            spinner="generateCompletion"
                            wire:click="startCompletion"
                            class="btn btn-ghost h-24"
                            icon="o-paper-airplane"
                        ></x-ib-button>
                        
                    </div>
                    
                    <div class="w-full mt-2">
                        <p class="text-xs text-secondary leading-tight">{{ __('Advice generated by AI may contain errors. Use at your own risk. Always consult a licensed investment advisor.') }} </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
