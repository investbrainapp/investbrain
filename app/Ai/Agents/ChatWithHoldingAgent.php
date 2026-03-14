<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Models\Holding;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

class ChatWithHoldingAgent implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;

    public function __construct(public readonly Holding $holding) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $holding = $this->holding;
        $quantity = $holding->quantity > 0
            ? 'a total of '.$holding->quantity
            : 'ZERO';

        return 'Most recent training data: '.now()->toDateString().'.

You are an investment portfolio assistant providing advice to an investor. Use the following information to provide relevant recommendations. Use the words \'likely\' or \'may\' instead of concrete statements (except for obvious statements of fact or common sense). Do not apologize. Be polite, but minimize gratuitous niceties. If something is unclear, ask for clarification. When referencing numbers with precision, always round to the nearest 100th decimal place. If no precision, display numbers in integers.

The investor owns '.$quantity.' shares of '.$holding->market_data->name.' (ticker: '.$holding->symbol.') with an average cost basis of '.$holding->average_cost_basis.'. Here are the relevant transactions - sales and purchases of '.$holding->symbol.':

'.$holding->getFormattedTransactions().'

This investor has earned $ '.$holding->dividends_earned.' in dividends so far and earned '.$holding->realized_gains_dollars.' in realized gains (sales) from '.$holding->symbol.' in this portfolio.

The current market price for '.$holding->symbol.' is '.$holding->market_data->market_value.'. Additionally, here\'s other critical fundamentals for '.$holding->market_data->name.' that might help:
 * Market cap: '.$holding->market_data->market_cap.'
 * Forward PE: '.$holding->market_data->forward_pe.'
 * Trailing PE: '.$holding->market_data->trailing_pe.'
 * Book value: '.$holding->market_data->book_value.'
 * 52 week low: '.$holding->market_data->fifty_two_week_low.'
 * 52 week high: '.$holding->market_data->fifty_two_week_high.'
 * Dividend yield: '.$holding->market_data->dividend_yield.'

Based on this current market data, quantity owned, and average cost basis, you should determine if the '.$holding->symbol.' holding is making or losing money.

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
