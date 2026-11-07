<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Holding;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Spotlight
{
    /**
     * Maximum number of results returned per result type.
     */
    private const int RESULTS_PER_TYPE = 5;

    /**
     * Character used to escape LIKE wildcards. Avoids backslash, whose literal syntax differs between databases.
     */
    private const string LIKE_ESCAPE = '!';

    /**
     * Search the authenticated user's portfolios and holdings.
     *
     * Every word in the search must appear somewhere in the result (case-insensitive, partial matches allowed).
     * Results are ranked by how closely they match: exact, then prefix, then word prefix, then anywhere.
     *
     * @return Collection<int, array{name: string, description: ?string, badge: array{label: string, class: string}|null, link: string, avatar: null}>
     */
    public function search(Request $request): Collection
    {
        $user = $request->user();
        $search = Str::squish((string) $request->input('search', ''));

        if (! $user || $search === '') {
            return collect();
        }

        $search = mb_strtolower($search);

        return $this->searchPortfolios($user, $search)
            ->concat($this->searchHoldings($user, $search))
            ->values();
    }

    /**
     * @return Collection<int, array{name: string, description: ?string, badge: array{label: string, class: string}|null, link: string, avatar: null}>
     */
    private function searchPortfolios(User $user, string $search): Collection
    {
        $query = $user->portfolios()->select('portfolios.id', 'portfolios.title');

        foreach (explode(' ', $search) as $word) {
            $this->whereContains($query, 'portfolios.title', $word);
        }

        $this->orderByMatchQuality($query, $search, exactAndPrefixColumn: 'portfolios.title', wordColumn: 'portfolios.title');

        return $query->orderBy('portfolios.title')
            ->limit(self::RESULTS_PER_TYPE)
            ->get()
            ->map(fn (Portfolio $portfolio): array => [
                'name' => 'Portfolio: '.$portfolio->title,
                'description' => null,
                'badge' => null,
                'link' => route('portfolio.show', ['portfolio' => $portfolio->id]),
                'avatar' => null,
            ]);
    }

    /**
     * Includes closed positions and wishlist holdings, ranked below open positions that match equally well.
     *
     * @return Collection<int, array{name: string, description: ?string, badge: array{label: string, class: string}|null, link: string, avatar: null}>
     */
    private function searchHoldings(User $user, string $search): Collection
    {
        $query = Holding::query()
            ->select('holdings.id', 'holdings.portfolio_id', 'holdings.symbol', 'holdings.quantity', 'market_data.name as market_data_name')
            ->leftJoin('market_data', 'holdings.symbol', 'market_data.symbol')
            ->whereIn('holdings.portfolio_id', $user->portfolios()->select('portfolios.id'))
            ->with('portfolio:id,title,wishlist');

        foreach (explode(' ', $search) as $word) {
            $query->where(function (Builder $query) use ($word): void {
                $this->whereContains($query, 'holdings.symbol', $word);
                $this->whereContains($query, 'market_data.name', $word, boolean: 'or');
            });
        }

        $this->orderByMatchQuality($query, $search, exactAndPrefixColumn: 'holdings.symbol', wordColumn: 'market_data.name');

        return $query->orderByRaw('CASE WHEN holdings.quantity > 0 THEN 0 ELSE 1 END')
            ->orderBy('holdings.symbol')
            ->limit(self::RESULTS_PER_TYPE)
            ->get()
            ->map(fn (Holding $holding): array => [
                'name' => 'Holding: '.($holding->market_data_name ?? $holding->symbol).' ('.$holding->symbol.')',
                'description' => $holding->portfolio->title,
                'badge' => $this->holdingBadge($holding),
                'link' => route('holding.show', ['portfolio' => $holding->portfolio_id, 'symbol' => $holding->symbol]),
                'avatar' => null,
            ]);
    }

    /**
     * @return array{label: string, class: string}|null
     */
    private function holdingBadge(Holding $holding): ?array
    {
        return match (true) {
            $holding->portfolio->wishlist => ['label' => __('Wishlist'), 'class' => 'badge-secondary'],
            $holding->quantity <= 0 => ['label' => __('Closed'), 'class' => 'badge-error'],
            default => null,
        };
    }

    /**
     * Case-insensitive "column contains value" that treats % and _ in the value literally.
     */
    private function whereContains(QueryBuilder $query, string $column, string $value, string $boolean = 'and'): void
    {
        $query->whereRaw(
            "LOWER({$column}) LIKE ? ESCAPE '".self::LIKE_ESCAPE."'",
            ['%'.$this->escapeLike($value).'%'],
            $boolean,
        );
    }

    /**
     * Rank: exact match on $exactAndPrefixColumn, then prefix of it, then a word in $wordColumn starting with the
     * search, then anything else that matched.
     */
    private function orderByMatchQuality(QueryBuilder $query, string $search, string $exactAndPrefixColumn, string $wordColumn): void
    {
        $escape = "ESCAPE '".self::LIKE_ESCAPE."'";
        $escaped = $this->escapeLike($search);

        $query->orderByRaw(
            "CASE
                WHEN LOWER({$exactAndPrefixColumn}) = ? THEN 0
                WHEN LOWER({$exactAndPrefixColumn}) LIKE ? {$escape} THEN 1
                WHEN LOWER({$wordColumn}) LIKE ? {$escape} OR LOWER({$wordColumn}) LIKE ? {$escape} THEN 2
                ELSE 3
            END",
            [$search, $escaped.'%', $escaped.'%', '% '.$escaped.'%'],
        );
    }

    private function escapeLike(string $value): string
    {
        return str_replace(
            [self::LIKE_ESCAPE, '%', '_'],
            [self::LIKE_ESCAPE.self::LIKE_ESCAPE, self::LIKE_ESCAPE.'%', self::LIKE_ESCAPE.'_'],
            $value,
        );
    }
}
