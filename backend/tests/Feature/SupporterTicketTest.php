<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionVenue;
use App\Models\EventEdition;
use App\Models\Registration;
use App\Models\SupporterTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupporterTicketTest extends TestCase
{
    use RefreshDatabase;

    private CompetitionVenue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = CompetitionVenue::create([
            'slug' => 'makassar-supporter',
            'name' => 'Kampus BSI Makassar',
            'city' => 'Makassar',
            'address' => 'Jl. A.P. Pettarani, Makassar',
            'activity_start_date' => now()->addDays(10)->toDateString(),
            'activity_end_date' => now()->addDays(11)->toDateString(),
            'is_active' => true,
        ]);

        EventEdition::resolveCurrent()->update([
            'supporter_ticket_price' => 25000,
            'supporter_bank_name' => 'Bank BSI',
            'supporter_bank_account_number' => '7123456789',
            'supporter_bank_account_holder' => 'BSI Flash Makassar',
            'supporter_payment_note' => 'Cantumkan kode tiket pada berita transfer.',
        ]);
    }

    private function participantRegistration(string $schoolName = 'SMAN 1 Makassar'): Registration
    {
        $competition = Competition::create([
            'title' => 'Lomba Supporter Test',
            'slug' => 'lomba-supporter-test',
            'category' => 'Sport Competition',
            'short_description' => 'Kompetisi untuk pengujian tiket supporter.',
            'description' => 'Deskripsi kompetisi.',
            'quota' => 100,
            'fee' => 0,
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addDays(10),
            'event_date' => now()->addDays(20),
            'location' => 'Makassar',
            'requirements' => [],
            'timeline' => [],
        ]);

        return Registration::create([
            'competition_id' => $competition->id,
            'ticket_code' => 'BSIFLASH-SCHOOL1',
            'full_name' => 'Peserta Sekolah',
            'whatsapp' => '081234567890',
            'email' => 'peserta-sekolah@test.id',
            'birth_place' => 'Makassar',
            'birth_date' => '2009-01-01',
            'grade' => 'XI',
            'nisn' => '1234567890',
            'mother_name' => 'Ibu Peserta',
            'school_name' => $schoolName,
            'teacher_name' => 'Guru Pendamping',
            'teacher_contact' => '081298765432',
            'student_card_path' => 'kartu.pdf',
            'delegation_letter_path' => 'delegasi.pdf',
            'photo_path' => 'foto.png',
            'consent' => true,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Supporter Test',
            'grade' => 'XI',
            'school_name' => 'SMAN 1 Makassar',
            'gender' => 'female',
            'email' => 'supporter@test.id',
            'whatsapp' => '081234567891',
            'interested_in_college' => '1',
            'payment_method' => 'cash',
            'competition_venue_id' => $this->venue->id,
        ], $overrides);
    }

    public function test_school_recommendations_come_from_participant_registrations(): void
    {
        $this->participantRegistration();

        $this->getJson('/api/supporter-schools?query=Makassar')
            ->assertOk()
            ->assertExactJson(['SMAN 1 Makassar']);
    }

    public function test_cash_ticket_can_be_created_without_payment_proof(): void
    {
        $this->participantRegistration();

        $payloadWithoutVenue = $this->validPayload();
        unset($payloadWithoutVenue['competition_venue_id']);
        $this->post('/api/supporter-tickets', $payloadWithoutVenue)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('competition_venue_id');

        $response = $this->post('/api/supporter-tickets', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('payment_method', 'cash')
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('ticket_price', '25000.00')
            ->assertJsonPath('venue.name', 'Kampus BSI Makassar');
        $this->assertStringStartsWith('SUPPORTER-', $response->json('ticket_code'));
        $this->assertDatabaseHas('supporter_tickets', [
            'email' => 'supporter@test.id',
            'payment_method' => 'cash',
            'payment_proof_path' => null,
            'ticket_price' => 25000,
            'status' => 'pending',
            'competition_venue_id' => $this->venue->id,
        ]);
    }

    public function test_transfer_ticket_requires_and_stores_payment_proof(): void
    {
        Storage::fake('public');
        $this->participantRegistration();

        $this->post('/api/supporter-tickets', $this->validPayload(['payment_method' => 'transfer']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_proof');

        $response = $this->post('/api/supporter-tickets', $this->validPayload([
            'payment_method' => 'transfer',
            'payment_proof' => UploadedFile::fake()->image('bukti-transfer.jpg'),
        ]));

        $response->assertCreated()->assertJsonPath('payment_method', 'transfer');
        $ticket = SupporterTicket::where('email', 'supporter@test.id')->firstOrFail();
        Storage::disk('public')->assertExists($ticket->payment_proof_path);
    }

    public function test_other_grade_requires_and_stores_supporter_category(): void
    {
        $this->post('/api/supporter-tickets', $this->validPayload(['grade' => 'other']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('supporter_category');

        $response = $this->post('/api/supporter-tickets', $this->validPayload([
            'grade' => 'other',
            'supporter_category' => 'parent',
        ]));

        $response->assertCreated()
            ->assertJsonPath('grade', 'other')
            ->assertJsonPath('supporter_category', 'parent');
        $this->assertDatabaseHas('supporter_tickets', [
            'email' => 'supporter@test.id',
            'grade' => 'other',
            'supporter_category' => 'parent',
        ]);
    }

    public function test_admin_can_update_public_ticket_price_and_transfer_information(): void
    {
        $admin = User::create([
            'name' => 'Admin Pengaturan Tiket',
            'email' => 'admin-settings@test.id',
            'password' => 'password123',
            'role' => 'super_admin',
            'api_token' => hash('sha256', 'admin-settings-token'),
        ]);

        $this->getJson('/api/supporter-ticket-settings')
            ->assertOk()
            ->assertJsonPath('ticket_price', 25000)
            ->assertJsonPath('transfer_enabled', true)
            ->assertJsonPath('bank_name', 'Bank BSI');

        $this->withToken('admin-settings-token')
            ->patchJson('/api/manage/supporter-ticket-settings', [
                'supporter_ticket_price' => 35000,
                'supporter_bank_name' => 'Bank Syariah Indonesia',
                'supporter_bank_account_number' => '7999888777',
                'supporter_bank_account_holder' => 'Panitia BSI Flash',
                'supporter_payment_note' => 'Gunakan kode tiket sebagai berita transfer.',
            ])
            ->assertOk()
            ->assertJsonPath('ticket_price', 35000)
            ->assertJsonPath('bank_account_number', '7999888777')
            ->assertJsonPath('transfer_enabled', true);

        $this->assertDatabaseHas('event_editions', [
            'id' => EventEdition::resolveCurrent()->id,
            'supporter_ticket_price' => 35000,
            'supporter_bank_account_number' => '7999888777',
        ]);
        $this->assertNotNull($admin->id);
    }

    public function test_admin_can_verify_cash_ticket(): void
    {
        $this->participantRegistration();
        $this->post('/api/supporter-tickets', $this->validPayload())->assertCreated();
        $ticket = SupporterTicket::firstOrFail();
        $admin = User::create([
            'name' => 'Admin Ticketing',
            'email' => 'admin-ticketing@test.id',
            'password' => 'password123',
            'role' => 'super_admin',
            'api_token' => hash('sha256', 'admin-ticket-token'),
        ]);

        $this->withToken('admin-ticket-token')
            ->patchJson('/api/manage/supporter-tickets/'.$ticket->id.'/verification', [
                'status' => 'verified',
                'verification_note' => 'Pembayaran cash diterima di lokasi.',
                'competition_venue_id' => $this->venue->id,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('verified_by', $admin->id);

        $this->assertDatabaseHas('supporter_tickets', [
            'id' => $ticket->id,
            'status' => 'verified',
            'verified_by' => $admin->id,
        ]);

        $this->withToken('admin-ticket-token')
            ->getJson('/api/manage/supporter-tickets')
            ->assertOk()
            ->assertJsonPath('summary.verified', 1)
            ->assertJsonPath('summary.sold', 1)
            ->assertJsonPath('summary.verified_revenue', 25000)
            ->assertJsonPath('summary.cash', 1)
            ->assertJsonPath('data.0.ticket_code', $ticket->ticket_code)
            ->assertJsonPath('data.0.venue.name', 'Kampus BSI Makassar');

        $otherVenue = CompetitionVenue::create([
            'slug' => 'bogor-supporter',
            'name' => 'Kampus BSI Bogor',
            'city' => 'Bogor',
            'address' => 'Jl. Merdeka, Bogor',
            'is_active' => true,
        ]);

        $this->withToken('admin-ticket-token')
            ->getJson('/api/manage/supporter-tickets?venue_id='.$otherVenue->id)
            ->assertOk()
            ->assertJsonPath('total', 0);

        $export = $this->withToken('admin-ticket-token')
            ->get('/api/manage/supporter-tickets/export?status=verified&payment_method=cash&venue_id='.$this->venue->id)
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = $export->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $this->assertStringContainsString('Tiket Supporter', $zip->getFromName('xl/workbook.xml'));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Nama Supporter', $sheet);
        $this->assertStringContainsString('Tempat BSI Flash', $sheet);
        $this->assertStringContainsString('Kampus BSI Makassar', $sheet);
        $this->assertStringContainsString('Harga Tiket', $sheet);
        $this->assertStringContainsString('25000', $sheet);
        $this->assertStringContainsString('Supporter Test', $sheet);
        $this->assertStringContainsString('Terverifikasi', $sheet);
        $zip->close();
    }
}
