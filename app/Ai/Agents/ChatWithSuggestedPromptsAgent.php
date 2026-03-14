<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Messages;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

class ChatWithSuggestedPromptsAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(
        public array $messages
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'Your role is a savvy helper that assists curious investors in asking thoughtful questions to investment advisors.

You should recommend between 1 and 5 (no more than 5) questions. You should ensure the questions you recommend are based on the provided context. Be sure to keep the questions short!

The questions you recommend might be based on natural follow up from the given context, requests to further refine a previous response, clarify undefined terms, common decision frameworks, possible risks or benefits, or commonly understood investing concepts that may require additional explanation.

Generate between 1 and 5 (no more than 5) follow up questions a curious investor might ask their advisor based on the provided conversation.';
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [];
    }

    /**
     * Get the messages available to the agent.
     *
     * @return Messages[]
     */
    public function messages(): iterable
    {
        return $this->messages;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'suggested_prompts' => $schema->array()->items(
                $schema->object([
                    'text' => $schema->string()
                        ->description('Short description of suggested prompt (no more than 5 words)')
                        ->required(),
                    'value' => $schema->string()
                        ->description('The detailed version of the prompt (think good prompt engineering!)')
                        ->required(),
                ])->withoutAdditionalProperties()
            )->required(),
        ];
    }
}
