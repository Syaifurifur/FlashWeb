<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionNotification;
use App\Models\CompetitionSession;
use App\Models\JudgeAssignment;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function competition(): Competition
    {
        return Competition::create([
            'title'=>'Lomba Dua Kota', 'slug'=>'lomba-dua-kota', 'category'=>'Science Competition',
            'short_description'=>'Lomba pengujian pemisahan lokasi.', 'description'=>'Data operasional wajib terpisah.',
            'quota'=>100, 'fee'=>0, 'registration_start'=>now()->subDay(),
            'registration_end'=>now()->addDays(10), 'event_date'=>now()->addDays(20),
            'location'=>'Multi Kota', 'requirements'=>[], 'timeline'=>[],
        ]);
    }

    private function competitionSession(Competition $competition, string $city, int $order): CompetitionSession
    {
        return $competition->sessions()->create([
            'city'=>$city, 'venue'=>'Kampus BSI '.$city,
            'activity_start_date'=>now()->addDays(10 + $order),
            'activity_end_date'=>now()->addDays(11 + $order),
            'competition_start_date'=>now()->addDays(10 + $order),
            'competition_end_date'=>now()->addDays(11 + $order),
            'quota'=>50, 'sort_order'=>$order, 'is_active'=>true,
        ]);
    }

    private function registration(Competition $competition, CompetitionSession $session, string $suffix): Registration
    {
        return Registration::create([
            'competition_id'=>$competition->id, 'competition_session_id'=>$session->id,
            'ticket_code'=>'BSIFLASH-'.$suffix, 'full_name'=>'Peserta '.$suffix,
            'whatsapp'=>'0812345678'.str_pad((string) $session->id, 2, '0', STR_PAD_LEFT),
            'email'=>strtolower($suffix).'@test.id', 'birth_place'=>$session->city,
            'birth_date'=>'2009-01-01', 'grade'=>'XI', 'nisn'=>str_pad((string) $session->id, 10, '1', STR_PAD_LEFT),
            'mother_name'=>'Ibu '.$suffix, 'school_name'=>'SMA '.$session->city,
            'teacher_name'=>'Guru '.$suffix, 'teacher_contact'=>'081298765432',
            'student_card_path'=>'kartu.pdf', 'delegation_letter_path'=>'surat.pdf',
            'photo_path'=>'foto.jpg', 'consent'=>true, 'status'=>'approved',
        ]);
    }

    public function test_pic_can_only_access_operational_data_from_assigned_location(): void
    {
        $competition = $this->competition();
        $jakarta = $this->competitionSession($competition, 'Jakarta', 1);
        $bogor = $this->competitionSession($competition, 'Bogor', 2);
        $jakartaRegistration = $this->registration($competition, $jakarta, 'JAKARTA');
        $bogorRegistration = $this->registration($competition, $bogor, 'BOGOR');

        $pic = User::create([
            'name'=>'PIC Jakarta', 'email'=>'pic-jakarta@test.id', 'whatsapp'=>'081234567899',
            'password'=>'password123', 'role'=>'pic', 'api_token'=>hash('sha256', 'pic-jakarta-token'),
        ]);
        $jakarta->staff()->attach($pic->id, ['role'=>'pic','sort_order'=>0]);

        $this->withToken('pic-jakarta-token')->getJson('/api/manage/registrations')
            ->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $jakartaRegistration->id)
            ->assertJsonPath('data.0.competition_session.id', $jakarta->id);
        $this->withToken('pic-jakarta-token')->getJson('/api/manage/registrations/'.$bogorRegistration->id)
            ->assertForbidden();
        $this->withToken('pic-jakarta-token')->getJson('/api/manage/registration-competitions')
            ->assertOk()->assertJsonCount(1, '0.sessions')->assertJsonPath('0.sessions.0.id', $jakarta->id);

        foreach (['tournaments', 'schedules', 'judging'] as $module) {
            $this->withToken('pic-jakarta-token')->getJson('/api/manage/'.$module)
                ->assertOk()->assertJsonCount(1, 'scopes')->assertJsonPath('scopes.0.session_id', $jakarta->id);
        }

        $this->withToken('pic-jakarta-token')->postJson('/api/manage/notifications', [
            'competition_id'=>$competition->id, 'competition_session_id'=>$bogor->id,
            'title'=>'Bukan Wilayah PIC', 'message'=>'Data Bogor harus ditolak.',
        ])->assertForbidden();
        $this->withToken('pic-jakarta-token')->postJson('/api/manage/notifications', [
            'competition_id'=>$competition->id, 'competition_session_id'=>$jakarta->id,
            'title'=>'Informasi Jakarta', 'message'=>'Khusus peserta Jakarta.',
        ])->assertCreated()->assertJsonPath('competition_session.id', $jakarta->id);

        CompetitionNotification::create([
            'competition_id'=>$competition->id, 'competition_session_id'=>$bogor->id,
            'author_id'=>$pic->id, 'title'=>'Informasi Bogor', 'message'=>'Khusus Bogor.', 'published_at'=>now(),
        ]);
        $this->withToken('pic-jakarta-token')->getJson('/api/manage/notifications')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.competition_session.id', $jakarta->id);
    }

    public function test_judging_lock_results_and_winners_are_separate_for_each_location(): void
    {
        $competition = $this->competition();
        $jakarta = $this->competitionSession($competition, 'Jakarta', 1);
        $bogor = $this->competitionSession($competition, 'Bogor', 2);
        $jakartaRegistration = $this->registration($competition, $jakarta, 'NILAI-JKT');
        $bogorRegistration = $this->registration($competition, $bogor, 'NILAI-BGR');
        $jakartaRegistration->update(['work_submission_url'=>'https://example.test/jakarta','work_verification_status'=>'verified']);
        $bogorRegistration->update(['work_submission_url'=>'https://example.test/bogor','work_verification_status'=>'verified']);
        $judge = User::create(['name'=>'Juri','email'=>'juri-lokasi@test.id','password'=>'password123','role'=>'judge']);
        $admin = User::create([
            'name'=>'Admin','email'=>'admin-lokasi@test.id','password'=>'password123','role'=>'super_admin',
            'api_token'=>hash('sha256','admin-lokasi-token'),
        ]);
        foreach ([$jakartaRegistration, $bogorRegistration] as $registration) {
            JudgeAssignment::create([
                'competition_id'=>$competition->id, 'registration_id'=>$registration->id,
                'judge_id'=>$judge->id, 'assigned_by'=>$admin->id, 'status'=>'final', 'submitted_at'=>now(),
            ]);
        }

        $this->withToken('admin-lokasi-token')->postJson('/api/manage/judging/competitions/'.$competition->id.'/lock', [
            'competition_session_id'=>$jakarta->id,
        ])->assertOk();
        $this->assertNotNull($jakarta->fresh()->judging_locked_at);
        $this->assertNull($bogor->fresh()->judging_locked_at);

        $this->withToken('admin-lokasi-token')->postJson('/api/manage/judging/competitions/'.$competition->id.'/announce', [
            'competition_session_id'=>$jakarta->id,
        ])->assertOk();
        $this->assertDatabaseHas('competition_results', [
            'competition_id'=>$competition->id, 'competition_session_id'=>$jakarta->id,
            'registration_id'=>$jakartaRegistration->id, 'source'=>'judging', 'rank'=>1,
        ]);
        $this->assertDatabaseMissing('competition_results', [
            'competition_id'=>$competition->id, 'competition_session_id'=>$bogor->id, 'source'=>'judging',
        ]);
        $this->withToken('admin-lokasi-token')->getJson('/api/manage/judging?session_id='.$bogor->id)
            ->assertOk()->assertJsonPath('competition.judging_locked_at', null)
            ->assertJsonPath('competition.results_announced_at', null)
            ->assertJsonPath('works.0.id', $bogorRegistration->id);
    }
}
