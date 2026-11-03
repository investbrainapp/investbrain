<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;

class ToastTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['toast'])]
    #[TestWith(['success'])]
    #[TestWith(['warning'])]
    #[TestWith(['error'])]
    #[TestWith(['info'])]
    public function test_toast_methods_cannot_be_called_as_livewire_actions(string $method): void
    {
        $this->actingAs(User::factory()->create());

        $portfolio = Portfolio::factory()->create();

        $this->expectException(MethodNotFoundException::class);

        Livewire::test('share-portfolio-form', ['portfolio' => $portfolio])
            ->call($method, 'Title');
    }
}
