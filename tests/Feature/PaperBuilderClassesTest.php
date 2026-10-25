<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\GradeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaperBuilderClassesTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_shows_all_twelve_classes_in_descending_order(): void
    {
        $this->seed(GradeSeeder::class);

        $this->actingAs(User::factory()->create())
            ->get(route('builder'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PaperBuilder/Classes')
                ->has('grades', 12)
                ->where('grades.0.number', 12)
                ->where('grades.8.number', 4)
                ->where('grades.9.number', 3)
                ->where('grades.10.number', 2)
                ->where('grades.11.number', 1)
                ->where('grades.11.active', true));
    }
}
