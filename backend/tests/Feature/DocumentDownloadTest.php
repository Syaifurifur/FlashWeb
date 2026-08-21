<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\EventEdition;
use App\Models\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_download_a_general_document_with_its_original_name(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site-content/general-documents/panduan.docx', 'word-document-content');

        SiteContent::create([
            'event_edition_id' => EventEdition::resolveCurrent(true)->id,
            'key' => 'general_documents',
            'content' => ['documents' => [[
                'title' => 'Panduan Peserta',
                'description' => 'Panduan umum.',
                'file_path' => 'site-content/general-documents/panduan.docx',
                'original_name' => 'Panduan Peserta.docx',
            ]]],
        ]);

        $this->get('/api/content/general-documents/0/download')
            ->assertOk()
            ->assertDownload('Panduan Peserta.docx')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertStreamedContent('word-document-content');
    }

    public function test_public_can_download_a_document_for_the_active_competition(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('competitions/1/downloads/template.xlsx', 'spreadsheet-content');

        $competition = Competition::create([
            'title' => 'Olimpiade Test',
            'slug' => 'olimpiade-test',
            'category' => 'Science Competition',
            'short_description' => 'Kompetisi untuk pengujian.',
            'description' => 'Deskripsi lengkap.',
            'quota' => 100,
            'fee' => 0,
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addDays(10),
            'event_date' => now()->addDays(20),
            'location' => 'Online',
            'requirements' => [],
            'timeline' => [],
            'downloadable_documents' => [[
                'title' => 'Template Nilai',
                'description' => '',
                'file_path' => 'competitions/1/downloads/template.xlsx',
                'original_name' => 'Template Nilai.xlsx',
            ]],
        ]);

        $this->get('/api/competitions/'.$competition->slug.'/documents/0/download')
            ->assertOk()
            ->assertDownload('Template Nilai.xlsx')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertStreamedContent('spreadsheet-content');
    }

    public function test_download_returns_not_found_instead_of_an_html_fallback_when_file_is_missing(): void
    {
        Storage::fake('public');

        SiteContent::create([
            'event_edition_id' => EventEdition::resolveCurrent(true)->id,
            'key' => 'general_documents',
            'content' => ['documents' => [[
                'title' => 'File Hilang',
                'file_path' => 'site-content/general-documents/missing.pdf',
                'original_name' => 'File Hilang.pdf',
            ]]],
        ]);

        $this->getJson('/api/content/general-documents/0/download')
            ->assertNotFound()
            ->assertJsonPath('message', 'Dokumen tidak ditemukan atau file sudah tidak tersedia.');
    }
}
