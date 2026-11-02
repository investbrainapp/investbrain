<?php

declare(strict_types=1);

namespace Tests;

use App\Exports\BackupExport;
use App\Exports\Sheets\TransactionsSheet as TransactionsExportSheet;
use App\Imports\Sheets\TransactionsSheet;
use App\Interfaces\MarketData\MarketDataInterface;
use App\Models\BackupImport as BackupImportModel;
use App\Models\Holding;
use App\Models\MarketData;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ImportFailedNotification;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\TestWith;

class ImportExportTest extends TestCase
{
    use RefreshDatabase;

    private const TRANSACTION_HEADINGS = ['Transaction ID', 'Symbol', 'Portfolio ID', 'Transaction Type', 'Quantity', 'Cost Basis', 'Sale Price', 'Currency', 'Split', 'Reinvested Dividend', 'Date'];

    /**
     * @var list<string>
     */
    private array $workbooks = [];

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => CurrencySeeder::class, '--force' => true]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->workbooks, 'file_exists'));

        parent::tearDown();
    }

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

    public function test_export_includes_transactions_without_market_data(): void
    {
        $this->actingAs(User::factory()->create());
        $transaction = Transaction::factory()->buy()->lastYear()->symbol('AAPL')->create();
        MarketData::where('symbol', 'AAPL')->delete();

        $exported = (new TransactionsExportSheet)->query()->pluck('id');

        $this->assertSame([$transaction->id], $exported->all());
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

        $portfolio = Portfolio::forceCreate([
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

    public function test_invalid_rows_are_all_reported_and_nothing_is_imported(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $portfolioId = Str::uuid()->toString();

        $import = $this->importWorkbook($user, [
            'Portfolios' => [
                ['Portfolio ID', 'Title'],
                [$portfolioId, 'Brokerage'],
            ],
            'Transactions' => [
                self::TRANSACTION_HEADINGS,
                [null, 'AAPL', $portfolioId, 'BUY', 'ten', 100, null, 'USD', null, null, '2024-01-01'],
                [null, 'AAPL', $portfolioId, 'HOLD', 1, 100, null, 'USD', null, null, '2024-01-02'],
                [null, 'MSFT', $portfolioId, 'BUY', 1, 100, null, 'USD', null, null, '2024-01-03'],
            ],
        ]);

        $this->assertSame('failed', $import->status);
        $this->assertSame('We found 2 problems in your file. Nothing was imported.', $import->message);
        $this->assertSame(2, $import->error_count);
        $this->assertSame([
            ['sheet' => 'Transactions', 'row' => 2, 'column' => 'Quantity', 'value' => 'ten', 'message' => 'This field must be a number.'],
            ['sheet' => 'Transactions', 'row' => 3, 'column' => 'Transaction Type', 'value' => 'HOLD', 'message' => 'This field must be BUY or SELL.'],
        ], $import->errors);

        $this->assertDatabaseMissing('portfolios', ['id' => $portfolioId]);
        $this->assertDatabaseCount('transactions', 0);

        Notification::assertSentTo($user, ImportFailedNotification::class, fn ($notification) => $notification->errorCount === 2);
    }

    public function test_missing_required_column_is_reported_once(): void
    {
        $user = User::factory()->create();

        $import = $this->importWorkbook($user, [
            'Transactions' => [
                ['Symbol', 'Transaction Type', 'Quantity', 'Date'],
                ['AAPL', 'BUY', 1, '2024-01-01'],
                ['AAPL', 'BUY', 2, '2024-01-02'],
            ],
        ]);

        $this->assertSame([
            ['sheet' => 'Transactions', 'row' => null, 'column' => null, 'value' => null, 'message' => 'The Transactions sheet is missing the Portfolio ID column. Check the heading row matches the import template.'],
        ], $import->errors);
    }

    public function test_file_without_any_known_sheets_is_rejected(): void
    {
        $user = User::factory()->create();

        $import = $this->importWorkbook($user, [
            'Sheet1' => [
                self::TRANSACTION_HEADINGS,
                [null, 'AAPL', Str::uuid()->toString(), 'BUY', 1, 100, null, 'USD', null, null, '2024-01-01'],
            ],
        ]);

        $this->assertSame('failed', $import->status);
        $this->assertSame('No data found. Check the sheet names and headings.', $import->errors[0]['message']);
    }

    public function test_currency_defaults_to_the_currency_the_symbol_trades_in(): void
    {
        $user = User::factory()->create();
        $portfolio = $this->portfolioOwnedBy($user);

        $import = $this->importWorkbook($user, [
            'Transactions' => [
                ['Symbol', 'Portfolio ID', 'Transaction Type', 'Quantity', 'Cost Basis', 'Date'],
                ['AAPL', $portfolio->id, 'BUY', 1, 100, '2024-01-01'],
            ],
        ]);

        $this->assertSame('success', $import->status);
        $this->assertSame(100.0, Transaction::sole()->cost_basis_base);
    }

    public function test_unknown_symbol_is_reported_and_no_holding_is_created(): void
    {
        $this->mock(MarketDataInterface::class)
            ->shouldReceive('quote')
            ->with('RDSB')
            ->andThrow(new \Exception('Could not find ticker'));
        $user = User::factory()->create();
        $portfolio = $this->portfolioOwnedBy($user);

        $import = $this->importWorkbook($user, [
            'Transactions' => [
                self::TRANSACTION_HEADINGS,
                [null, 'RDSB', $portfolio->id, 'BUY', 1, 100, null, 'USD', null, null, '2024-01-01'],
            ],
        ]);

        $this->assertSame([
            ['sheet' => 'Transactions', 'row' => 2, 'column' => 'Symbol', 'value' => 'RDSB', 'message' => 'Could not find symbol.'],
        ], $import->errors);
        $this->assertDatabaseCount('holdings', 0);
    }

    public function test_selling_more_shares_than_held_is_rejected(): void
    {
        $user = User::factory()->create();
        $portfolio = $this->portfolioOwnedBy($user);

        $import = $this->importWorkbook($user, [
            'Transactions' => [
                self::TRANSACTION_HEADINGS,
                [null, 'AAPL', $portfolio->id, 'BUY', 5, 100, null, 'USD', null, null, '2024-01-01'],
                [null, 'AAPL', $portfolio->id, 'SELL', 10, null, 120, 'USD', null, null, '2024-02-01'],
            ],
        ]);

        $this->assertSame([
            ['sheet' => 'Transactions', 'row' => 3, 'column' => 'Quantity', 'value' => '10', 'message' => 'You cannot sell 10 AAPL on 2024-02-01 because only 5 are held in that portfolio on that date.'],
        ], $import->errors);
    }

    public function test_sale_on_the_same_day_as_the_purchase_is_accepted(): void
    {
        $user = User::factory()->create();
        $portfolio = $this->portfolioOwnedBy($user);

        $import = $this->importWorkbook($user, [
            'Transactions' => [
                self::TRANSACTION_HEADINGS,
                [null, 'AAPL', $portfolio->id, 'SELL', 5, 100, 120, 'USD', null, null, '2024-01-01'],
                [null, 'AAPL', $portfolio->id, 'BUY', 5, 100, null, 'USD', null, null, '2024-01-01'],
            ],
        ]);

        $this->assertSame('success', $import->status);
    }

    public function test_reverse_split_rows_with_negative_quantity_are_accepted(): void
    {
        $user = User::factory()->create();
        $portfolio = $this->portfolioOwnedBy($user);

        $this->importWorkbook($user, [
            'Transactions' => [
                self::TRANSACTION_HEADINGS,
                [null, 'AAPL', $portfolio->id, 'BUY', 10, 100, null, 'USD', null, null, '2024-01-01'],
                [null, 'AAPL', $portfolio->id, 'BUY', -5, 0, null, 'USD', true, null, '2024-02-01'],
            ],
        ]);

        $this->assertSame(5.0, Holding::sole()->quantity);
    }

    public function test_cannot_import_into_a_portfolio_without_full_access(): void
    {
        [$owner, $sharedPortfolio] = $this->createSharedPortfolioWithTransaction(fullAccess: false);
        $member = $sharedPortfolio->users()->where('owner', false)->first();

        $import = $this->importWorkbook($member, [
            'Transactions' => [
                self::TRANSACTION_HEADINGS,
                [null, 'MSFT', $sharedPortfolio->id, 'BUY', 1, 100, null, 'USD', null, null, '2024-01-01'],
            ],
        ]);

        $this->assertSame('You do not have access to that portfolio.', $import->errors[0]['message']);
        $this->assertSame(0, Transaction::where('symbol', 'MSFT')->count());
    }

    public function test_read_only_member_cannot_hijack_transaction_via_import(): void
    {
        [$owner, $victimPortfolio, $victimTransaction] = $this->createSharedPortfolioWithTransaction(fullAccess: false);
        $attacker = $victimPortfolio->users()->where('owner', false)->first();
        $attackerPortfolio = $this->portfolioOwnedBy($attacker);

        $import = $this->importWorkbook($attacker, [
            'Transactions' => [
                self::TRANSACTION_HEADINGS,
                [$victimTransaction->id, 'HACK', $attackerPortfolio->id, 'BUY', 1, 100, null, 'USD', null, null, '2024-01-01'],
            ],
        ]);

        $this->assertSame('failed', $import->status);
        $this->assertSame(__('You do not have access to that portfolio.'), $import->errors[0]['message']);

        $victimTransaction->refresh();
        $this->assertEquals($victimPortfolio->id, $victimTransaction->portfolio_id);
        $this->assertEquals('AAPL', $victimTransaction->symbol);
    }

    public function test_full_access_member_can_update_shared_transaction_via_import(): void
    {
        [$owner, $sharedPortfolio, $transaction] = $this->createSharedPortfolioWithTransaction(fullAccess: true);
        $member = $sharedPortfolio->users()->where('owner', false)->first();

        $import = $this->importWorkbook($member, [
            'Transactions' => [
                self::TRANSACTION_HEADINGS,
                [$transaction->id, 'MSFT', $sharedPortfolio->id, 'BUY', 1, 100, null, 'USD', null, null, '2024-01-01'],
            ],
        ]);

        $transaction->refresh();
        $this->assertSame('success', $import->status);
        $this->assertEquals($sharedPortfolio->id, $transaction->portfolio_id);
        $this->assertEquals('MSFT', $transaction->symbol);
        $this->assertDatabaseMissing('holdings', ['portfolio_id' => $sharedPortfolio->id, 'symbol' => 'AAPL']);
    }

    public function test_import_with_unknown_transaction_id_inserts_into_own_portfolio(): void
    {
        $user = User::factory()->create();
        $portfolio = $this->portfolioOwnedBy($user);
        $newId = '9e792bb8-94e7-4ed3-b8cc-43b50d34c338';

        $this->importWorkbook($user, [
            'Transactions' => [
                self::TRANSACTION_HEADINGS,
                [$newId, 'AAPL', $portfolio->id, 'BUY', 1, 100, null, 'USD', null, null, '2024-01-01'],
            ],
        ]);

        $this->assertEquals($portfolio->id, Transaction::find($newId)->portfolio_id);
    }

    #[TestWith(["X' OR SLEEP(9) OR '"])]
    #[TestWith(['AAPL; DROP TABLE users'])]
    #[TestWith(['ABCDEFGHIJKLMNOPQRSTUVWXYZ'])]
    #[TestWith(['RDS B'])]
    public function test_import_rejects_malformed_symbols(string $symbol): void
    {
        $validator = Validator::make(['symbol' => $symbol], ['symbol' => (new TransactionsSheet)->rowRules([])['symbol']]);

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
        $validator = Validator::make(['symbol' => $symbol], ['symbol' => (new TransactionsSheet)->rowRules([])['symbol']]);

        $this->assertFalse($validator->fails());
    }

    public function test_unreadable_file_marks_import_as_failed(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        try {
            BackupImportModel::create(['user_id' => $user->id, 'path' => __DIR__.'/does_not_exist.xlsx']);
        } catch (\Throwable) {
            // the sync queue re-throws after calling the job's failed() handler
        }

        $import = BackupImportModel::sole();
        $this->assertSame('failed', $import->status);
        $this->assertSame('Something went wrong while importing your file. Nothing was imported.', $import->message);
    }

    public function test_import_page_lists_problems_with_the_uploaded_file(): void
    {
        $user = User::factory()->create();
        $portfolio = $this->portfolioOwnedBy($user);
        $path = $this->workbook([
            'Transactions' => [
                self::TRANSACTION_HEADINGS,
                [null, 'AAPL', $portfolio->id, 'BUY', 'ten', 100, null, 'USD', null, null, '2024-01-01'],
            ],
        ]);

        Livewire::actingAs($user)
            ->test('import-portfolios-field')
            ->set('file', UploadedFile::fake()->createWithContent('import.xlsx', file_get_contents($path)))
            ->call('import')
            ->assertSee('We found 1 problem in your file. Nothing was imported.')
            ->assertSee('This field must be a number.');
    }

    public function test_import_page_reopens_dialog_when_a_dismissed_import_fails(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $backupImport = BackupImportModel::create(['user_id' => $user->id, 'path' => 'import.xlsx']);

        $component = Livewire::actingAs($user)
            ->test('import-portfolios-field')
            ->assertSet('importStatusDialog', true)
            ->call('closeImportStatus')
            ->assertSet('importStatusDialog', false);

        $this->markImportFailed($backupImport);

        $component->call('checkImportStatus')
            ->assertSet('importStatusDialog', true)
            ->assertSee('This field must be BUY or SELL.');
    }

    public function test_import_page_shows_a_failure_that_happened_while_away_until_dismissed(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->markImportFailed(BackupImportModel::create(['user_id' => $user->id, 'path' => 'import.xlsx']));

        Livewire::actingAs($user)
            ->test('import-portfolios-field')
            ->assertSet('importStatusDialog', true)
            ->assertSee('This field must be BUY or SELL.')
            ->call('closeImportStatus');

        Livewire::actingAs($user)
            ->test('import-portfolios-field')
            ->assertSet('importStatusDialog', false)
            ->assertSet('backupImport', null);
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

        Auth::logout();

        return [$owner, $portfolio, $transaction];
    }

    private function markImportFailed(BackupImportModel $backupImport): void
    {
        $backupImport->update([
            'status' => 'failed',
            'message' => 'We found 1 problem in your file. Nothing was imported.',
            'has_errors' => true,
            'error_count' => 1,
            'errors' => [
                ['sheet' => 'Transactions', 'row' => 2, 'column' => 'Transaction Type', 'value' => 'BUYS', 'message' => 'This field must be BUY or SELL.'],
            ],
        ]);
    }

    private function portfolioOwnedBy(User $user): Portfolio
    {
        Auth::login($user);
        $portfolio = Portfolio::factory()->create();
        Auth::logout();

        return $portfolio;
    }

    /**
     * Imports a workbook the way the queued job does (imports run without an authenticated user).
     *
     * @param  array<string, list<list<mixed>>>  $sheets
     */
    private function importWorkbook(User $user, array $sheets): BackupImportModel
    {
        return BackupImportModel::create([
            'user_id' => $user->id,
            'path' => $this->workbook($sheets),
        ])->refresh();
    }

    /**
     * @param  array<string, list<list<mixed>>>  $sheets  rows per sheet title, headings first
     */
    private function workbook(array $sheets): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $title => $rows) {
            $spreadsheet->createSheet()->setTitle($title)->fromArray($rows, null, 'A1', true);
        }

        $path = sys_get_temp_dir().'/'.Str::uuid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $this->workbooks[] = $path;
    }
}
