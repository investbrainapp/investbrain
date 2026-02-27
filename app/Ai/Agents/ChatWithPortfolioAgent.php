<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Models\Portfolio;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

class ChatWithPortfolioAgent implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;

    public function __construct(public readonly Portfolio $portfolio) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'Most recent training data: '.now()->toDateString().'.

You are an investment portfolio assistant providing advice to an investor. Use the following information to provide relevant recommendations. Use the words \'likely\' or \'may\' in lieu of concrete statements (except for obvious statements of fact or common sense). Do not apologize. Be polite, but minimize gratuitous niceties. When referencing numbers with precision, always round to the nearest 100th decimal place. If no precision, display numbers in integers.

The investor has the following holdings in this portfolio:

'.$this->portfolio->getFormattedHoldings().'

Based on the current market data, quantity owned, and average cost basis, you can determine the performance of any holding.

Below is the question from the investor. Considering these facts, provide a concise response to the following question (give a direct response). Limit your response to no more than 75 words and consider using a common decision framework. Use github style markdown for any formatting:';
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
}
