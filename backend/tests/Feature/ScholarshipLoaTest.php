<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\Registration;
use App\Models\ScholarshipLoaIssuance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScholarshipLoaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_builds_loa_from_four_winner_ranks(): void
    {
        User::create([
            'name'=>'Admin LOA', 'email'=>'loa-admin@test.id', 'password'=>'password123',
            'role'=>'super_admin', 'api_token'=>hash('sha256', 'loa-admin-token'),
        ]);
        $competition = Competition::create([
            'title'=>'Futsal LOA Test', 'slug'=>'futsal-loa-test', 'category'=>'Sport Competition',
            'participation_type'=>'team', 'team_size'=>5, 'official_count'=>1,
            'short_description'=>'Pengujian LOA pemenang.', 'description'=>'Pengujian penerbitan LOA.',
            'quota'=>16, 'fee'=>0, 'registration_start'=>now()->subDay(),
            'registration_end'=>now()->addDay(), 'event_date'=>now()->addWeek(),
            'location'=>'Bogor', 'requirements'=>[], 'guides'=>[['title'=>'Panduan','content'=>'Test']], 'timeline'=>[],
        ]);

        foreach (range(1, 4) as $rank) {
            $registration = Registration::create([
                'competition_id'=>$competition->id, 'ticket_code'=>'LOA-TEST-'.$rank,
                'full_name'=>'Koordinator '.$rank, 'team_name'=>'Tim Juara '.$rank,
                'email'=>'tim'.$rank.'@test.id', 'whatsapp'=>'08123456789'.$rank,
                'school_name'=>'SMA Pemenang '.$rank, 'school_city'=>'Bogor', 'status'=>'approved', 'consent'=>true,
            ]);
            foreach (range(1, 2) as $memberOrder) {
                $registration->members()->create([
                    'competition_id'=>$competition->id, 'member_order'=>$memberOrder,
                    'full_name'=>'Pemain '.$rank.'-'.$memberOrder, 'email'=>'pemain'.$rank.$memberOrder.'@test.id',
                    'whatsapp'=>'0822000000'.$rank.$memberOrder, 'nisn'=>str_pad((string)($rank * 10 + $memberOrder), 10, '0', STR_PAD_LEFT),
                    'birth_place'=>'Bogor', 'birth_date'=>'2009-01-01', 'grade'=>'XI', 'mother_name'=>'Ibu Test',
                    'student_card_path'=>'test/kartu.jpg', 'photo_path'=>'test/foto.jpg',
                ]);
            }
            $registration->officials()->create([
                'official_order'=>1, 'full_name'=>'Pelatih '.$rank, 'position'=>'Head Coach', 'whatsapp'=>'08330000000'.$rank,
            ]);
            CompetitionResult::create([
                'competition_id'=>$competition->id, 'registration_id'=>$registration->id,
                'rank'=>$rank, 'title'=>$rank === 4 ? 'Juara Harapan' : 'Juara '.$rank,
                'source'=>'manual', 'announced_at'=>now(),
            ]);
        }

        $template = $this->withToken('loa-admin-token')->postJson('/api/manage/scholarship-loas/templates', [
            'name'=>'Format Resmi Test', 'scholarship_name'=>'Beasiswa BSI Test',
            'body_template'=>'{{nama_peserta}} dari {{nama_tim}} sebagai {{peran_penerima}} meraih {{peringkat}} pada {{nama_lomba}} dan menerima {{nama_beasiswa}} sebesar {{besaran_beasiswa}}.',
            'number_pattern'=>'{{sequence}}/LOA/{{year}}', 'signing_city'=>'Jakarta',
            'signatory_name'=>'Direktur Test', 'signatory_position'=>'Direktur', 'is_active'=>true,
            'award_rank_1'=>'100%', 'award_rank_2'=>'75%', 'award_rank_3'=>'50%', 'award_rank_4'=>'25%',
        ])->assertCreated()->json();

        $this->withToken('loa-admin-token')->postJson('/api/manage/scholarship-loas/generate', [
            'competition_id'=>$competition->id, 'competition_session_id'=>null, 'template_id'=>$template['id'],
        ])->assertCreated()->assertJsonCount(12, 'issuances');

        $this->assertDatabaseCount('scholarship_loa_issuances', 12);
        $this->assertSame(12, ScholarshipLoaIssuance::distinct()->count('document_number'));
        $this->withToken('loa-admin-token')->getJson('/api/manage/scholarship-loas?competition_id='.$competition->id)
            ->assertOk()
            ->assertJsonPath('results.3.loa_recipient_count', 3)
            ->assertJsonCount(3, 'results.3.scholarship_loa_issuances');
        $rankFour = CompetitionResult::where('rank', 4)->first();
        $issuanceId = $rankFour->scholarshipLoaIssuances()->where('recipient_key', 'like', 'official:%')->firstOrFail()->id;
        $this->withToken('loa-admin-token')->getJson('/api/manage/scholarship-loas/issuances/'.$issuanceId)
            ->assertOk()
            ->assertJsonPath('snapshot.rank_title', 'Juara Harapan')
            ->assertJsonPath('snapshot.team_name', 'Tim Juara 4')
            ->assertJsonPath('snapshot.recipient_name', 'Pelatih 4')
            ->assertJsonPath('snapshot.recipient_type', 'Official')
            ->assertJsonPath('snapshot.recipient_role', 'Head Coach')
            ->assertJsonPath('snapshot.scholarship_award', '25%')
            ->assertJsonPath('template.scholarship_name', 'Beasiswa BSI Test')
            ->assertJsonPath('rendered_body', 'Pelatih 4 dari Tim Juara 4 sebagai Head Coach meraih Juara Harapan pada Futsal LOA Test dan menerima Beasiswa BSI Test sebesar 25%.');

        $this->withToken('loa-admin-token')->postJson('/api/manage/scholarship-loas/templates/'.$template['id'], [
            'name'=>'Format Resmi Test', 'scholarship_name'=>'Beasiswa BSI Test',
            'body_template'=>'{{nama_peserta}} menerima {{nama_beasiswa}} sebesar {{besaran_beasiswa}}.',
            'number_pattern'=>'{{sequence}}/LOA/{{year}}', 'signing_city'=>'Jakarta',
            'signatory_name'=>'Direktur Test', 'signatory_position'=>'Direktur', 'is_active'=>true,
            'award_rank_1'=>'100%', 'award_rank_2'=>'75%', 'award_rank_3'=>'50%', 'award_rank_4'=>'30%',
        ])->assertOk();

        $this->withToken('loa-admin-token')->postJson('/api/manage/scholarship-loas/generate', [
            'competition_id'=>$competition->id, 'competition_session_id'=>null, 'template_id'=>$template['id'],
        ])->assertCreated()->assertJsonCount(12, 'issuances');

        $this->assertDatabaseCount('scholarship_loa_issuances', 12);
        $this->withToken('loa-admin-token')->getJson('/api/manage/scholarship-loas/issuances/'.$issuanceId)
            ->assertOk()
            ->assertJsonPath('snapshot.scholarship_award', '30%')
            ->assertJsonPath('rendered_body', 'Pelatih 4 menerima Beasiswa BSI Test sebesar 30%.');
    }

    public function test_non_admin_cannot_manage_scholarship_loa(): void
    {
        User::create([
            'name'=>'PIC LOA', 'email'=>'loa-pic@test.id', 'password'=>'password123',
            'role'=>'pic', 'api_token'=>hash('sha256', 'loa-pic-token'),
        ]);
        $this->withToken('loa-pic-token')->getJson('/api/manage/scholarship-loas')->assertForbidden();
    }
}
