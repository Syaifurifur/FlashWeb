<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionSession;
use App\Models\CompetitionVenue;
use App\Models\AccessRole;
use App\Models\Registration;
use App\Models\RegistrationMember;
use App\Models\User;
use App\Models\TournamentMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use RefreshDatabase;

    private function competition(): Competition
    {
        return Competition::create([
            'title' => 'Olimpiade Test', 'slug' => 'olimpiade-test', 'category' => 'Science Competition',
            'short_description' => 'Kompetisi untuk pengujian.', 'description' => 'Deskripsi lengkap.',
            'quota' => 100, 'fee' => 0, 'registration_start' => now()->subDay(),
            'registration_end' => now()->addDays(10), 'event_date' => now()->addDays(20),
            'location' => 'Online', 'requirements' => [], 'timeline' => [],
        ]);
    }

    public function test_validation_and_http_errors_are_returned_in_indonesian(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'email wajib diisi.')
            ->assertJsonPath('errors.password.0', 'kata sandi wajib diisi.');

        $this->getJson('/api/competitions/lomba-yang-tidak-ada')
            ->assertNotFound()
            ->assertJsonPath('message', 'Data yang dicari tidak ditemukan.');

        $this->getJson('/api/rute-yang-tidak-ada')
            ->assertNotFound()
            ->assertJsonPath('message', 'Halaman atau data yang dicari tidak ditemukan.');
    }

    public function test_public_can_browse_and_submit_a_valid_registration(): void
    {
        Storage::fake('public');
        $competition = $this->competition();

        $this->getJson('/api/competitions')->assertOk()->assertJsonCount(1);
        $response = $this->post('/api/registrations', [
            'competition_id' => $competition->id, 'full_name' => 'Peserta Test',
            'whatsapp' => '081234567890', 'email' => 'peserta@test.id', 'birth_place' => 'Bandung',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'birth_date' => '2009-01-01', 'grade' => 'XI', 'nisn' => '1234567890',
            'mother_name' => 'Data Sangat Rahasia', 'school_name' => 'SMA Test',
            'school_city'=>'Kota Makassar','school_address'=>'Jl. Pendidikan No. 1, Makassar',
            'teacher_name' => 'Guru Test', 'teacher_contact' => '081298765432', 'consent' => true,
            'student_card' => UploadedFile::fake()->create('kartu.pdf', 100, 'application/pdf'),
            'delegation_letter' => UploadedFile::fake()->create('delegasi.pdf', 100, 'application/pdf'),
            'photo' => UploadedFile::fake()->create('foto.png', 100, 'image/png'),
        ]);

        $response->assertCreated()->assertJsonStructure(['ticket_code']);
        $this->assertStringStartsWith('BSIFLASH-', $response->json('ticket_code'));
        $registration = Registration::first();
        $this->assertSame('Data Sangat Rahasia', $registration->mother_name);
        $this->assertNotSame('Data Sangat Rahasia', $registration->getRawOriginal('mother_name'));
        $this->assertDatabaseHas('users', ['email'=>'peserta@test.id','role'=>'participant']);
        $this->assertDatabaseHas('registrations', [
            'id'=>$registration->id,'school_city'=>'Kota Makassar','school_address'=>'Jl. Pendidikan No. 1, Makassar',
        ]);
    }

    public function test_initial_registration_requires_school_name(): void
    {
        $competition = $this->competition();

        $this->postJson('/api/registrations', [
            'competition_id'=>$competition->id,
            'full_name'=>'Peserta Tanpa Sekolah',
            'whatsapp'=>'081234567890',
            'email'=>'tanpa-sekolah@test.id',
            'password'=>'password123',
            'password_confirmation'=>'password123',
            'consent'=>true,
        ])->assertUnprocessable()->assertJsonValidationErrors('school_name');
    }

    public function test_pic_and_super_admin_can_see_mother_name_for_validation(): void
    {
        $competition = $this->competition();
        $registration = Registration::create([
            'competition_id' => $competition->id, 'ticket_code' => 'BSIFLASH-TEST1234',
            'full_name' => 'Peserta', 'whatsapp' => '081234567890', 'email' => 'p@test.id',
            'birth_place' => 'Jakarta', 'birth_date' => '2009-01-01', 'grade' => 'XI',
            'nisn' => '1234567890', 'mother_name' => 'Rahasia', 'school_name' => 'SMA Test',
            'teacher_name' => 'Guru', 'teacher_contact' => '081298765432',
            'student_card_path' => 'a.pdf', 'delegation_letter_path' => 'b.pdf',
            'photo_path' => 'c.png', 'consent' => true,
        ]);
        $pic = User::create(['name'=>'PIC','email'=>'pic@test.id','password'=>'password123','role'=>'pic','competition_id'=>$competition->id,'api_token'=>hash('sha256','pic-token')]);
        User::create(['name'=>'Admin','email'=>'admin@test.id','password'=>'password123','role'=>'super_admin','api_token'=>hash('sha256','admin-token')]);

        $this->withToken('pic-token')->getJson('/api/manage/registrations/'.$registration->id)
            ->assertOk()->assertJsonPath('mother_name', 'Rahasia');
        $this->withToken('admin-token')->getJson('/api/manage/registrations/'.$registration->id)
            ->assertOk()->assertJsonPath('mother_name', 'Rahasia');
    }

    public function test_login_and_role_boundaries_are_enforced(): void
    {
        $competition = $this->competition();
        User::create(['name'=>'PIC','email'=>'pic@test.id','password'=>'password123','role'=>'pic','competition_id'=>$competition->id]);

        $login = $this->postJson('/api/login', ['email'=>'pic@test.id','password'=>'password123'])
            ->assertOk()->assertJsonPath('user.role', 'pic');
        $this->withToken($login->json('token'))->postJson('/api/manage/competitions', [])
            ->assertForbidden();
    }

    public function test_pic_can_edit_only_the_assigned_competition(): void
    {
        $assigned = $this->competition();
        $other = $assigned->replicate();
        $other->title = 'Lomba Milik PIC Lain';
        $other->slug = 'lomba-milik-pic-lain';
        $other->save();
        User::create([
            'name'=>'PIC Editor','email'=>'pic-editor@test.id','password'=>'password123','role'=>'pic',
            'competition_id'=>$assigned->id,'api_token'=>hash('sha256','pic-editor-token'),
        ]);
        $payload = [
            'title'=>'Olimpiade Test Diperbarui','category'=>'Science Competition',
            'short_description'=>'Ringkasan telah diperbarui PIC.','description'=>'Deskripsi lengkap yang diperbarui PIC.',
            'quota'=>120,'fee'=>50000,'location'=>'Makassar','poster_url'=>null,
            'whatsapp_group_url'=>'https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrStUv','requirements'=>[],
            'guides'=>[['title'=>'Panduan Peserta','content'=>'Peserta membawa kartu pelajar.']],
            'timeline'=>[['label'=>'Hari Kompetisi','type'=>'single','date'=>now()->addDays(20)->toDateString()]],
            'is_featured'=>false,'participation_type'=>'individual','team_size'=>1,'official_count'=>0,'pic_slots'=>1,
        ];

        $this->withToken('pic-editor-token')->putJson('/api/manage/competitions/'.$assigned->id, $payload)
            ->assertOk()->assertJsonPath('title', 'Olimpiade Test Diperbarui')->assertJsonPath('quota', 120)
            ->assertJsonPath('whatsapp_group_url', 'https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrStUv');
        $this->getJson('/api/competitions/'.$assigned->fresh()->slug)
            ->assertOk()->assertJsonPath('whatsapp_group_url', 'https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrStUv');
        $this->withToken('pic-editor-token')->putJson('/api/manage/competitions/'.$other->id, $payload)
            ->assertForbidden();
        $this->withToken('pic-editor-token')->deleteJson('/api/manage/competitions/'.$assigned->id)
            ->assertForbidden();
    }

    public function test_admin_and_pic_notifications_are_visible_to_the_right_participants(): void
    {
        $assigned = $this->competition();
        $other = $assigned->replicate();
        $other->title = 'Lomba Lain';
        $other->slug = 'lomba-lain-notifikasi';
        $other->save();
        User::create(['name'=>'PIC Notifikasi','email'=>'pic-notif@test.id','password'=>'password123','role'=>'pic','competition_id'=>$assigned->id,'api_token'=>hash('sha256','pic-notif-token')]);
        User::create(['name'=>'Admin Notifikasi','email'=>'admin-notif@test.id','password'=>'password123','role'=>'super_admin','api_token'=>hash('sha256','admin-notif-token')]);
        $participant = User::create(['name'=>'Peserta Notifikasi','email'=>'peserta-notif@test.id','password'=>'password123','role'=>'participant','api_token'=>hash('sha256','peserta-notif-token')]);
        Registration::create([
            'user_id'=>$participant->id,'competition_id'=>$assigned->id,'ticket_code'=>'NOTIF-001','full_name'=>'Peserta Notifikasi',
            'whatsapp'=>'081234567890','email'=>'peserta-notif@test.id','birth_place'=>'Makassar','birth_date'=>'2009-01-01',
            'grade'=>'XI','nisn'=>'1234567890','mother_name'=>'Ibu Peserta','school_name'=>'SMA Test','teacher_name'=>'Guru Test',
            'teacher_contact'=>'081298765432','student_card_path'=>'kartu.pdf','delegation_letter_path'=>'surat.pdf','photo_path'=>'foto.jpg','consent'=>true,
        ]);

        $this->withToken('pic-notif-token')->postJson('/api/manage/notifications', [
            'competition_id'=>$assigned->id,'title'=>'Jadwal Technical Meeting','message'=>'Technical meeting dimulai pukul 09.00.',
        ])->assertCreated()->assertJsonPath('competition.id', $assigned->id);
        $this->withToken('pic-notif-token')->postJson('/api/manage/notifications', [
            'competition_id'=>$other->id,'title'=>'Tidak Diizinkan','message'=>'Bukan lomba PIC.',
        ])->assertForbidden();
        $this->withToken('admin-notif-token')->postJson('/api/manage/notifications', [
            'competition_id'=>null,'title'=>'Pengumuman Umum','message'=>'Selamat datang seluruh peserta.',
        ])->assertCreated();
        $this->withToken('admin-notif-token')->postJson('/api/manage/notifications', [
            'competition_id'=>$other->id,'title'=>'Khusus Lomba Lain','message'=>'Tidak tampil untuk peserta ini.',
        ])->assertCreated();

        $this->withToken('peserta-notif-token')->getJson('/api/participant/notifications')
            ->assertOk()->assertJsonCount(2)
            ->assertJsonFragment(['title'=>'Jadwal Technical Meeting'])
            ->assertJsonFragment(['title'=>'Pengumuman Umum'])
            ->assertJsonMissing(['title'=>'Khusus Lomba Lain']);
    }

    public function test_participant_can_submit_work_link_only_during_non_sport_window(): void
    {
        $competition = $this->competition();
        $competition->update([
            'category'=>'Talent Competition',
            'submission_start_at'=>now()->subHour(),
            'submission_end_at'=>now()->addHour(),
        ]);
        $participant = User::create(['name'=>'Pengirim Karya','email'=>'karya@test.id','password'=>'password123','role'=>'participant','api_token'=>hash('sha256','karya-token')]);
        $registration = Registration::create([
            'user_id'=>$participant->id,'competition_id'=>$competition->id,'ticket_code'=>'KARYA-001','full_name'=>'Pengirim Karya',
            'whatsapp'=>'081234567890','email'=>'karya@test.id','birth_place'=>'Makassar','birth_date'=>'2009-01-01',
            'grade'=>'XI','nisn'=>'1234567890','mother_name'=>'Ibu Peserta','school_name'=>'SMA Test','teacher_name'=>'Guru Test',
            'teacher_contact'=>'081298765432','student_card_path'=>'kartu.pdf','delegation_letter_path'=>'surat.pdf','photo_path'=>'foto.jpg','consent'=>true,
        ]);

        $this->withToken('karya-token')->postJson('/api/participant/registrations/'.$registration->id.'/work-submission', [
            'work_submission_url'=>'https://drive.google.com/file/d/karya-test/view',
        ])->assertOk()->assertJsonPath('work_submission_url', 'https://drive.google.com/file/d/karya-test/view');
        $this->assertNotNull($registration->fresh()->work_submitted_at);

        $competition->update(['submission_start_at'=>now()->addHour(), 'submission_end_at'=>now()->addHours(2)]);
        $this->withToken('karya-token')->postJson('/api/participant/registrations/'.$registration->id.'/work-submission', [
            'work_submission_url'=>'https://example.com/karya-baru',
        ])->assertUnprocessable()->assertJsonPath('message', 'Pengumpulan karya sedang tidak dibuka.');

        $competition->update(['category'=>'Sport Competition','submission_start_at'=>now()->subHour(),'submission_end_at'=>now()->addHour()]);
        $this->withToken('karya-token')->postJson('/api/participant/registrations/'.$registration->id.'/work-submission', [
            'work_submission_url'=>'https://example.com/karya-olahraga',
        ])->assertUnprocessable()->assertJsonPath('message', 'Lomba olahraga tidak menerima pengumpulan karya.');
    }

    public function test_complete_judging_flow_from_verification_to_announced_result(): void
    {
        $competition=$this->competition();
        $competition->update(['category'=>'Talent Competition']);
        User::create(['name'=>'PIC Penilaian','email'=>'pic-judge@test.id','password'=>'password123','role'=>'pic','competition_id'=>$competition->id,'api_token'=>hash('sha256','pic-judge-token')]);
        $judge=User::create(['name'=>'Juri Satu','email'=>'judge@test.id','password'=>'password123','role'=>'judge','api_token'=>hash('sha256','judge-token')]);
        $participant=User::create(['name'=>'Peserta Karya','email'=>'participant-judge@test.id','password'=>'password123','role'=>'participant','api_token'=>hash('sha256','participant-judge-token')]);
        $registration=Registration::create([
            'user_id'=>$participant->id,'competition_id'=>$competition->id,'ticket_code'=>'JUDGE-001','full_name'=>'Peserta Karya',
            'whatsapp'=>'081234567890','email'=>'participant-judge@test.id','birth_place'=>'Makassar','birth_date'=>'2009-01-01','grade'=>'XI','nisn'=>'4234567890',
            'mother_name'=>'Ibu Peserta','school_name'=>'SMA Test','school_city'=>'Makassar','school_address'=>'Jl. Sekolah',
            'teacher_name'=>'Guru','teacher_contact'=>'081298765432','student_card_path'=>'kartu.pdf','delegation_letter_path'=>'surat.pdf','photo_path'=>'foto.jpg',
            'work_submission_url'=>'https://example.com/karya','work_submitted_at'=>now(),'consent'=>true,
        ]);
        $configured=$this->withToken('pic-judge-token')->putJson('/api/manage/judging/competitions/'.$competition->id.'/criteria',[
            'judging_guide'=>'Nilai karya secara objektif dan independen.',
            'criteria'=>[['name'=>'Kreativitas','description'=>'Orisinalitas gagasan','max_score'=>50],['name'=>'Eksekusi','description'=>'Kualitas pelaksanaan','max_score'=>50]],
        ])->assertOk();
        $criterionOne=$configured->json('judging_criteria.0.id');
        $criterionTwo=$configured->json('judging_criteria.1.id');
        $this->withToken('pic-judge-token')->patchJson('/api/manage/judging/registrations/'.$registration->id.'/verify',['status'=>'verified'])
            ->assertOk()->assertJsonPath('work_verification_status','verified');
        $assignment=$this->withToken('pic-judge-token')->postJson('/api/manage/judging/registrations/'.$registration->id.'/assign',['judge_id'=>$judge->id])->assertCreated();
        $assignmentId=$assignment->json('id');
        $this->withToken('judge-token')->getJson('/api/judge/assignments')->assertOk()->assertJsonCount(1)->assertJsonPath('0.registration.id',$registration->id);
        $this->withToken('judge-token')->putJson('/api/judge/assignments/'.$assignmentId.'/score',[
            'action'=>'draft','notes'=>'Catatan sementara','scores'=>[(string)$criterionOne=>40],
        ])->assertOk()->assertJsonPath('status','draft');
        $this->withToken('judge-token')->putJson('/api/judge/assignments/'.$assignmentId.'/score',[
            'action'=>'final','notes'=>'Penilaian lengkap','scores'=>[(string)$criterionOne=>42,(string)$criterionTwo=>46],
        ])->assertOk()->assertJsonPath('status','final');
        $this->withToken('pic-judge-token')->postJson('/api/manage/judging/competitions/'.$competition->id.'/lock')->assertOk();
        $this->withToken('judge-token')->putJson('/api/judge/assignments/'.$assignmentId.'/score',[
            'action'=>'final','scores'=>[(string)$criterionOne=>45,(string)$criterionTwo=>45],
        ])->assertUnprocessable();
        $this->withToken('pic-judge-token')->postJson('/api/manage/judging/competitions/'.$competition->id.'/announce')->assertOk();
        $this->withToken('participant-judge-token')->getJson('/api/participant/judging-results')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.total_score',88)->assertJsonPath('0.judge_count',1);
    }

    public function test_drawing_bracket_byes_manual_order_history_and_winner_progression(): void
    {
        $competition=$this->competition();
        User::create(['name'=>'PIC Drawing','email'=>'pic-drawing@test.id','password'=>'password123','role'=>'pic','competition_id'=>$competition->id,'api_token'=>hash('sha256','pic-drawing-token')]);
        $registrations=collect();
        foreach(range(1,14) as $number)$registrations->push(Registration::create([
            'competition_id'=>$competition->id,'ticket_code'=>'DRAW-'.str_pad($number,3,'0',STR_PAD_LEFT),'full_name'=>'Peserta '.$number,
            'whatsapp'=>'08123456'.str_pad($number,4,'0',STR_PAD_LEFT),'email'=>'draw'.$number.'@test.id','birth_place'=>'Makassar','birth_date'=>'2009-01-01',
            'grade'=>'XI','nisn'=>str_pad((string)(5000000000+$number),10,'0',STR_PAD_LEFT),'mother_name'=>'Ibu '.$number,'school_name'=>'Sekolah '.$number,
            'teacher_name'=>'Guru','teacher_contact'=>'081298765432','student_card_path'=>'a.pdf','delegation_letter_path'=>'b.pdf','photo_path'=>'c.jpg','consent'=>true,'status'=>'approved',
        ]));
        $random=$this->withToken('pic-drawing-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'random','format'=>'single_elimination','avoid_same_school'=>true,'separate_seeds'=>true,'third_place'=>true,
        ])->assertCreated()->assertJsonCount(16,'entries');
        $this->assertCount(2,collect($random->json('entries'))->where('is_bye',true));
        $drawId=$random->json('id');
        $playable=TournamentMatch::where('tournament_draw_id',$drawId)->whereNotNull('participant_a_id')->whereNotNull('participant_b_id')->firstOrFail();
        $winner=$playable->participant_a_id;
        $this->withToken('pic-drawing-token')->putJson('/api/manage/tournaments/matches/'.$playable->id,[
            'score_a'=>2,'score_b'=>1,'status'=>'completed','scheduled_at'=>now()->toDateTimeString(),'venue'=>'Lapangan 1',
        ])->assertOk();
        $dependent=TournamentMatch::where('source_a_match_id',$playable->id)->orWhere('source_b_match_id',$playable->id)->firstOrFail();
        $this->assertContains($winner,[$dependent->fresh()->participant_a_id,$dependent->fresh()->participant_b_id]);

        $manualOrder=$registrations->pluck('id')->reverse()->values()->all();
        $manual=$this->withToken('pic-drawing-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'manual','format'=>'round_robin','manual_order'=>$manualOrder,'avoid_same_school'=>false,'separate_seeds'=>false,'third_place'=>false,
        ])->assertCreated()->assertJsonPath('version',2)->assertJsonPath('entries.0.registration_id',$manualOrder[0]);
        $manualId=$manual->json('id');
        $this->withToken('pic-drawing-token')->getJson('/api/manage/tournaments?competition_id='.$competition->id)
            ->assertOk()->assertJsonCount(2,'history');
        $this->getJson('/api/competitions/'.$competition->slug.'/tournament')->assertOk()->assertJsonPath('draw',null);
        $this->withToken('pic-drawing-token')->postJson('/api/manage/tournaments/draws/'.$manualId.'/lock')->assertOk()->assertJsonPath('status','locked');
        $this->getJson('/api/competitions/'.$competition->slug.'/tournament')->assertOk()->assertJsonPath('draw.id',$manualId);
        $this->withToken('pic-drawing-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'random','format'=>'single_elimination',
        ])->assertUnprocessable();
    }

    public function test_double_elimination_and_group_knockout_generation(): void
    {
        $competition=$this->competition();
        User::create(['name'=>'Admin Tournament','email'=>'admin-tournament@test.id','password'=>'password123','role'=>'super_admin','api_token'=>hash('sha256','admin-tournament-token')]);
        foreach(range(1,8) as $number)Registration::create([
            'competition_id'=>$competition->id,'ticket_code'=>'FORMAT-'.$number,'full_name'=>'Tim '.$number,'whatsapp'=>'0812345600'.str_pad($number,2,'0',STR_PAD_LEFT),
            'email'=>'format'.$number.'@test.id','birth_place'=>'Makassar','birth_date'=>'2009-01-01','grade'=>'XI','nisn'=>(string)(6000000000+$number),
            'mother_name'=>'Ibu','school_name'=>'Sekolah '.$number,'teacher_name'=>'Guru','teacher_contact'=>'081298765432','student_card_path'=>'a.pdf',
            'delegation_letter_path'=>'b.pdf','photo_path'=>'c.jpg','consent'=>true,'status'=>'approved',
        ]);
        $double=$this->withToken('admin-tournament-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'seeded','format'=>'double_elimination','seeded_ids'=>Registration::limit(2)->pluck('id')->all(),
        ])->assertCreated();
        $this->assertCount(14,$double->json('matches'));
        $groups=$this->withToken('admin-tournament-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'random','format'=>'groups_knockout','group_count'=>2,'third_place'=>true,
        ])->assertCreated();
        $groupDrawId=$groups->json('id');
        $groupMatches=TournamentMatch::where('tournament_draw_id',$groupDrawId)->where('stage','group')->get();
        $this->assertCount(12,$groupMatches);
        $this->withToken('admin-tournament-token')->postJson('/api/manage/tournaments/draws/'.$groupDrawId.'/lock')->assertOk();
        $firstGroupMatch=$groupMatches->shift();
        $this->withToken('admin-tournament-token')->putJson('/api/manage/tournaments/matches/'.$firstGroupMatch->id,[
            'score_a'=>2,'score_b'=>1,'status'=>'completed','venue'=>'Lapangan Grup',
        ])->assertOk();
        foreach($groupMatches as $match)$match->update(['score_a'=>2,'score_b'=>1,'winner_id'=>$match->participant_a_id,'status'=>'completed']);
        $this->withToken('admin-tournament-token')->postJson('/api/manage/tournaments/draws/'.$groupDrawId.'/knockout')
            ->assertOk()->assertJsonFragment(['stage'=>'knockout']);
    }

    public function test_team_drawing_only_uses_complete_and_reviewed_teams(): void
    {
        $competition=$this->competition();
        $competition->update(['participation_type'=>'team','team_size'=>2]);
        $pic=User::create(['name'=>'PIC Validasi Drawing','email'=>'pic-validasi-drawing@test.id','password'=>'password123','role'=>'pic','competition_id'=>$competition->id,'api_token'=>hash('sha256','pic-validasi-drawing-token')]);

        $states=[
            ['name'=>'Tim Layak 1','status'=>'approved','complete'=>true,'reviewed'=>true],
            ['name'=>'Tim Layak 2','status'=>'approved','complete'=>true,'reviewed'=>true],
            ['name'=>'Tim Belum Lengkap','status'=>'approved','complete'=>false,'reviewed'=>true],
            ['name'=>'Tim Belum Divalidasi','status'=>'pending','complete'=>true,'reviewed'=>false],
            ['name'=>'Tim Tanpa Pemeriksa','status'=>'approved','complete'=>true,'reviewed'=>false],
        ];
        foreach($states as $index=>$state){
            $registration=Registration::create([
                'competition_id'=>$competition->id,'ticket_code'=>'ELIGIBLE-'.$index,'full_name'=>$state['name'],
                'team_name'=>$state['name'],'email'=>'eligible'.$index.'@test.id','whatsapp'=>'08123456789'.$index,
                'status'=>$state['status'],'team_completed_at'=>$state['complete']?now():null,
                'reviewed_by'=>$state['reviewed']?$pic->id:null,'reviewed_at'=>$state['reviewed']?now():null,
            ]);
            foreach(range(1,2) as $order)RegistrationMember::create([
                'registration_id'=>$registration->id,'competition_id'=>$competition->id,
                'member_order'=>$order,'full_name'=>$state['name'].' Anggota '.$order,
            ]);
        }

        $this->withToken('pic-validasi-drawing-token')->getJson('/api/manage/tournaments?competition_id='.$competition->id)
            ->assertOk()->assertJsonCount(2,'participants')
            ->assertJsonPath('participants.0.team_name','Tim Layak 1')
            ->assertJsonPath('participants.1.team_name','Tim Layak 2');
        $this->withToken('pic-validasi-drawing-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'random','format'=>'single_elimination',
        ])->assertCreated()->assertJsonCount(2,'entries');
    }

    public function test_panitia_schedules_bracket_detects_conflicts_and_notifies_participants(): void
    {
        $competition=$this->competition();
        User::create(['name'=>'PIC Jadwal','email'=>'pic-jadwal@test.id','password'=>'password123','role'=>'pic','competition_id'=>$competition->id,'api_token'=>hash('sha256','pic-jadwal-token')]);
        foreach(range(1,8) as $number) Registration::create([
            'competition_id'=>$competition->id,'ticket_code'=>'SCHEDULE-'.$number,'full_name'=>'Peserta Jadwal '.$number,
            'whatsapp'=>'08127777'.str_pad($number,4,'0',STR_PAD_LEFT),'email'=>'schedule'.$number.'@test.id','birth_place'=>'Makassar','birth_date'=>'2009-01-01',
            'grade'=>'XI','nisn'=>(string)(7000000000+$number),'mother_name'=>'Ibu','school_name'=>'Sekolah '.$number,
            'teacher_name'=>'Guru','teacher_contact'=>'081298765432','student_card_path'=>'a.pdf','delegation_letter_path'=>'b.pdf','photo_path'=>'c.jpg','consent'=>true,'status'=>'approved',
        ]);
        $draw=$this->withToken('pic-jadwal-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'random','format'=>'single_elimination',
        ])->assertCreated();
        $this->withToken('pic-jadwal-token')->postJson('/api/manage/tournaments/draws/'.$draw->json('id').'/lock')->assertOk();
        $matches=TournamentMatch::where('tournament_draw_id',$draw->json('id'))->whereNotNull('participant_a_id')->whereNotNull('participant_b_id')->take(2)->get();
        $startsAt=now()->addDay()->setTime(8,0)->format('Y-m-d H:i:s');

        $this->withToken('pic-jadwal-token')->putJson('/api/manage/schedules/matches/'.$matches[0]->id,[
            'scheduled_at'=>$startsAt,'venue'=>'Lapangan Utama','duration_minutes'=>60,'status'=>'upcoming','notify'=>true,
        ])->assertOk();
        $this->assertDatabaseHas('competition_notifications',['competition_id'=>$competition->id,'title'=>'Pembaruan Jadwal Match '.$matches[0]->match_number]);

        $this->withToken('pic-jadwal-token')->putJson('/api/manage/schedules/matches/'.$matches[1]->id,[
            'scheduled_at'=>$startsAt,'venue'=>'Lapangan Utama','duration_minutes'=>60,'status'=>'upcoming',
        ])->assertUnprocessable()->assertJsonPath('message','Jadwal berbenturan.');
        $this->withToken('pic-jadwal-token')->putJson('/api/manage/schedules/matches/'.$matches[1]->id,[
            'scheduled_at'=>$startsAt,'venue'=>'Lapangan Utama','duration_minutes'=>60,'status'=>'upcoming','force'=>true,
        ])->assertOk()->assertJsonCount(1,'conflicts');

        $this->withToken('pic-jadwal-token')->postJson('/api/manage/schedules/competitions/'.$competition->id.'/blocks',[
            'title'=>'Istirahat','venue'=>'Lapangan 2','starts_at'=>now()->addDay()->setTime(12,0)->format('Y-m-d H:i:s'),'duration_minutes'=>60,
        ])->assertCreated()->assertJsonCount(1,'blocks');
        $this->getJson('/api/competitions/'.$competition->slug.'/schedule')->assertOk()
            ->assertJsonPath('draw.id',$draw->json('id'))->assertJsonCount(1,'blocks');
    }

    public function test_super_admin_creates_role_with_checked_permissions(): void
    {
        $competition=$this->competition();
        User::create(['name'=>'Admin Role','email'=>'admin-role@test.id','password'=>'password123','role'=>'super_admin','api_token'=>hash('sha256','admin-role-token')]);
        $roleResponse=$this->withToken('admin-role-token')->postJson('/api/manage/roles',[
            'name'=>'Petugas Data','permissions'=>['registrations.view'],
        ])->assertCreated()->assertJsonPath('name','Petugas Data');
        $role=AccessRole::findOrFail($roleResponse->json('id'));
        $this->assertContains('dashboard.view',$role->permissions);
        $staff=User::create(['name'=>'Petugas','email'=>'petugas@test.id','password'=>'password123','role'=>$role->slug,'competition_id'=>$competition->id,'api_token'=>hash('sha256','petugas-token')]);

        $this->withToken('petugas-token')->getJson('/api/manage/registrations')->assertOk();
        $this->withToken('petugas-token')->postJson('/api/manage/competitions',[])->assertForbidden();
        $this->assertSame('Petugas Data',$staff->fresh()->role_name);
    }

    public function test_registration_list_can_be_filtered_by_competition_name(): void
    {
        $competition=$this->competition();
        $other=$competition->replicate();
        $other->title='Lomba Tanpa Pendaftar'; $other->slug='lomba-tanpa-pendaftar'; $other->save();
        Registration::create([
            'competition_id'=>$competition->id,'ticket_code'=>'BSIFLASH-FILTER01','full_name'=>'Peserta Filter',
            'whatsapp'=>'081234567890','email'=>'filter@test.id','birth_place'=>'Jakarta','birth_date'=>'2009-01-01',
            'grade'=>'XI','nisn'=>'2234567890','mother_name'=>'Rahasia','school_name'=>'SMA Filter',
            'teacher_name'=>'Guru','teacher_contact'=>'081298765432','student_card_path'=>'a.pdf',
            'delegation_letter_path'=>'b.pdf','photo_path'=>'c.png','consent'=>true,
        ]);
        User::create(['name'=>'Admin Filter','email'=>'admin-filter@test.id','password'=>'password123','role'=>'super_admin','api_token'=>hash('sha256','admin-filter-token')]);

        $this->withToken('admin-filter-token')->getJson('/api/manage/registrations?competition_id='.$competition->id)
            ->assertOk()->assertJsonCount(1,'data');
        $this->withToken('admin-filter-token')->getJson('/api/manage/registrations?competition_id='.$other->id)
            ->assertOk()->assertJsonCount(0,'data');
    }

    public function test_excel_export_and_super_admin_only_registration_deletion(): void
    {
        $competition=$this->competition();
        $registration=Registration::create([
            'competition_id'=>$competition->id,'ticket_code'=>'BSIFLASH-EXCEL01','full_name'=>'Peserta Excel',
            'whatsapp'=>'081234567890','email'=>'excel@test.id','birth_place'=>'Jakarta','birth_date'=>'2009-01-01',
            'grade'=>'XI','nisn'=>'3234567890','mother_name'=>'Rahasia','school_name'=>'SMA Excel',
            'teacher_name'=>'Guru','teacher_contact'=>'081298765432','student_card_path'=>'a.pdf',
            'delegation_letter_path'=>'b.pdf','photo_path'=>'c.png','consent'=>true,'status'=>'approved',
        ]);
        User::create(['name'=>'Admin Excel','email'=>'admin-excel@test.id','password'=>'password123','role'=>'super_admin','api_token'=>hash('sha256','admin-excel-token')]);
        User::create(['name'=>'PIC Excel','email'=>'pic-excel@test.id','password'=>'password123','role'=>'pic','competition_id'=>$competition->id,'api_token'=>hash('sha256','pic-excel-token')]);

        $response=$this->withToken('admin-excel-token')->get('/api/manage/registrations/export')->assertOk()
            ->assertHeader('content-type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path=$response->baseResponse->getFile()->getPathname();
        $zip=new \ZipArchive();
        $this->assertTrue($zip->open($path)===true);
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet1.xml'));
        $zip->close();

        $this->withToken('pic-excel-token')->deleteJson('/api/manage/registrations/'.$registration->id)->assertForbidden();
        $this->withToken('admin-excel-token')->deleteJson('/api/manage/registrations/'.$registration->id)->assertNoContent();
        $this->assertDatabaseMissing('registrations',['id'=>$registration->id]);
    }

    public function test_super_admin_can_create_custom_named_timeline(): void
    {
        User::create(['name'=>'Admin','email'=>'timeline-admin@test.id','password'=>'password123','role'=>'super_admin','api_token'=>hash('sha256','timeline-admin-token')]);
        $timeline = [
            ['label'=>'Pendaftaran Gelombang Satu','type'=>'single','date'=>now()->addDay()->toDateString()],
            ['label'=>'Technical Meeting','type'=>'single','date'=>now()->addDays(5)->toDateString()],
            ['label'=>'Babak Penyisihan','type'=>'range','start_date'=>now()->addDays(12)->toDateString(),'end_date'=>now()->addDays(14)->toDateString()],
            ['label'=>'Grand Final','type'=>'single','date'=>now()->addDays(20)->toDateString()],
        ];

        $this->withToken('timeline-admin-token')->postJson('/api/manage/competitions', [
            'title'=>'Lomba Timeline Fleksibel','category'=>'Science Competition','short_description'=>'Timeline dapat disesuaikan.',
            'description'=>'Deskripsi lomba dengan rangkaian tanggal sendiri.','quota'=>100,'fee'=>0,
            'location'=>'Online','requirements'=>[],
            'guides'=>[
                ['title'=>'Ketentuan Pendaftaran','content'=>"Daftar secara online.\nLengkapi seluruh berkas."],
                ['title'=>'Ketentuan Peserta','content'=>'Peserta merupakan siswa aktif.'],
            ],
            'timeline'=>$timeline,'is_featured'=>false,'participation_type'=>'individual','team_size'=>1,
            'official_count'=>0,'pic_slots'=>1,
        ])->assertCreated()->assertJsonPath('timeline.1.label','Technical Meeting');

        $competition = Competition::where('title','Lomba Timeline Fleksibel')->first();
        $this->assertSame('range', $competition->timeline[2]['type']);
        $this->assertSame('Ketentuan Pendaftaran', $competition->guides[0]['title']);
        $this->assertSame('Peserta merupakan siswa aktif.', $competition->guides[1]['content']);
        $this->assertSame($timeline[2]['start_date'], $competition->timeline[2]['start_date']);
        $this->assertSame($timeline[2]['end_date'], $competition->timeline[2]['end_date']);
        $this->assertSame($timeline[0]['date'], $competition->registration_start->toDateString());
        $this->assertSame($timeline[3]['date'], $competition->event_date->toDateString());
    }

    public function test_representative_registers_complete_team_and_officials(): void
    {
        Storage::fake('public');
        $competition = $this->competition();
        $competition->update(['participation_type' => 'team', 'team_size' => 2, 'official_count' => 1, 'fee' => 150000]);
        $this->post('/api/registrations', [
            'competition_id'=>$competition->id, 'full_name'=>'Perwakilan Tim', 'email'=>'team@test.id',
            'password'=>'password123','password_confirmation'=>'password123','whatsapp'=>'081234567890',
            'birth_place'=>'Jakarta','birth_date'=>'2009-01-01','grade'=>'XI','nisn'=>'1234567890','mother_name'=>'Ibu Perwakilan',
            'team_name'=>'Tim Test', 'school_name'=>'SMA Test','school_city'=>'Kabupaten Gowa',
            'school_address'=>'Jl. Pendidikan No. 2, Gowa', 'teacher_name'=>'Guru',
            'teacher_contact'=>'081298765432', 'consent'=>true,
            'student_card'=>UploadedFile::fake()->create('kartu.pdf',100,'application/pdf'),
            'school_logo'=>UploadedFile::fake()->create('logo.png',100,'image/png'),
            'statement_letter'=>UploadedFile::fake()->create('pernyataan.pdf',100,'application/pdf'),
            'delegation_letter'=>UploadedFile::fake()->create('delegasi.pdf',100,'application/pdf'),
            'payment_proof'=>UploadedFile::fake()->create('struk.pdf',100,'application/pdf'),
            'photo'=>UploadedFile::fake()->create('foto.png',100,'image/png'),
            'members'=>[[
                'full_name'=>'Anggota Kedua','email'=>'anggota2@test.id','whatsapp'=>'081234567898',
                'nisn'=>'1234567891','birth_place'=>'Bandung',
                'birth_date'=>'2009-02-02','grade'=>'X','mother_name'=>'Ibu Anggota',
            ]],
            'member_student_cards'=>[UploadedFile::fake()->create('kartu-2.pdf',100,'application/pdf')],
            'member_photos'=>[UploadedFile::fake()->create('foto-2.png',100,'image/png')],
            'officials'=>[['full_name'=>'Pelatih Test','position'=>'Pelatih','whatsapp'=>'081234567899']],
        ])->assertCreated();
        $this->assertDatabaseHas('registrations',['team_name'=>'Tim Test','full_name'=>'Perwakilan Tim']);
        $this->assertDatabaseCount('registration_members',2);
        $this->assertDatabaseHas('registration_members',['member_order'=>1,'full_name'=>'Perwakilan Tim']);
        $this->assertDatabaseHas('registration_members',['member_order'=>2,'full_name'=>'Anggota Kedua']);
        $this->assertDatabaseHas('registration_members',['member_order'=>2,'email'=>'anggota2@test.id','whatsapp'=>'081234567898']);
        $this->assertDatabaseHas('registration_officials',['official_order'=>1,'full_name'=>'Pelatih Test']);
        User::create(['name'=>'Admin Official','email'=>'admin-official@test.id','password'=>'password123','role'=>'super_admin','api_token'=>hash('sha256','admin-official-token')]);
        User::create(['name'=>'PIC Official','email'=>'pic-official@test.id','password'=>'password123','role'=>'pic','competition_id'=>$competition->id,'api_token'=>hash('sha256','pic-official-token')]);
        $this->withToken('admin-official-token')->getJson('/api/manage/registrations/'.Registration::first()->id)
            ->assertOk()->assertJsonPath('officials.0.full_name','Pelatih Test')
            ->assertJsonPath('officials.0.position','Pelatih')
            ->assertJsonPath('members.1.email','anggota2@test.id')
            ->assertJsonPath('members.1.whatsapp','081234567898');

        $member=RegistrationMember::where('member_order',2)->firstOrFail();
        $pic=User::where('email','pic-official@test.id')->firstOrFail();
        $this->withToken('pic-official-token')->patchJson('/api/manage/registration-members/'.$member->id.'/nisn-verification',[
            'is_valid'=>true,
        ])->assertOk()->assertJsonPath('id',$member->id)->assertJsonPath('nisn_verified_by',$pic->id);
        $this->assertNotNull($member->fresh()->nisn_verified_at);

        $registration=Registration::firstOrFail();
        $this->withToken('pic-official-token')->patchJson('/api/manage/registrations/'.$registration->id.'/review',[
            'status'=>'approved','review_note'=>null,
        ])->assertUnprocessable()->assertJsonPath('message','Bukti pembayaran harus diperiksa dan ditandai valid sebelum peserta diterima.');
        $this->withToken('pic-official-token')->patchJson('/api/manage/registrations/'.$registration->id.'/payment-verification',[
            'is_valid'=>true,
        ])->assertOk()->assertJsonPath('payment_verified_by',$pic->id);
        $this->assertNotNull($registration->fresh()->payment_verified_at);
        $this->withToken('pic-official-token')->patchJson('/api/manage/registrations/'.$registration->id.'/review',[
            'status'=>'approved','review_note'=>null,
        ])->assertOk()->assertJsonPath('status','approved');

        $participant=User::where('email','team@test.id')->firstOrFail();
        $participant->update(['api_token'=>hash('sha256','team-participant-token')]);
        $registration->update(['status'=>'revision','review_note'=>'Perbaiki data anggota tim.']);
        $this->withToken('team-participant-token')->getJson('/api/participant/registrations')
            ->assertOk()->assertJsonPath('0.members.1.mother_name','Ibu Anggota')
            ->assertJsonPath('0.members.1.nisn_verified_by',$pic->id)
            ->assertJsonPath('0.officials.0.full_name','Pelatih Test');

        $this->withToken('team-participant-token')->postJson('/api/participant/registrations/'.$registration->id,[
            'team_name'=>'Tim Test Revisi','school_name'=>'SMA Test','school_city'=>'Kota Makassar',
            'school_address'=>'Jl. Sekolah Baru No. 3','teacher_name'=>'Guru Test',
            'teacher_contact'=>'081298765432',
            'members'=>[
                ['full_name'=>'Perwakilan Tim','email'=>'team@test.id','whatsapp'=>'081234567890','nisn'=>'1234567890','birth_place'=>'Jakarta','birth_date'=>'2009-01-01','grade'=>'XI','mother_name'=>'Ibu Perwakilan'],
                ['full_name'=>'Anggota Kedua Revisi','email'=>'anggota2@test.id','whatsapp'=>'081234567898','nisn'=>'1234567891','birth_place'=>'Bandung','birth_date'=>'2009-02-02','grade'=>'X','mother_name'=>'Ibu Anggota'],
            ],
            'officials'=>[['full_name'=>'Pelatih Test','position'=>'Pelatih','whatsapp'=>'081234567899']],
        ])->assertOk()->assertJsonPath('registration.status','pending')->assertJsonPath('registration.members.1.full_name','Anggota Kedua Revisi');
        $this->assertDatabaseHas('registrations',['id'=>$registration->id,'team_name'=>'Tim Test Revisi','status'=>'pending']);
        $this->assertNull($member->fresh()->nisn_verified_at);
    }

    public function test_participant_can_edit_only_after_revision_request(): void
    {
        $competition=$this->competition();
        $user=User::create(['name'=>'Peserta','email'=>'peserta@test.id','password'=>'password123','role'=>'participant','api_token'=>hash('sha256','participant-token')]);
        $registration=Registration::create([
            'user_id'=>$user->id,'competition_id'=>$competition->id,'ticket_code'=>'BSIFLASH-PART1234','full_name'=>'Peserta','whatsapp'=>'081234567890','email'=>$user->email,
            'birth_place'=>'Jakarta','birth_date'=>'2009-01-01','grade'=>'XI','nisn'=>'1234567890','mother_name'=>'Rahasia','school_name'=>'SMA Test',
            'teacher_name'=>'Guru','teacher_contact'=>'081298765432','student_card_path'=>'a.pdf','delegation_letter_path'=>'b.pdf','photo_path'=>'c.png','consent'=>true,
        ]);
        $payload=['full_name'=>'Peserta Baru','whatsapp'=>'081234567890','birth_place'=>'Jakarta','birth_date'=>'2009-01-01','grade'=>'XI','nisn'=>'1234567890','school_name'=>'SMA Test','teacher_name'=>'Guru','teacher_contact'=>'081298765432'];
        $this->withToken('participant-token')->postJson('/api/participant/registrations/'.$registration->id,$payload)->assertForbidden();
        $registration->update(['status'=>'revision','review_note'=>'Perbaiki nama.']);
        $this->withToken('participant-token')->postJson('/api/participant/registrations/'.$registration->id,$payload)->assertOk()->assertJsonPath('registration.status','pending');
        $this->assertDatabaseHas('registrations',['id'=>$registration->id,'full_name'=>'Peserta Baru','status'=>'pending']);
    }

    public function test_pic_can_only_configure_format_for_assigned_competition(): void
    {
        $assigned = $this->competition();
        $other = $assigned->replicate();
        $other->title = 'Lomba Lain'; $other->slug = 'lomba-lain'; $other->save();
        User::create(['name'=>'PIC','email'=>'pic@test.id','password'=>'password123','role'=>'pic','competition_id'=>$assigned->id,'api_token'=>hash('sha256','pic-format-token')]);
        $registration = Registration::create([
            'competition_id'=>$assigned->id,
            'ticket_code'=>'BSIFLASH-FORMAT01',
            'full_name'=>'Peserta Format',
            'email'=>'peserta-format@test.id',
            'whatsapp'=>'081234567890',
            'birth_place'=>'Bandung',
            'birth_date'=>'2009-01-01',
            'grade'=>'XI',
            'nisn'=>'1234567890',
            'mother_name'=>'Ibu Peserta',
            'school_name'=>'SMA Format',
            'student_card_path'=>'kartu.pdf',
            'photo_path'=>'foto.jpg',
            'consent'=>true,
            'team_completed_at'=>now(),
        ]);

        $this->withToken('pic-format-token')->patchJson('/api/manage/competitions/'.$assigned->id.'/format', [
            'participation_type'=>'team', 'team_size'=>4, 'official_count'=>2,
        ])->assertOk()->assertJsonPath('team_size', 4)->assertJsonPath('official_count', 2);
        $this->assertNull($registration->fresh()->team_completed_at);
        $this->withToken('pic-format-token')->patchJson('/api/manage/competitions/'.$other->id.'/format', [
            'participation_type'=>'team', 'team_size'=>3, 'official_count'=>1,
        ])->assertForbidden();
        $guides = [['title'=>'Ketentuan Peserta','content'=>'Peserta wajib membawa kartu pelajar.']];
        $this->withToken('pic-format-token')->patchJson('/api/manage/competitions/'.$assigned->id.'/guides', [
            'guides'=>$guides,
        ])->assertOk()->assertJsonPath('guides.0.title', 'Ketentuan Peserta');
        $this->withToken('pic-format-token')->patchJson('/api/manage/competitions/'.$other->id.'/guides', [
            'guides'=>$guides,
        ])->assertForbidden();
    }

    public function test_user_can_reset_forgotten_password(): void
    {
        Mail::fake();
        User::create(['name'=>'Peserta','email'=>'forgot@test.id','password'=>'password-lama','role'=>'participant']);
        $forgot=$this->postJson('/api/forgot-password',['email'=>'forgot@test.id'])->assertOk();
        parse_str(parse_url($forgot->json('reset_url'),PHP_URL_QUERY),$query);
        $this->postJson('/api/reset-password',[
            'email'=>'forgot@test.id','token'=>$query['token'],'password'=>'password-baru','password_confirmation'=>'password-baru',
        ])->assertOk();
        $this->postJson('/api/login',['email'=>'forgot@test.id','password'=>'password-lama'])->assertUnprocessable();
        $this->postJson('/api/login',['email'=>'forgot@test.id','password'=>'password-baru'])->assertOk();
    }

    public function test_super_admin_assigns_multiple_whatsapp_pics_within_slot_limit(): void
    {
        $competition=$this->competition();
        $competition->update(['pic_slots'=>2]);
        User::create(['name'=>'Admin','email'=>'admin@test.id','password'=>'password123','role'=>'super_admin','api_token'=>hash('sha256','admin-pic-token')]);
        $picOne=User::create(['name'=>'PIC Satu','email'=>'pic1@test.id','whatsapp'=>'081234567890','password'=>'password123','role'=>'pic']);
        $picTwo=User::create(['name'=>'PIC Dua','email'=>'pic2@test.id','whatsapp'=>'081234567891','password'=>'password123','role'=>'pic']);

        $this->withToken('admin-pic-token')->putJson('/api/manage/competitions/'.$competition->id.'/pics',[
            'pic_ids'=>[$picOne->id,$picTwo->id],
        ])->assertOk()->assertJsonCount(2,'pics');
        $this->getJson('/api/competitions/'.$competition->slug)
            ->assertOk()->assertJsonCount(2,'pics')->assertJsonPath('pics.0.whatsapp','081234567890');
    }

    public function test_home_content_can_be_managed_without_image_slideshows(): void
    {
        $competition=$this->competition();
        User::create(['name'=>'Admin Content','email'=>'admin-content@test.id','password'=>'password123','role'=>'super_admin','api_token'=>hash('sha256','admin-content-token')]);
        User::create(['name'=>'PIC Content','email'=>'pic-content@test.id','password'=>'password123','role'=>'pic','competition_id'=>$competition->id,'api_token'=>hash('sha256','pic-content-token')]);

        $this->getJson('/api/content/home-hero')->assertOk()->assertJsonPath('title_primary','YOUR TALENT.')->assertJsonMissingPath('slides');
        $payload=[
            'badge'=>'Kompetisi Pelajar 2026','title_primary'=>'TUNJUKKAN BAKATMU.','title_accent'=>'MENANG BERSAMA.',
            'description'=>'Konten hero yang dapat diubah dari dashboard.','primary_button_label'=>'Lihat Lomba','primary_button_url'=>'/lomba',
            'secondary_button_label'=>'Masuk','secondary_button_url'=>'/login','hashtag'=>'#JUARABERSAMA',
        ];
        $this->withToken('pic-content-token')->postJson('/api/manage/content/home-hero',$payload)->assertForbidden();
        $this->withToken('admin-content-token')->postJson('/api/manage/content/home-hero',$payload)
            ->assertOk()->assertJsonPath('hashtag','#JUARABERSAMA')->assertJsonMissingPath('slides');
        $this->getJson('/api/content/home-hero')->assertOk()->assertJsonPath('title_primary','TUNJUKKAN BAKATMU.')->assertJsonMissingPath('slide_interval');
        $this->assertDatabaseHas('site_contents',['key'=>'home_hero']);

        $extras=[
            'testimonial_title'=>'Cerita Para Juara','testimonial_interval'=>5,'testimonials'=>[
                ['name'=>'Alya Putri','role'=>'Peserta BSI Flash 2026','testimonial'=>'BSI Flash membantu saya lebih percaya diri dan berani berprestasi.','photo_url'=>'https://example.com/alya.jpg','is_active'=>true],
            ],
            'sponsor_title'=>'Sponsor BSI Flash 2027','sponsors'=>[
                ['name'=>'Sponsor Satu','logo_url'=>'https://example.com/sponsor.png','website_url'=>'https://example.com'],
            ],
            'media_partner_title'=>'Media Partners','media_partners'=>[
                ['name'=>'Media Satu','logo_url'=>'https://example.com/media.png','website_url'=>'https://example.com/media'],
            ],
        ];
        $this->withToken('pic-content-token')->postJson('/api/manage/content/landing-extras',$extras)->assertForbidden();
        $this->withToken('admin-content-token')->postJson('/api/manage/content/landing-extras',$extras)
            ->assertOk()->assertJsonMissingPath('activity_slides')->assertJsonCount(1,'testimonials')->assertJsonCount(1,'sponsors')->assertJsonCount(1,'media_partners');
        $this->getJson('/api/content/landing-extras')->assertOk()
            ->assertJsonMissingPath('activity_slides')
            ->assertJsonPath('testimonial_title','Cerita Para Juara')->assertJsonPath('testimonials.0.name','Alya Putri')
            ->assertJsonPath('sponsors.0.name','Sponsor Satu')->assertJsonPath('media_partners.0.name','Media Satu');
        $this->assertDatabaseHas('site_contents',['key'=>'landing_extras']);

        $consent = [
            'title'=>'Persetujuan Data BSI Flash 2027',
            'checkbox_label'=>'Saya membaca dan menyetujui penggunaan data untuk proses lomba.',
            'security_note'=>'Password tidak dapat dilihat oleh panitia.',
            'items'=>[
                ['title'=>'Identitas','description'=>'Digunakan untuk memvalidasi peserta.'],
                ['title'=>'Dokumen','description'=>'Digunakan untuk memeriksa persyaratan lomba.'],
            ],
        ];
        $this->withToken('pic-content-token')->postJson('/api/manage/content/data-consent',$consent)->assertForbidden();
        $this->withToken('admin-content-token')->postJson('/api/manage/content/data-consent',$consent)
            ->assertOk()->assertJsonPath('title','Persetujuan Data BSI Flash 2027')->assertJsonCount(2,'items');
        $this->getJson('/api/content/data-consent')->assertOk()
            ->assertJsonPath('checkbox_label','Saya membaca dan menyetujui penggunaan data untuk proses lomba.')
            ->assertJsonPath('items.1.title','Dokumen');
        $this->assertDatabaseHas('site_contents',['key'=>'data_consent']);
    }

    public function test_pic_and_admin_upload_competition_documents_for_participants_to_download(): void
    {
        Storage::fake('public');
        $competition = $this->competition();
        User::create(['name'=>'PIC Dokumen','email'=>'pic-doc@test.id','password'=>'password123','role'=>'pic','competition_id'=>$competition->id,'api_token'=>hash('sha256','pic-doc-token')]);
        User::create(['name'=>'Admin Dokumen','email'=>'admin-doc@test.id','password'=>'password123','role'=>'super_admin','api_token'=>hash('sha256','admin-doc-token')]);

        $this->withToken('pic-doc-token')->post('/api/manage/competitions/'.$competition->id.'/downloadable-documents', [
            'documents'=>[
                ['title'=>'Format Surat Rekomendasi Sekolah','description'=>'Diisi dan ditandatangani pihak sekolah.'],
                ['title'=>'Panduan Teknis','description'=>'Panduan persiapan peserta.'],
            ],
            'document_files'=>[
                UploadedFile::fake()->create('rekomendasi.docx',100,'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                UploadedFile::fake()->create('panduan.pdf',100,'application/pdf'),
            ],
        ])->assertOk()->assertJsonPath('downloadable_documents.0.title','Format Surat Rekomendasi Sekolah');

        $this->getJson('/api/competitions/'.$competition->slug)
            ->assertOk()->assertJsonCount(2,'downloadable_documents')
            ->assertJsonPath('downloadable_documents.1.original_name','panduan.pdf');

        $this->withToken('pic-doc-token')->post('/api/manage/general-documents', [
            'documents'=>[
                ['title'=>'Panduan Umum Peserta','description'=>'Berlaku untuk seluruh cabang lomba.'],
            ],
            'document_files'=>[
                UploadedFile::fake()->create('panduan-umum.pdf',100,'application/pdf'),
            ],
        ])->assertOk()->assertJsonPath('documents.0.title','Panduan Umum Peserta');
        $this->getJson('/api/content/general-documents')->assertOk()
            ->assertJsonPath('documents.0.original_name','panduan-umum.pdf');

        $this->withToken('admin-doc-token')->post('/api/manage/competitions/'.$competition->id.'/downloadable-documents', [
            'documents'=>[],
        ])->assertOk()->assertJsonCount(0,'downloadable_documents');
        $this->withToken('admin-doc-token')->post('/api/manage/general-documents', ['documents'=>[]])
            ->assertOk()->assertJsonCount(0,'documents');
    }

    public function test_participant_completes_data_and_documents_in_separate_deadline_stages(): void
    {
        Storage::fake('public');
        $competition = $this->competition();
        $competition->update([
            'participation_type'=>'team',
            'team_size'=>2,
            'team_update_deadline_at'=>now()->addDay(),
            'document_upload_deadline_at'=>now()->addDays(2),
        ]);

        $this->postJson('/api/registrations', [
            'competition_id'=>$competition->id,
            'full_name'=>'Perwakilan Bertahap',
            'school_name'=>'SMA Bertahap',
            'email'=>'bertahap@test.id',
            'whatsapp'=>'081234567890',
            'password'=>'password123',
            'password_confirmation'=>'password123',
            'consent'=>true,
        ])->assertCreated();

        $registration = Registration::firstOrFail();
        $this->assertNull($registration->team_completed_at);
        $this->assertNull($registration->documents_completed_at);
        $user = User::where('email','bertahap@test.id')->firstOrFail();
        $user->update(['api_token'=>hash('sha256','participant-staged-token')]);

        $this->withToken('participant-staged-token')->post('/api/participant/registrations/'.$registration->id.'/documents', [
            'school_logo'=>UploadedFile::fake()->create('logo.png',100,'image/png'),
            'statement_letter'=>UploadedFile::fake()->create('pernyataan.pdf',100,'application/pdf'),
            'school_recommendation_letter'=>UploadedFile::fake()->create('rekomendasi.docx',100,'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertOk();

        $registration->refresh();
        $this->assertNull($registration->team_completed_at);
        $this->assertNotNull($registration->documents_completed_at);
        $this->assertDatabaseCount('registration_members', 1);

        $this->withToken('participant-staged-token')->post('/api/participant/registrations/'.$registration->id.'/team', [
            'team_name'=>'Tim Bertahap',
            'members'=>[
                ['full_name'=>'Perwakilan Bertahap','email'=>'bertahap@test.id','whatsapp'=>'081234567890','nisn'=>'9876543210','birth_place'=>'Makassar','birth_date'=>'2009-01-01','grade'=>'XI','mother_name'=>'Ibu Bertahap'],
                ['full_name'=>'Anggota Bertahap','email'=>'anggota-bertahap@test.id','whatsapp'=>'081234567891','nisn'=>'9876543211','birth_place'=>'Gowa','birth_date'=>'2009-02-01','grade'=>'X','mother_name'=>'Ibu Anggota'],
            ],
            'school_name'=>'SMA Bertahap',
            'school_city'=>'Kota Makassar',
            'school_address'=>'Jl. Pendidikan',
            'teacher_name'=>'Guru Pendamping',
            'teacher_contact'=>'081298765432',
            'member_student_cards'=>[
                UploadedFile::fake()->create('kartu-1.pdf',100,'application/pdf'),
                UploadedFile::fake()->create('kartu-2.pdf',100,'application/pdf'),
            ],
            'member_photos'=>[
                UploadedFile::fake()->create('foto-1.jpg',100,'image/jpeg'),
                UploadedFile::fake()->create('foto-2.jpg',100,'image/jpeg'),
            ],
        ])->assertOk()->assertJsonPath('registration.team_name','Tim Bertahap');

        $registration->refresh();
        $this->assertNotNull($registration->team_completed_at);
        $this->assertNotNull($registration->documents_completed_at);
        $this->assertNotNull($registration->school_logo_path);
        $this->assertNotNull($registration->statement_letter_path);
    }

    public function test_team_documents_can_be_uploaded_per_member_and_resumed(): void
    {
        Storage::fake('public');
        $competition = $this->competition();
        $competition->update([
            'participation_type'=>'team',
            'team_size'=>2,
            'team_update_deadline_at'=>now()->addDay(),
        ]);

        $this->postJson('/api/registrations', [
            'competition_id'=>$competition->id,
            'full_name'=>'Ketua Upload Bertahap',
            'school_name'=>'SMA Upload Bertahap',
            'email'=>'ketua-upload@test.id',
            'whatsapp'=>'081234567880',
            'password'=>'password123',
            'password_confirmation'=>'password123',
            'consent'=>true,
        ])->assertCreated();

        $registration = Registration::firstOrFail();
        $user = User::where('email','ketua-upload@test.id')->firstOrFail();
        $user->update(['api_token'=>hash('sha256','member-upload-token')]);

        $this->withToken('member-upload-token')->postJson('/api/participant/registrations/'.$registration->id.'/team', [
            'team_name'=>'Tim Upload Bertahap',
            'members'=>[
                ['full_name'=>'Ketua Upload Bertahap','email'=>'ketua-upload@test.id','whatsapp'=>'081234567880','nisn'=>'8876543210','birth_place'=>'Jakarta','birth_date'=>'2009-01-01','grade'=>'XI','mother_name'=>'Ibu Ketua'],
                ['full_name'=>'Anggota Upload Bertahap','email'=>'anggota-upload@test.id','whatsapp'=>'081234567881','nisn'=>'8876543211','birth_place'=>'Depok','birth_date'=>'2009-02-01','grade'=>'X','mother_name'=>'Ibu Anggota'],
            ],
            'school_name'=>'SMA Upload Bertahap',
            'school_city'=>'Jakarta',
            'school_address'=>'Jl. Pendidikan No. 1',
            'teacher_name'=>'Guru Upload',
            'teacher_contact'=>'081298765430',
        ])->assertOk()->assertJsonPath('registration.team_completed_at', null);

        $members = $registration->fresh()->members()->orderBy('member_order')->get();
        $this->assertCount(2, $members);

        $firstUpload = $this->withToken('member-upload-token')->post('/api/participant/registrations/'.$registration->id.'/members/'.$members[0]->id.'/documents', [
            'student_card'=>UploadedFile::fake()->create('kartu-ketua.pdf',100,'application/pdf'),
            'photo'=>UploadedFile::fake()->create('foto-ketua.jpg',100,'image/jpeg'),
        ]);
        $firstUpload->assertOk()
            ->assertJsonPath('uploaded_members', 1)
            ->assertJsonPath('total_members', 2)
            ->assertJsonPath('team_completed', false);
        $this->assertNull($registration->fresh()->team_completed_at);

        $secondUpload = $this->withToken('member-upload-token')->post('/api/participant/registrations/'.$registration->id.'/members/'.$members[1]->id.'/documents', [
            'student_card'=>UploadedFile::fake()->create('kartu-anggota.pdf',100,'application/pdf'),
            'photo'=>UploadedFile::fake()->create('foto-anggota.jpg',100,'image/jpeg'),
        ]);
        $secondUpload->assertOk()
            ->assertJsonPath('uploaded_members', 2)
            ->assertJsonPath('total_members', 2)
            ->assertJsonPath('team_completed', true);
        $this->assertNotNull($registration->fresh()->team_completed_at);
    }

    public function test_admin_can_manage_multiple_location_sessions_for_one_competition(): void
    {
        User::create(['name'=>'Admin Lokasi','email'=>'lokasi-admin@test.id','password'=>'password123','role'=>'super_admin','api_token'=>hash('sha256','lokasi-admin-token')]);
        $timeline = [
            ['label'=>'Pendaftaran','type'=>'range','start_date'=>'2026-08-01','end_date'=>'2026-08-31'],
            ['label'=>'Pelaksanaan','type'=>'range','start_date'=>'2026-09-03','end_date'=>'2027-01-19'],
        ];

        $response = $this->withToken('lokasi-admin-token')->postJson('/api/manage/competitions', [
            'title'=>'Futsal Putra Multi Kota','category'=>'Sport Competition','short_description'=>'Futsal BSI Flash di beberapa kota.',
            'description'=>'Peserta memilih satu kota pelaksanaan.','quota'=>300,'fee'=>0,'location'=>'Beberapa lokasi',
            'guides'=>[['title'=>'Ketentuan','content'=>'Peserta merupakan siswa aktif.']],
            'timeline'=>$timeline,'is_featured'=>true,'participation_type'=>'team','team_size'=>10,'official_count'=>2,'pic_slots'=>2,
            'sessions'=>[
                ['city'=>'Bogor','venue'=>'BSI Sport Center UBSI Kampus Bogor A','activity_start_date'=>'2026-09-03','activity_end_date'=>'2026-09-09','competition_start_date'=>'2026-09-07','competition_end_date'=>'2026-09-09','information_label'=>'Technical Meeting','information_at'=>'2026-08-26 09:00:00','quota'=>50],
                ['city'=>'Pontianak','venue'=>'BSI Sport Center UBSI Kampus Pontianak','activity_start_date'=>'2026-09-30','activity_end_date'=>'2026-10-06','competition_start_date'=>'2026-09-30','competition_end_date'=>'2026-10-02','information_label'=>'Technical Meeting','information_at'=>'2026-09-23 09:00:00','quota'=>50],
            ],
        ])->assertCreated()->assertJsonCount(2, 'sessions')->assertJsonPath('sessions.1.city', 'Pontianak');

        $competition = Competition::findOrFail($response->json('id'));
        $this->assertSame('2 lokasi', $competition->location);
        $this->assertSame('2026-10-02', $competition->event_date->toDateString());
        $this->assertDatabaseHas('competition_sessions', ['competition_id'=>$competition->id,'city'=>'Bogor']);
        $this->getJson('/api/competitions/'.$competition->slug)
            ->assertOk()->assertJsonCount(2, 'sessions')->assertJsonPath('sessions.0.city', 'Bogor');
    }

    public function test_super_admin_can_manage_competition_venues(): void
    {
        User::create([
            'name'=>'Admin Venue','email'=>'admin-venue@test.id','password'=>'password123',
            'role'=>'super_admin','api_token'=>hash('sha256','admin-venue-token'),
        ]);

        $pic = User::create(['name'=>'PIC Kota','email'=>'pic-kota@test.id','whatsapp'=>'081234567890','password'=>'password123','role'=>'pic']);
        $supervisorResponse = $this->withToken('admin-venue-token')->postJson('/api/manage/accounts', [
            'name'=>'SPV Kota','email'=>'spv-kota@test.id','whatsapp'=>'081234567891',
            'password'=>'password123','role'=>'spv','competition_id'=>null,'is_active'=>true,
        ])->assertCreated()->assertJsonPath('whatsapp', '081234567891');
        $supervisor = User::findOrFail($supervisorResponse->json('id'));
        $created = $this->withToken('admin-venue-token')->postJson('/api/manage/venues', [
            'name'=>'BSI Sport Center UBSI Kampus Bogor A',
            'city'=>'Bogor',
            'address'=>'Jl. Merdeka No. 10, Kota Bogor',
            'activity_start_date'=>'2026-09-03',
            'activity_end_date'=>'2026-09-09',
            'field_photo_url'=>'https://example.com/lapangan-bogor.jpg',
            'pic_user_id'=>$pic->id,
            'supervisor_user_id'=>$supervisor->id,
            'maps_url'=>'https://maps.google.com/?q=BSI+Bogor',
            'contact_name'=>'Panitia Bogor',
            'contact_phone'=>'0812 3456 7890',
            'notes'=>'Tersedia parkir bus.',
            'is_active'=>true,
        ])->assertCreated()->assertJsonPath('city', 'Bogor');

        $venue = CompetitionVenue::findOrFail($created->json('id'));
        $this->withToken('admin-venue-token')->getJson('/api/manage/city-staff')
            ->assertOk()
            ->assertJsonPath('pics.0.name', 'PIC Kota')
            ->assertJsonPath('supervisors.0.name', 'SPV Kota');
        $this->withToken('admin-venue-token')->putJson('/api/manage/venues/'.$venue->id, [
            'name'=>$venue->name,
            'city'=>'Kota Bogor',
            'address'=>$venue->address,
            'activity_start_date'=>'2026-09-03',
            'activity_end_date'=>'2026-09-09',
            'field_photo_url'=>'https://example.com/lapangan-bogor.jpg',
            'pic_user_id'=>$pic->id,
            'supervisor_user_id'=>$supervisor->id,
            'maps_url'=>$venue->maps_url,
            'contact_name'=>$venue->contact_name,
            'contact_phone'=>$venue->contact_phone,
            'notes'=>$venue->notes,
            'is_active'=>true,
        ])->assertOk()->assertJsonPath('city', 'Kota Bogor');

        $competition = $this->competition();
        CompetitionSession::create([
            'competition_id'=>$competition->id,'venue_id'=>$venue->id,'city'=>'Kota Bogor','venue'=>$venue->name,
            'activity_start_date'=>now()->addDays(10),'activity_end_date'=>now()->addDays(11),
            'competition_start_date'=>now()->addDays(10),'competition_end_date'=>now()->addDays(11),
            'is_active'=>true,
        ]);

        $this->withToken('admin-venue-token')->deleteJson('/api/manage/venues/'.$venue->id)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Tempat masih digunakan pada sesi lomba. Nonaktifkan tempat atau pindahkan sesi terlebih dahulu.');

        $this->withToken('admin-venue-token')->getJson('/api/manage/venues')
            ->assertOk()->assertJsonPath('0.sessions_count', 1);
        $this->getJson('/api/venues')->assertOk()->assertJsonPath('0.city', 'Kota Bogor');
        $this->getJson('/api/venues/'.$venue->fresh()->slug)
            ->assertOk()
            ->assertJsonPath('pic.name', 'PIC Kota')
            ->assertJsonPath('supervisor.name', 'SPV Kota');
    }

    public function test_competition_global_data_and_city_terms_are_stored_separately(): void
    {
        User::create(['name'=>'Admin Kota','email'=>'admin-kota@test.id','password'=>'password123','role'=>'super_admin','api_token'=>hash('sha256','admin-kota-token')]);
        $pic = User::create(['name'=>'PIC Bekasi','email'=>'pic-bekasi@test.id','whatsapp'=>'081234567899','password'=>'password123','role'=>'pic']);
        $picSecond = User::create(['name'=>'PIC Bekasi Dua','email'=>'pic-bekasi-2@test.id','whatsapp'=>'081234567897','password'=>'password123','role'=>'pic']);
        $supervisor = User::create(['name'=>'SPV Bekasi','email'=>'spv-bekasi@test.id','whatsapp'=>'081234567898','password'=>'password123','role'=>'spv']);
        $supervisorSecond = User::create(['name'=>'SPV Bekasi Dua','email'=>'spv-bekasi-2@test.id','whatsapp'=>'081234567896','password'=>'password123','role'=>'spv']);
        $venue = CompetitionVenue::create([
            'slug'=>'bekasi','name'=>'BSI Sport Center UBSI Bekasi','city'=>'Bekasi','address'=>'Kampus UBSI Bekasi',
            'activity_start_date'=>'2027-01-13','activity_end_date'=>'2027-01-19',
            'field_photo_url'=>'https://example.com/lapangan-bekasi.jpg','pic_user_id'=>$pic->id,
            'supervisor_user_id'=>$supervisor->id,'is_active'=>true,
        ]);

        $response = $this->withToken('admin-kota-token')->postJson('/api/manage/competitions', [
            'title'=>'Futsal Putra Kota','category'=>'Sport Competition','short_description'=>'Futsal multi kota.',
            'description'=>'Data utama lomba berlaku secara global.','poster_url'=>'https://example.com/futsal.jpg',
            'guides'=>[['title'=>'Panduan Futsal','content'=>'Peserta wajib membawa kartu pelajar.']],
            'participation_type'=>'team','team_size'=>10,'official_count'=>2,'pic_slots'=>1,
            'sessions'=>[array_merge([
                'venue_id'=>$venue->id,'pic_slots'=>2,'supervisor_slots'=>2,
                'pic_ids'=>[$pic->id,$picSecond->id],
                'supervisor_ids'=>[$supervisor->id,$supervisorSecond->id],
                'quota'=>48,'fee'=>250000,
                'team_update_deadline_at'=>'2027-01-06 23:59:00',
                'timeline'=>[
                    ['label'=>'Pendaftaran','type'=>'range','start_date'=>'2026-12-01','end_date'=>'2027-01-06'],
                    ['label'=>'Pertandingan','type'=>'range','start_date'=>'2027-01-13','end_date'=>'2027-01-19'],
                ],
                'whatsapp_number'=>'081234567899','whatsapp_group_url'=>'https://chat.whatsapp.com/ExampleGroup',
            ])],
        ])->assertCreated()
            ->assertJsonPath('sessions.0.city', 'Bekasi')
            ->assertJsonPath('sessions.0.fee', '250000.00')
            ->assertJsonPath('sessions.0.timeline.1.label', 'Pertandingan')
            ->assertJsonCount(2, 'sessions.0.pics')
            ->assertJsonCount(2, 'sessions.0.supervisors');

        $competition = Competition::findOrFail($response->json('id'));
        $this->assertSame('Bekasi', $competition->location);
        $this->assertSame(48, $competition->quota);
        $this->assertDatabaseHas('competition_sessions', [
            'competition_id'=>$competition->id,'venue_id'=>$venue->id,'pic_user_id'=>$pic->id,
            'supervisor_user_id'=>$supervisor->id,'pic_slots'=>2,'supervisor_slots'=>2,'quota'=>48,'fee'=>250000,
        ]);
        $this->assertDatabaseHas('competition_session_staff', ['user_id'=>$picSecond->id,'role'=>'pic']);
        $this->assertDatabaseHas('competition_session_staff', ['user_id'=>$supervisorSecond->id,'role'=>'spv']);
        $this->getJson('/api/venues/bekasi')
            ->assertOk()
            ->assertJsonPath('sessions.0.competition.title', 'Futsal Putra Kota')
            ->assertJsonCount(2, 'sessions.0.pics')
            ->assertJsonCount(2, 'sessions.0.supervisors')
            ->assertJsonPath('sessions.0.pics.1.name', 'PIC Bekasi Dua')
            ->assertJsonPath('sessions.0.supervisors.1.name', 'SPV Bekasi Dua')
            ->assertJsonPath('sessions.0.whatsapp_number', '081234567899');
        $this->withToken('admin-kota-token')->getJson('/api/manage/dashboard')
            ->assertOk()
            ->assertJsonPath('cities.0.city', 'Bekasi')
            ->assertJsonPath('cities.0.competitions_count', 1)
            ->assertJsonPath('cities.0.registrations_count', 0)
            ->assertJsonPath('cities.0.approved_count', 0)
            ->assertJsonPath('cities.0.pending_count', 0);
    }

    public function test_registration_requires_available_session_when_competition_has_locations(): void
    {
        $competition = $this->competition();
        $session = CompetitionSession::create([
            'competition_id'=>$competition->id,'city'=>'Bekasi','venue'=>'BSI Sport Center UBSI Bekasi',
            'activity_start_date'=>now()->addDays(15)->toDateString(),'activity_end_date'=>now()->addDays(17)->toDateString(),
            'competition_start_date'=>now()->addDays(15)->toDateString(),'competition_end_date'=>now()->addDays(17)->toDateString(),
            'quota'=>1,'is_active'=>true,
        ]);
        $payload = [
            'competition_id'=>$competition->id,'full_name'=>'Peserta Lokasi','whatsapp'=>'081234567890',
            'email'=>'peserta-lokasi@test.id','school_name'=>'SMA Lokasi','password'=>'password123','password_confirmation'=>'password123','consent'=>true,
        ];

        $this->postJson('/api/registrations', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('competition_session_id');

        $this->postJson('/api/registrations', $payload + ['competition_session_id'=>$session->id])
            ->assertCreated();
        $this->assertDatabaseHas('registrations', [
            'competition_id'=>$competition->id,'competition_session_id'=>$session->id,'email'=>'peserta-lokasi@test.id',
        ]);

        $this->postJson('/api/registrations', [
            ...$payload,'competition_session_id'=>$session->id,'full_name'=>'Peserta Kedua',
            'email'=>'peserta-kedua@test.id',
        ])->assertUnprocessable()->assertJsonPath('message', 'Kuota pada lokasi yang dipilih telah penuh.');
    }
}
