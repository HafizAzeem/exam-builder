<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\SavedPaper;
use App\Models\User;
use App\Support\PaperTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaperTemplatePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_choices_and_details_survive_save_reopen_and_print(): void
    {
        $institution = Institution::create(['name' => 'Template school', 'expiry_date' => now()->addYear()]);
        $owner = User::factory()->create(['institution_id' => $institution->id]);
        $paper = SavedPaper::create([
            'institution_id' => $institution->id, 'user_id' => $owner->id, 'title' => 'Practice',
            'config_snapshot' => ['question_ids' => []],
        ]);
        $this->actingAs($owner);
        foreach (PaperTemplates::IDS as $template) {
            $this->patch(route('editor.update', $paper), ['layout_snapshot' => [
                'header_template' => $template, 'template_meta' => ['batch' => 'Evening', 'version' => 'B'],
                'paper_content' => ['header' => ['institute_name' => 'Edited school'], 'sections' => []],
            ]])->assertRedirect();
            $this->assertSame($template, $paper->fresh()->layout_snapshot['header_template']);
            foreach (['editor.show', 'editor.print'] as $route) {
                $this->get(route($route, $paper))->assertOk()->assertInertia(fn (Assert $page) => $page
                    ->where('preview.layout.header_template', $template)
                    ->where('preview.layout.template_meta.batch', 'Evening')
                    ->where('preview.layout.template_meta.version', 'B')
                    ->where('preview.layout.paper_content.header.institute_name', 'Edited school'));
            }
        }
        $this->get(route('editor.show', $paper))->assertInertia(fn (Assert $page) => $page->where('headerTemplates', PaperTemplates::IDS));
    }
}
