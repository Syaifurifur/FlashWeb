<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionVenue;
use App\Models\EventEdition;
use App\Models\Registration;
use App\Models\SiteContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnualEditionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_year_clones_setup_but_keeps_operational_data_separate(): void
    {
        $source = EventEdition::where('year', 2027)->firstOrFail();
        User::create([
            'name'=>'Admin Tahunan', 'email'=>'annual-admin@test.id', 'password'=>'password123',
            'role'=>'super_admin', 'api_token'=>hash('sha256', 'annual-admin-token'),
        ]);
        $venue = CompetitionVenue::create([
            'event_edition_id'=>$source->id, 'name'=>'BSI Sport Center', 'city'=>'Bogor',
            'slug'=>'bogor', 'address'=>'Kampus Bogor', 'activity_start_date'=>'2027-09-03',
            'activity_end_date'=>'2027-09-09', 'field_photo_url'=>'https://example.test/bogor.jpg', 'is_active'=>true,
        ]);
        $competition = Competition::create([
            'event_edition_id'=>$source->id, 'title'=>'Futsal Putra', 'slug'=>'futsal-putra-2027',
            'category'=>'Sport Competition', 'short_description'=>'Lomba futsal tahunan.',
            'description'=>'Deskripsi lomba futsal.', 'quota'=>32, 'fee'=>200000,
            'registration_start'=>'2027-07-01', 'registration_end'=>'2027-08-20',
            'event_date'=>'2027-09-09', 'location'=>'Bogor', 'requirements'=>[],
            'timeline'=>[['label'=>'Pertandingan','type'=>'range','start_date'=>'2027-09-07','end_date'=>'2027-09-09','date'=>'2027-09-07|2027-09-09']],
        ]);
        $competition->sessions()->create([
            'venue_id'=>$venue->id, 'city'=>'Bogor', 'venue'=>'BSI Sport Center',
            'activity_start_date'=>'2027-09-03', 'activity_end_date'=>'2027-09-09',
            'competition_start_date'=>'2027-09-07', 'competition_end_date'=>'2027-09-09',
            'quota'=>32, 'fee'=>200000, 'sort_order'=>0, 'is_active'=>true,
        ]);
        Registration::create([
            'event_edition_id'=>$source->id, 'competition_id'=>$competition->id, 'ticket_code'=>'YEAR-001',
            'full_name'=>'Peserta Lama', 'whatsapp'=>'081234567890', 'email'=>'lama@test.id',
            'birth_place'=>'Bogor', 'birth_date'=>'2009-01-01', 'grade'=>'XI', 'nisn'=>'1234567890',
            'mother_name'=>'Ibu Peserta', 'school_name'=>'SMA Lama', 'teacher_name'=>'Guru',
            'teacher_contact'=>'081298765432', 'student_card_path'=>'a.pdf',
            'delegation_letter_path'=>'b.pdf', 'photo_path'=>'c.jpg', 'consent'=>true, 'status'=>'approved',
        ]);
        SiteContent::create([
            'event_edition_id'=>$source->id, 'key'=>'home_hero',
            'content'=>['title_primary'=>'BSI FLASH 2027'],
        ]);

        $created = $this->withToken('annual-admin-token')->postJson('/api/manage/editions', [
            'year'=>2028, 'name'=>'BSI Flash 2028', 'clone_from_id'=>$source->id,
        ])->assertCreated()->assertJsonPath('year', 2028);
        $target = EventEdition::findOrFail($created->json('id'));

        $this->assertSame('draft', $target->status);
        $this->assertCount(1, $target->competitions);
        $this->assertCount(1, $target->venues);
        $this->assertCount(0, $target->registrations);
        $this->assertSame('2028-09-09', $target->competitions->first()->event_date->toDateString());
        $this->assertSame('BSI FLASH 2028', $target->siteContents()->where('key', 'home_hero')->firstOrFail()->content['title_primary']);

        $this->withToken('annual-admin-token')->postJson('/api/manage/editions/'.$target->id.'/activate')
            ->assertOk()->assertJsonPath('status', 'active');
        $this->assertSame('archived', $source->fresh()->status);

        $this->withHeaders(['Authorization'=>'Bearer annual-admin-token', 'X-BSI-Edition'=>(string) $target->id])
            ->getJson('/api/manage/registrations')->assertOk()->assertJsonCount(0, 'data');
        $this->withHeaders(['Authorization'=>'Bearer annual-admin-token', 'X-BSI-Edition'=>(string) $source->id])
            ->getJson('/api/manage/registrations')->assertOk()->assertJsonCount(1, 'data');
        $this->withHeader('X-BSI-Edition', '')->getJson('/api/competitions?year=2028')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.event_edition_id', $target->id);
    }
}
