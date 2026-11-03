<?php

declare(strict_types=1);

namespace Tests;

use App\Models\BackupExport;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_accounts_can_be_deleted(): void
    {

        $this->actingAs($user = User::factory()->create());

        $component = Livewire::test('delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $this->assertNull($user->fresh());
    }

    public function test_deleting_an_account_deletes_the_portfolios_it_owns(): void
    {
        $this->actingAs($user = User::factory()->create());

        $owned = Portfolio::factory()->create();
        $sharedOut = Portfolio::factory()->create();
        $sharedOut->users()->syncWithoutDetaching([User::factory()->create()->id => ['full_access' => true]]);
        Transaction::factory()->buy()->portfolio($owned->id)->symbol('ACME')->create();

        Livewire::test('delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $this->assertModelMissing($owned);
        $this->assertModelMissing($sharedOut);
        $this->assertDatabaseMissing('transactions', ['portfolio_id' => $owned->id]);
        $this->assertDatabaseMissing('holdings', ['portfolio_id' => $owned->id]);
    }

    public function test_deleting_an_account_keeps_portfolios_owned_by_others(): void
    {
        $this->actingAs($owner = User::factory()->create());
        $portfolio = Portfolio::factory()->create();

        $collaborator = User::factory()->create();
        $portfolio->users()->syncWithoutDetaching([$collaborator->id => ['full_access' => true]]);

        $this->actingAs($collaborator);

        Livewire::test('delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $this->assertModelExists($portfolio);
        $this->assertSame($owner->id, $portfolio->fresh()->owner_id);
        $this->assertNull($portfolio->users()->find($collaborator->id));
    }

    public function test_deleting_an_account_removes_its_export_files(): void
    {
        Storage::fake();
        $this->actingAs($user = User::factory()->create());

        $export = BackupExport::create(['user_id' => $user->id]);
        $export->update(['status' => 'success', 'path' => 'exports/backup.xlsx']);
        Storage::disk()->put('exports/backup.xlsx', 'contents');

        Livewire::test('delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $this->assertModelMissing($export);
        Storage::disk()->assertMissing('exports/backup.xlsx');
    }

    public function test_correct_password_must_be_provided_before_account_can_be_deleted(): void
    {

        $this->actingAs($user = User::factory()->create());

        Livewire::test('delete-user-form')
            ->set('password', 'wrong-password')
            ->call('deleteUser')
            ->assertHasErrors(['password']);

        $this->assertNotNull($user->fresh());
    }
}
