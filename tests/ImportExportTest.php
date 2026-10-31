<?php

declare(strict_types=1);

namespace Tests;

use App\Exports\BackupExport;
use App\Imports\Sheets\TransactionsSheet;
use App\Models\BackupImport as BackupImportModel;
use App\Models\Holding;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\TestWith;

class ImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_exports(): void
    {
        Excel::fake();

        $this->actingAs($user = User::factory()->create());

        Transaction::factory(5)->buy()->lastYear()->symbol('AAPL')->create();

        Excel::download(new BackupExport, now()->format('Y_m_d').'_investbrain_backup.xlsx');

        Excel::assertDownloaded(now()->format('Y_m_d').'_investbrain_backup.xlsx', function (BackupExport $export) {
            return true;
        });
    }

    public function test_import_job_completes(): void
    {
        $this->actingAs($user = User::factory()->create());

        $import_job = BackupImportModel::create([
            'user_id' => auth()->user()->id,
            'path' => __DIR__.'/0000_00_00_import_test.xlsx',
        ]);

        $import_job->refresh();

        $this->assertEquals('success', $import_job->status);
    }

    public function test_import_job_inserts_rows(): void
    {
        $this->actingAs($user = User::factory()->create());

        BackupImportModel::create([
            'user_id' => auth()->user()->id,
            'path' => __DIR__.'/0000_00_00_import_test.xlsx',
        ]);

        $this->assertEquals(3, $user->transactions->count());
    }

    public function test_import_job_calculates_correct_holding_data(): void
    {
        $this->actingAs($user = User::factory()->create());

        BackupImportModel::create([
            'user_id' => auth()->user()->id,
            'path' => __DIR__.'/0000_00_00_import_test.xlsx',
        ]);

        $holding = $user->holdings->first();

        $this->assertEquals('AAPL', $holding->symbol);
        $this->assertEquals(6, $holding->quantity);
        $this->assertEqualsWithDelta(233.33, $holding->average_cost_basis, 0.01);
    }

    public function test_configurations_are_set_on_import(): void
    {
        $this->actingAs($user = User::factory()->create());

        $portfolio = Portfolio::create([
            'id' => '9e792bb8-94e7-4ed3-b8cc-43b50d34c337',
            'title' => 'Test Portfolio',
        ]);

        $holding = Holding::create([
            'portfolio_id' => $portfolio->id,
            'symbol' => 'ACME',
            'quantity' => 0,
            'reinvest_dividends' => false,
        ]);

        Transaction::factory()->buy()->lastYear()->costBasis(100)->portfolio($portfolio->id)->symbol('AAPL')->create();

        $this->assertEquals(false, $holding->reinvest_dividends);

        BackupImportModel::create([
            'user_id' => auth()->user()->id,
            'path' => __DIR__.'/0000_00_00_import_configs_test.xlsx',
        ]);

        $holding->refresh();

        $this->assertEquals(true, $holding->reinvest_dividends);
    }

    public function test_read_only_member_cannot_hijack_transaction_via_import(): void
    {
        [$owner, $victimPortfolio, $victimTransaction] = $this->createSharedPortfolioWithTransaction(fullAccess: false);

        $attacker = $victimPortfolio->users()->where('owner', false)->first();
        Auth::login($attacker);
        $attackerPortfolio = Portfolio::factory()->create();

        try {
            $this->makeTransactionsSheet($attacker)->collection(collect([
                $this->transactionRow($victimTransaction->id, $attackerPortfolio->id, 'HACK'),
            ]));

            $this->fail('Import should have been rejected.');
        } catch (\Exception $e) {
            $this->assertEquals(__('You do not have access to that portfolio.'), $e->getMessage());
        }

        $victimTransaction->refresh();

        $this->assertEquals($victimPortfolio->id, $victimTransaction->portfolio_id);
        $this->assertEquals('AAPL', $victimTransaction->symbol);
    }

    public function test_full_access_member_can_update_shared_transaction_via_import(): void
    {
        [$owner, $sharedPortfolio, $transaction] = $this->createSharedPortfolioWithTransaction(fullAccess: true);

        $member = $sharedPortfolio->users()->where('owner', false)->first();

        $this->makeTransactionsSheet($member)->collection(collect([
            $this->transactionRow($transaction->id, $sharedPortfolio->id, 'MSFT'),
        ]));

        $transaction->refresh();

        $this->assertEquals($sharedPortfolio->id, $transaction->portfolio_id);
        $this->assertEquals('MSFT', $transaction->symbol);
    }

    public function test_import_with_unknown_transaction_id_inserts_into_own_portfolio(): void
    {
        $this->actingAs($user = User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        $newId = '9e792bb8-94e7-4ed3-b8cc-43b50d34c338';

        $this->makeTransactionsSheet($user)->collection(collect([
            $this->transactionRow($newId, $portfolio->id, 'AAPL'),
        ]));

        $this->assertEquals($portfolio->id, Transaction::find($newId)->portfolio_id);
    }

    #[TestWith(["X' OR SLEEP(9) OR '"])]
    #[TestWith(['AAPL; DROP TABLE users'])]
    #[TestWith(['ABCDEFGHIJKLMNOPQRSTUVWXYZ'])]
    public function test_import_rejects_malformed_symbols(string $symbol): void
    {
        $sheet = $this->makeTransactionsSheet(User::factory()->create());

        $validator = Validator::make(['symbol' => $symbol], ['symbol' => $sheet->rules()['symbol']]);

        $this->assertTrue($validator->fails());
    }

    #[TestWith(['AAPL'])]
    #[TestWith(['BRK.B'])]
    #[TestWith(['BTC-USD'])]
    #[TestWith(['BTC/USD'])]
    #[TestWith(['^GSPC'])]
    #[TestWith(['EURUSD=X'])]
    public function test_import_accepts_common_symbol_formats(string $symbol): void
    {
        $sheet = $this->makeTransactionsSheet(User::factory()->create());

        $validator = Validator::make(['symbol' => $symbol], ['symbol' => $sheet->rules()['symbol']]);

        $this->assertFalse($validator->fails());
    }

    /**
     * @return array{0: User, 1: Portfolio, 2: Transaction}
     */
    private function createSharedPortfolioWithTransaction(bool $fullAccess): array
    {
        $owner = User::factory()->create();
        Auth::login($owner);

        $portfolio = Portfolio::factory()->create();
        $transaction = Transaction::factory()->buy()->lastYear()->portfolio($portfolio->id)->symbol('AAPL')->create();

        $portfolio->users()->syncWithoutDetaching([
            User::factory()->create()->id => [
                'full_access' => $fullAccess,
                'owner' => false,
            ],
        ]);

        return [$owner, $portfolio, $transaction];
    }

    private function makeTransactionsSheet(User $user): TransactionsSheet
    {
        $backupImport = BackupImportModel::withoutEvents(fn () => BackupImportModel::create([
            'user_id' => $user->id,
            'path' => __DIR__.'/0000_00_00_import_test.xlsx',
            'status' => 'in_progress',
            'message' => '',
        ]));

        return new TransactionsSheet($backupImport);
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionRow(string $transactionId, string $portfolioId, string $symbol): array
    {
        return [
            'transaction_id' => $transactionId,
            'portfolio_id' => $portfolioId,
            'symbol' => $symbol,
            'transaction_type' => 'BUY',
            'quantity' => 1,
            'currency' => config('investbrain.base_currency'),
            'date' => '2024-01-01',
            'cost_basis' => 100,
            'sale_price' => null,
            'split' => false,
            'reinvested_dividend' => false,
        ];
    }
}
