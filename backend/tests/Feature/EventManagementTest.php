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
            ->assertJsonPath('message', 'Data belum dapat diproses. Periksa kolom yang ditandai.')
            ->assertJsonPath('errors.email.0', 'email wajib diisi.')
            ->assertJsonPath('errors.password.0', 'kata sandi wajib diisi.')
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.location.module', 'Autentikasi akun')
            ->assertJsonPath('error.location.endpoint', 'POST /api/login')
            ->assertJsonPath('error.fields.0.key', 'email')
            ->assertJsonPath('error.fields.0.label', 'Email');

        $this->getJson('/api/competitions/lomba-yang-tidak-ada')
            ->assertNotFound()
            ->assertJsonPath('message', 'Data yang dicari tidak ditemukan.')
            ->assertJsonPath('error.code', 'DATA_NOT_FOUND')
            ->assertJsonPath('error.location.module', 'Manajemen lomba');

        $this->getJson('/api/rute-yang-tidak-ada')
            ->assertNotFound()
            ->assertJsonPath('message', 'Halaman atau data yang dicari tidak ditemukan.')
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonPath('error.location.endpoint', 'GET /api/rute-yang-tidak-ada')
            ->assertJsonStructure(['error'=>['id','status','detected_at','location'=>['module','endpoint','path']]]);
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

    public function test_dashboard_shows_filled_quota_for_each_competition_city(): void
    {
        $competition = $this->competition();
        $competition->update(['quota' => 4]);
        $jakartaVenue = CompetitionVenue::create([
            'slug' => 'jakarta-kuota',
            'name' => 'Kampus BSI Jakarta',
            'city' => 'Jakarta',
            'address' => 'Jakarta',
            'activity_start_date' => '2027-01-10',
            'activity_end_date' => '2027-01-12',
            'is_active' => true,
        ]);
        $bandungVenue = CompetitionVenue::create([
            'slug' => 'bandung-kuota',
            'name' => 'Kampus BSI Bandung',
            'city' => 'Bandung',
            'address' => 'Bandung',
            'activity_start_date' => '2027-01-15',
            'activity_end_date' => '2027-01-16',
            'is_active' => true,
        ]);
        $session = CompetitionSession::create([
            'competition_id' => $competition->id,
            'venue_id' => $jakartaVenue->id,
            'city' => 'Jakarta',
            'venue' => 'Kampus BSI Jakarta',
            'activity_start_date' => '2027-01-10',
            'activity_end_date' => '2027-01-12',
            'competition_start_date' => '2027-01-11',
            'competition_end_date' => '2027-01-12',
            'quota' => 4,
        ]);
        $bandungSession = CompetitionSession::create([
            'competition_id' => $competition->id,
            'venue_id' => $bandungVenue->id,
            'city' => 'Bandung',
            'venue' => 'Kampus BSI Bandung',
            'activity_start_date' => '2027-01-15',
            'activity_end_date' => '2027-01-16',
            'competition_start_date' => '2027-01-15',
            'competition_end_date' => '2027-01-16',
            'quota' => 10,
        ]);
        User::create([
            'name' => 'Admin Kuota',
            'email' => 'admin-kuota@test.id',
            'password' => 'password123',
            'role' => 'super_admin',
            'api_token' => hash('sha256', 'admin-kuota-token'),
        ]);

        foreach (range(1, 3) as $number) {
            Registration::create([
                'competition_id' => $competition->id,
                'competition_session_id' => $session->id,
                'ticket_code' => 'BSIFLASH-KUOTA'.$number,
                'full_name' => 'Peserta Kuota '.$number,
                'whatsapp' => '08123456789'.$number,
                'email' => 'peserta-kuota-'.$number.'@test.id',
                'birth_place' => 'Jakarta',
                'birth_date' => '2009-01-01',
                'grade' => 'XI',
                'nisn' => '123456789'.$number,
                'mother_name' => 'Ibu Peserta',
                'school_name' => 'SMA Test',
                'teacher_name' => 'Guru Test',
                'teacher_contact' => '081298765432',
                'student_card_path' => 'kartu.pdf',
                'delegation_letter_path' => 'delegasi.pdf',
                'photo_path' => 'foto.png',
                'consent' => true,
            ]);
        }
        Registration::create([
            'competition_id' => $competition->id,
            'competition_session_id' => $bandungSession->id,
            'ticket_code' => 'BSIFLASH-BANDUNG1',
            'full_name' => 'Peserta Bandung',
            'whatsapp' => '081234567899',
            'email' => 'peserta-bandung@test.id',
            'birth_place' => 'Bandung',
            'birth_date' => '2009-01-01',
            'grade' => 'XI',
            'nisn' => '9876543210',
            'mother_name' => 'Ibu Peserta',
            'school_name' => 'SMA Bandung',
            'teacher_name' => 'Guru Bandung',
            'teacher_contact' => '081298765433',
            'student_card_path' => 'kartu.pdf',
            'delegation_letter_path' => 'delegasi.pdf',
            'photo_path' => 'foto.png',
            'consent' => true,
        ]);

        $this->withToken('admin-kuota-token')->getJson('/api/manage/dashboard')
            ->assertOk()
            ->assertJsonCount(2, 'cities')
            ->assertJsonPath('cities.0.city', 'Jakarta')
            ->assertJsonPath('cities.0.competition_quotas.0.title', 'Olimpiade Test')
            ->assertJsonPath('cities.0.competition_quotas.0.filled', 3)
            ->assertJsonPath('cities.0.competition_quotas.0.quota', 4)
            ->assertJsonPath('cities.1.city', 'Bandung')
            ->assertJsonPath('cities.1.competition_quotas.0.title', 'Olimpiade Test')
            ->assertJsonPath('cities.1.competition_quotas.0.filled', 1)
            ->assertJsonPath('cities.1.competition_quotas.0.quota', 10)
            ->assertJsonMissingPath('competition_quotas');
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
        $admin=User::create(['name'=>'Admin Buka Drawing','email'=>'admin-buka-drawing@test.id','password'=>'password123','role'=>'super_admin','api_token'=>hash('sha256','admin-buka-drawing-token')]);
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
        $this->withToken('pic-drawing-token')->postJson('/api/manage/tournaments/draws/'.$manualId.'/unlock')
            ->assertForbidden();
        $this->withToken('pic-drawing-token')->getJson('/api/manage/tournaments?competition_id='.$competition->id)
            ->assertOk()->assertJsonPath('can_unlock',false);
        $this->withToken('admin-buka-drawing-token')->getJson('/api/manage/tournaments?competition_id='.$competition->id)
            ->assertOk()->assertJsonPath('can_unlock',true);
        $this->withToken('admin-buka-drawing-token')->postJson('/api/manage/tournaments/draws/'.$manualId.'/unlock')
            ->assertOk()
            ->assertJsonPath('status','draft')
            ->assertJsonPath('locked_at',null)
            ->assertJsonPath('settings.unlock_history.0.user_id',$admin->id);
        $this->getJson('/api/competitions/'.$competition->slug.'/tournament')->assertOk()->assertJsonPath('draw',null);
        $this->withToken('pic-drawing-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'random','format'=>'single_elimination',
        ])->assertCreated()->assertJsonPath('version',3);
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
        ])->assertCreated()->assertJsonCount(2,'group_standings')
            ->assertJsonPath('group_standings.0.played_matches',0)
            ->assertJsonPath('group_standings.0.completed',false)
            ->assertJsonCount(4,'group_standings.0.rows');
        $groupDrawId=$groups->json('id');
        $groupMatches=TournamentMatch::where('tournament_draw_id',$groupDrawId)->where('stage','group')->get();
        $this->assertCount(12,$groupMatches);
        $this->withToken('admin-tournament-token')->postJson('/api/manage/tournaments/draws/'.$groupDrawId.'/lock')->assertOk();
        $firstGroupMatch=$groupMatches->shift();
        $this->withToken('admin-tournament-token')->putJson('/api/manage/tournaments/matches/'.$firstGroupMatch->id,[
            'score_a'=>2,'score_b'=>1,'status'=>'completed','venue'=>'Lapangan Grup',
        ])->assertOk()->assertJsonPath('group_standings.0.played_matches',1)
            ->assertJsonPath('group_standings.0.rows.0.points',3);
        foreach($groupMatches as $match)$match->update(['score_a'=>2,'score_b'=>1,'winner_id'=>$match->participant_a_id,'status'=>'completed']);
        $knockout=$this->withToken('admin-tournament-token')->postJson('/api/manage/tournaments/draws/'.$groupDrawId.'/knockout')
            ->assertOk()->assertJsonFragment(['stage'=>'knockout']);
        foreach($knockout->json('group_standings') as $standing){
            $this->assertTrue($standing['completed']);
            $this->assertCount(2,collect($standing['rows'])->where('qualified',true));
        }
        $this->getJson('/api/competitions/'.$competition->slug.'/tournament')
            ->assertOk()->assertJsonCount(2,'draw.group_standings');
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
            ['name'=>'Tim Ditolak','status'=>'rejected','complete'=>true,'reviewed'=>true],
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
            ->assertJsonCount(3,'force_majeure_candidates')
            ->assertJsonPath('drawing_readiness.verified',2)
            ->assertJsonPath('drawing_readiness.force_majeure_candidates',3)
            ->assertJsonPath('drawing_readiness.rejected',1)
            ->assertJsonPath('participants.0.team_name','Tim Layak 1')
            ->assertJsonPath('participants.1.team_name','Tim Layak 2');
        $this->withToken('pic-validasi-drawing-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'random','format'=>'single_elimination',
        ])->assertCreated()->assertJsonCount(2,'entries');

        $pending=Registration::where('team_name','Tim Belum Divalidasi')->firstOrFail();
        $this->withToken('pic-validasi-drawing-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'random','format'=>'single_elimination','force_majeure_ids'=>[$pending->id],
            'force_majeure_reason'=>'Singkat',
        ])->assertUnprocessable()->assertJsonPath('message','Alasan force majeure wajib diisi minimal 10 karakter.');

        $rejected=Registration::where('team_name','Tim Ditolak')->firstOrFail();
        $this->withToken('pic-validasi-drawing-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'random','format'=>'single_elimination','force_majeure_ids'=>[$rejected->id],
            'force_majeure_reason'=>'Keputusan rapat panitia sebelum drawing.',
        ])->assertUnprocessable()->assertJsonPath('message','Pilihan force majeure tidak valid atau tim sudah ditolak.');

        $forceMajeure=$this->withToken('pic-validasi-drawing-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'random','format'=>'single_elimination','force_majeure_ids'=>[$pending->id],
            'force_majeure_reason'=>'Dokumen asli sedang diverifikasi saat jadwal drawing dimulai.',
        ])->assertCreated()->assertJsonCount(4,'entries')
            ->assertJsonPath('settings.force_majeure.registration_ids.0',$pending->id)
            ->assertJsonPath('settings.force_majeure.teams.0.status','pending')
            ->assertJsonPath('settings.force_majeure.approved_by.id',$pic->id);
        $this->assertContains($pending->id,collect($forceMajeure->json('entries'))->pluck('registration_id'));
    }

    public function test_manual_drawing_supports_bracket_groups_and_round_robin_formats(): void
    {
        $competition=$this->competition();
        User::create(['name'=>'Admin Manual Drawing','email'=>'admin-manual-drawing@test.id','password'=>'password123','role'=>'super_admin','api_token'=>hash('sha256','admin-manual-drawing-token')]);
        $registrations=collect();
        foreach(range(1,6) as $number)$registrations->push(Registration::create([
            'competition_id'=>$competition->id,'ticket_code'=>'MANUAL-'.$number,'full_name'=>'Tim Manual '.$number,
            'whatsapp'=>'08126666'.str_pad($number,4,'0',STR_PAD_LEFT),'email'=>'manual'.$number.'@test.id',
            'school_name'=>'Sekolah Manual '.$number,'consent'=>true,'status'=>'approved',
        ]));
        $ids=$registrations->pluck('id')->all();
        $slots=[$ids[2],null,$ids[0],$ids[5],$ids[1],null,$ids[4],$ids[3]];

        $single=$this->withToken('admin-manual-drawing-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'manual','format'=>'single_elimination','manual_slots'=>$slots,'avoid_same_school'=>true,
        ])->assertCreated()->assertJsonCount(8,'entries')
            ->assertJsonPath('entries.0.registration_id',$ids[2])
            ->assertJsonPath('entries.1.registration_id',null)
            ->assertJsonPath('entries.2.registration_id',$ids[0])
            ->assertJsonPath('entries.3.registration_id',$ids[5]);
        $this->assertSame($slots,collect($single->json('entries'))->pluck('registration_id')->all());

        $invalidSlots=$slots;
        $invalidSlots[7]=$ids[2];
        $this->withToken('admin-manual-drawing-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'manual','format'=>'single_elimination','manual_slots'=>$invalidSlots,
        ])->assertUnprocessable()->assertJsonPath('message','Slot manual harus memuat setiap peserta tepat satu kali; slot lainnya boleh BYE.');

        $this->withToken('admin-manual-drawing-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'manual','format'=>'double_elimination','manual_slots'=>$slots,
        ])->assertCreated()->assertJsonPath('entries.0.registration_id',$ids[2]);

        $groups=[[$ids[0],$ids[3],$ids[4]],[$ids[1],$ids[2],$ids[5]]];
        $groupDraw=$this->withToken('admin-manual-drawing-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'manual','format'=>'groups_knockout','group_count'=>2,'manual_groups'=>$groups,
        ])->assertCreated()->assertJsonCount(6,'entries');
        $this->assertSame($groups[0],collect($groupDraw->json('entries'))->where('group_name','Grup A')->pluck('registration_id')->values()->all());
        $this->assertSame($groups[1],collect($groupDraw->json('entries'))->where('group_name','Grup B')->pluck('registration_id')->values()->all());
        $this->withToken('admin-manual-drawing-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'manual','format'=>'groups_knockout','group_count'=>2,
            'manual_groups'=>[[$ids[0]],[$ids[1],$ids[2],$ids[3],$ids[4],$ids[5]]],
        ])->assertUnprocessable()->assertJsonPath('message','Setiap grup manual harus berisi minimal dua peserta.');

        $reverse=array_reverse($ids);
        $this->withToken('admin-manual-drawing-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'manual','format'=>'round_robin','manual_order'=>$reverse,
        ])->assertCreated()->assertJsonPath('entries.0.registration_id',$reverse[0]);
        $this->withToken('admin-manual-drawing-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'manual','format'=>'round_robin_full','manual_order'=>$ids,
            'host_ids'=>[$ids[5]],'host_policy'=>'first',
        ])->assertCreated()->assertJsonPath('entries.0.registration_id',$ids[0]);
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
        $startsAt='2030-01-15 08:00:00';

        $this->withToken('pic-jadwal-token')->putJson('/api/manage/schedules/matches/'.$matches[0]->id,[
            'scheduled_at'=>$startsAt,'venue'=>'Lapangan Utama','duration_minutes'=>60,'status'=>'upcoming','notify'=>true,
        ])->assertOk();
        $this->assertDatabaseHas('tournament_matches',['id'=>$matches[0]->id,'scheduled_at'=>'2030-01-15 01:00:00']);
        $this->assertDatabaseHas('competition_notifications',['competition_id'=>$competition->id,'title'=>'Pembaruan Jadwal Match '.$matches[0]->match_number]);

        $this->withToken('pic-jadwal-token')->putJson('/api/manage/schedules/matches/'.$matches[1]->id,[
            'scheduled_at'=>$startsAt,'venue'=>'Lapangan Utama','duration_minutes'=>60,'status'=>'upcoming',
        ])->assertUnprocessable()->assertJsonPath('message','Jadwal berbenturan.');
        $this->withToken('pic-jadwal-token')->putJson('/api/manage/schedules/matches/'.$matches[1]->id,[
            'scheduled_at'=>$startsAt,'venue'=>'Lapangan Utama','duration_minutes'=>60,'status'=>'upcoming','force'=>true,
        ])->assertOk()->assertJsonCount(1,'conflicts');

        $this->withToken('pic-jadwal-token')->postJson('/api/manage/schedules/competitions/'.$competition->id.'/blocks',[
            'title'=>'Istirahat','venue'=>'Lapangan 2','starts_at'=>'2030-01-15 12:00:00','duration_minutes'=>60,
        ])->assertCreated()->assertJsonCount(1,'blocks');
        $this->assertDatabaseHas('tournament_schedule_blocks',['competition_id'=>$competition->id,'starts_at'=>'2030-01-15 05:00:00']);
        $this->withToken('pic-jadwal-token')->putJson('/api/manage/schedules/matches/'.$matches[0]->id,[
            'scheduled_at'=>$startsAt,'venue'=>'Lapangan Utama','duration_minutes'=>60,'status'=>'ongoing','force'=>true,
        ])->assertOk()->assertJsonFragment(['id'=>$matches[0]->id,'status'=>'ongoing']);
        $this->withToken('pic-jadwal-token')->putJson('/api/manage/schedules/matches/'.$matches[0]->id,[
            'scheduled_at'=>$startsAt,'venue'=>'Lapangan Utama','duration_minutes'=>60,'status'=>'completed',
            'score_a'=>3,'score_b'=>1,'force'=>true,
        ])->assertOk()->assertJsonFragment(['id'=>$matches[0]->id,'status'=>'completed']);
        $this->assertDatabaseHas('tournament_matches',[
            'id'=>$matches[0]->id,'status'=>'completed','score_a'=>3,'score_b'=>1,'winner_id'=>$matches[0]->participant_a_id,
        ]);
        $tvFeed=$this->getJson('/api/competitions/'.$competition->slug.'/schedule')->assertOk()
            ->assertJsonPath('draw.id',$draw->json('id'))->assertJsonPath('timezone','Asia/Jakarta')
            ->assertJsonPath('timezone_label','WIB')->assertJsonPath('utc_offset','+07:00')->assertJsonCount(1,'blocks');
        $tvResult=collect($tvFeed->json('matches'))->firstWhere('id',$matches[0]->id);
        $tvNext=collect($tvFeed->json('matches'))->firstWhere('id',$matches[1]->id);
        $this->assertSame('completed',$tvResult['status']);
        $this->assertSame(3,$tvResult['score_a']);
        $this->assertSame(1,$tvResult['score_b']);
        $this->assertSame($matches[0]->participant_a_id,$tvResult['winner_id']);
        $this->assertSame('upcoming',$tvNext['status']);
        $this->assertNotNull($tvNext['scheduled_at']);
    }

    public function test_panitia_generates_automatic_schedule_in_wib_with_gap_capacity_and_status_protection(): void
    {
        $competition=$this->competition();
        $competition->update(['schedule_venues'=>['Lapangan 1','Lapangan 2']]);
        User::create(['name'=>'PIC Jadwal Otomatis','email'=>'pic-jadwal-otomatis@test.id','password'=>'password123','role'=>'pic','competition_id'=>$competition->id,'api_token'=>hash('sha256','pic-jadwal-otomatis-token')]);
        foreach(range(1,4) as $number) Registration::create([
            'competition_id'=>$competition->id,'ticket_code'=>'AUTO-SCHEDULE-'.$number,'full_name'=>'Peserta Otomatis '.$number,
            'whatsapp'=>'08128888'.str_pad($number,4,'0',STR_PAD_LEFT),'email'=>'auto-schedule'.$number.'@test.id','birth_place'=>'Jakarta','birth_date'=>'2009-01-01',
            'grade'=>'XI','nisn'=>(string)(7100000000+$number),'mother_name'=>'Ibu','school_name'=>'Sekolah Otomatis '.$number,
            'teacher_name'=>'Guru','teacher_contact'=>'081298765432','student_card_path'=>'a.pdf','delegation_letter_path'=>'b.pdf','photo_path'=>'c.jpg','consent'=>true,'status'=>'approved',
        ]);
        $participantIds=Registration::where('competition_id',$competition->id)->orderBy('id')->pluck('id')->all();
        $draw=$this->withToken('pic-jadwal-otomatis-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'mode'=>'manual','format'=>'round_robin','manual_order'=>$participantIds,
        ])->assertCreated();
        $this->withToken('pic-jadwal-otomatis-token')->postJson('/api/manage/schedules/competitions/'.$competition->id.'/blocks',[
            'title'=>'Persiapan Lapangan','venue'=>'Lapangan 1','starts_at'=>'2030-02-10 08:00:00','duration_minutes'=>60,
        ])->assertCreated();

        $payload=[
            'start_date'=>'2030-02-10','start_time'=>'08:00','end_time'=>'17:00',
            'duration_minutes'=>60,'gap_minutes'=>30,'max_days'=>1,
            'venues'=>['Lapangan 1','Lapangan 2'],'notify'=>true,
        ];
        $this->withToken('pic-jadwal-otomatis-token')->postJson('/api/manage/schedules/competitions/'.$competition->id.'/generate',$payload)
            ->assertOk()->assertJsonPath('automation.scheduled_count',6)->assertJsonPath('automation.waiting_count',0)
            ->assertJsonPath('timezone','Asia/Jakarta')->assertJsonPath('timezone_label','WIB')->assertJsonCount(0,'conflicts');

        $matches=TournamentMatch::where('tournament_draw_id',$draw->json('id'))->orderBy('match_number')->get();
        $this->assertCount(6,$matches);
        $this->assertSame('2030-02-10 01:00:00',$matches->min('scheduled_at')->format('Y-m-d H:i:s'));
        $this->assertFalse($matches->contains(fn($match)=>$match->venue==='Lapangan 1' && $match->scheduled_at->format('Y-m-d H:i:s')==='2030-02-10 01:00:00'));
        foreach($matches as $match) {
            $this->assertNotNull($match->scheduled_at);
            $this->assertContains($match->venue,['Lapangan 1','Lapangan 2']);
            $this->assertSame('upcoming',$match->status);
        }
        foreach($matches as $index=>$match) foreach($matches->slice($index+1) as $other) {
            $shared=array_intersect([$match->participant_a_id,$match->participant_b_id],[$other->participant_a_id,$other->participant_b_id]);
            if(!$shared) continue;
            $earlier=$match->scheduled_at->lte($other->scheduled_at) ? $match : $other;
            $later=$earlier->is($match) ? $other : $match;
            $this->assertGreaterThanOrEqual(
                $earlier->scheduled_at->copy()->addMinutes($earlier->duration_minutes+30)->timestamp,
                $later->scheduled_at->timestamp,
                'Peserta yang sama harus memperoleh jeda minimal 30 menit.'
            );
        }
        $this->assertDatabaseHas('competition_notifications',['competition_id'=>$competition->id,'title'=>'Jadwal Pertandingan Telah Dibuat']);

        $protected=$matches->first();
        $protected->update(['status'=>'check_in']);
        $protectedAt=$protected->scheduled_at->format('Y-m-d H:i:s');
        $this->withToken('pic-jadwal-otomatis-token')->postJson('/api/manage/schedules/competitions/'.$competition->id.'/generate',[
            ...$payload,'start_date'=>'2030-02-11','replace_existing'=>true,'notify'=>false,
        ])->assertOk()->assertJsonPath('automation.scheduled_count',5);
        $this->assertDatabaseHas('tournament_matches',['id'=>$protected->id,'status'=>'check_in','scheduled_at'=>$protectedAt]);

        $beforeFailure=TournamentMatch::where('tournament_draw_id',$draw->json('id'))->pluck('scheduled_at','id')->map(fn($value)=>(string)$value)->all();
        $this->withToken('pic-jadwal-otomatis-token')->postJson('/api/manage/schedules/competitions/'.$competition->id.'/generate',[
            ...$payload,'start_date'=>'2030-02-12','end_time'=>'09:00','venues'=>['Lapangan 1'],'replace_existing'=>true,'notify'=>false,
        ])->assertUnprocessable()->assertJsonPath('message','Kapasitas jadwal tidak cukup. Tambah jumlah hari/lapangan, perpanjang jam operasional, atau kurangi durasi dan jeda pertandingan.');
        $afterFailure=TournamentMatch::where('tournament_draw_id',$draw->json('id'))->pluck('scheduled_at','id')->map(fn($value)=>(string)$value)->all();
        $this->assertSame($beforeFailure,$afterFailure);
    }

    public function test_drawing_bracket_and_schedule_are_isolated_for_each_city_session(): void
    {
        $competition=$this->competition();
        $admin=User::create(['name'=>'Admin Multi Kota','email'=>'admin-multi-kota@test.id','password'=>'password123','role'=>'super_admin','api_token'=>hash('sha256','admin-multi-kota-token')]);
        $bogor=$competition->sessions()->create([
            'city'=>'Bogor','venue'=>'Kampus BSI Bogor','activity_start_date'=>'2030-03-01','activity_end_date'=>'2030-03-03',
            'competition_start_date'=>'2030-03-01','competition_end_date'=>'2030-03-03','quota'=>16,'sort_order'=>1,'is_active'=>true,
        ]);
        $jakarta=$competition->sessions()->create([
            'city'=>'Jakarta','venue'=>'Kampus BSI Jakarta','activity_start_date'=>'2030-04-01','activity_end_date'=>'2030-04-03',
            'competition_start_date'=>'2030-04-01','competition_end_date'=>'2030-04-03','quota'=>16,'sort_order'=>2,'is_active'=>true,
        ]);
        foreach([[$bogor,'BOGOR'],[$jakarta,'JAKARTA']] as [$session,$prefix])foreach(range(1,4) as $number)Registration::create([
            'competition_id'=>$competition->id,'competition_session_id'=>$session->id,'ticket_code'=>$prefix.'-'.$number,
            'full_name'=>'Tim '.$prefix.' '.$number,'whatsapp'=>'08126666'.str_pad($session->id.$number,4,'0',STR_PAD_LEFT),
            'email'=>strtolower($prefix).$number.'@multi-kota.test','birth_place'=>'Bogor','birth_date'=>'2009-01-01','grade'=>'XI',
            'nisn'=>(string)(7200000000+$session->id*10+$number),'mother_name'=>'Ibu','school_name'=>'Sekolah '.$prefix,
            'teacher_name'=>'Guru','teacher_contact'=>'081298765432','student_card_path'=>'a.pdf','delegation_letter_path'=>'b.pdf','photo_path'=>'c.jpg','consent'=>true,'status'=>'approved',
        ]);

        $manage=$this->withToken('admin-multi-kota-token')->getJson('/api/manage/tournaments')->assertOk()->assertJsonCount(2,'scopes');
        $this->assertSame($bogor->id,$manage->json('session.id'));
        $bogorDraw=$this->withToken('admin-multi-kota-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'competition_session_id'=>$bogor->id,'mode'=>'random','format'=>'single_elimination',
        ])->assertCreated()->assertJsonPath('competition_session_id',$bogor->id)->assertJsonPath('version',1);
        $this->withToken('admin-multi-kota-token')->postJson('/api/manage/tournaments/draws/'.$bogorDraw->json('id').'/lock')->assertOk();
        $jakartaDraw=$this->withToken('admin-multi-kota-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw',[
            'competition_session_id'=>$jakarta->id,'mode'=>'random','format'=>'single_elimination',
        ])->assertCreated()->assertJsonPath('competition_session_id',$jakarta->id)->assertJsonPath('version',1);
        $this->withToken('admin-multi-kota-token')->postJson('/api/manage/tournaments/draws/'.$jakartaDraw->json('id').'/lock')->assertOk();

        $bogorEntryIds=collect($bogorDraw->json('entries'))->pluck('registration_id')->filter();
        $jakartaEntryIds=collect($jakartaDraw->json('entries'))->pluck('registration_id')->filter();
        $this->assertEmpty($bogorEntryIds->intersect($jakartaEntryIds));
        $this->assertTrue($bogorEntryIds->every(fn($id)=>Registration::find($id)->competition_session_id===$bogor->id));
        $this->assertTrue($jakartaEntryIds->every(fn($id)=>Registration::find($id)->competition_session_id===$jakarta->id));
        $this->withToken('admin-multi-kota-token')->getJson('/api/manage/tournaments?competition_id='.$competition->id.'&session_id='.$jakarta->id)
            ->assertOk()->assertJsonPath('draw.id',$jakartaDraw->json('id'))->assertJsonPath('session.city','Jakarta');

        foreach([[$bogor,$bogorDraw,'Lapangan Bogor','2030-03-01'],[$jakarta,$jakartaDraw,'Lapangan Jakarta','2030-04-01']] as [$session,$draw,$field,$date]){
            $this->withToken('admin-multi-kota-token')->putJson('/api/manage/schedules/competitions/'.$competition->id.'/venues',[
                'competition_session_id'=>$session->id,'venues'=>[$field],
            ])->assertOk()->assertJsonPath('session.id',$session->id);
            $this->withToken('admin-multi-kota-token')->postJson('/api/manage/schedules/competitions/'.$competition->id.'/generate',[
                'competition_session_id'=>$session->id,'start_date'=>$date,'start_time'=>'08:00','end_time'=>'12:00',
                'duration_minutes'=>60,'gap_minutes'=>0,'max_days'=>1,'venues'=>[$field],'notify'=>true,
            ])->assertOk()->assertJsonPath('automation.scheduled_count',2)->assertJsonPath('session.id',$session->id);
            $this->assertDatabaseHas('competition_notifications',['competition_id'=>$competition->id,'competition_session_id'=>$session->id,'title'=>'Jadwal Pertandingan Telah Dibuat']);
        }

        $this->getJson('/api/competitions/'.$competition->slug.'/tournament?session_id='.$bogor->id)
            ->assertOk()->assertJsonPath('session.city','Bogor')->assertJsonPath('draw.id',$bogorDraw->json('id'));
        $bogorSchedule=$this->getJson('/api/competitions/'.$competition->slug.'/schedule?session_id='.$bogor->id)
            ->assertOk()->assertJsonPath('session.city','Bogor');
        $jakartaSchedule=$this->getJson('/api/competitions/'.$competition->slug.'/schedule?session_id='.$jakarta->id)
            ->assertOk()->assertJsonPath('session.city','Jakarta');
        $this->assertTrue(collect($bogorSchedule->json('matches'))->whereNotNull('scheduled_at')->every(fn($match)=>$match['venue']==='Lapangan Bogor'));
        $this->assertTrue(collect($jakartaSchedule->json('matches'))->whereNotNull('scheduled_at')->every(fn($match)=>$match['venue']==='Lapangan Jakarta'));
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

    public function test_registration_list_supports_paging_and_page_size(): void
    {
        $competition=$this->competition();
        User::create(['name'=>'Admin Paging','email'=>'admin-paging@test.id','password'=>'password123','role'=>'super_admin','api_token'=>hash('sha256','admin-paging-token')]);
        foreach(range(1,23) as $number) Registration::create([
            'competition_id'=>$competition->id,'ticket_code'=>'PAGING-'.str_pad($number,3,'0',STR_PAD_LEFT),'full_name'=>'Peserta Paging '.$number,
            'whatsapp'=>'08129999'.str_pad($number,4,'0',STR_PAD_LEFT),'email'=>'paging'.$number.'@test.id','birth_place'=>'Jakarta','birth_date'=>'2009-01-01',
            'grade'=>'XI','nisn'=>(string)(7200000000+$number),'mother_name'=>'Ibu','school_name'=>'Sekolah Paging',
            'teacher_name'=>'Guru','teacher_contact'=>'081298765432','student_card_path'=>'a.pdf','delegation_letter_path'=>'b.pdf','photo_path'=>'c.jpg','consent'=>true,
        ]);

        $this->withToken('admin-paging-token')->getJson('/api/manage/registrations?per_page=10&page=2')
            ->assertOk()->assertJsonCount(10,'data')->assertJsonPath('current_page',2)
            ->assertJsonPath('per_page',10)->assertJsonPath('last_page',3)
            ->assertJsonPath('from',11)->assertJsonPath('to',20)->assertJsonPath('total',23);
        $this->withToken('admin-paging-token')->getJson('/api/manage/registrations?per_page=50&page=1')
            ->assertOk()->assertJsonCount(23,'data')->assertJsonPath('last_page',1)->assertJsonPath('total',23);
        $this->withToken('admin-paging-token')->getJson('/api/manage/registrations?per_page=15')
            ->assertUnprocessable()->assertJsonValidationErrors(['per_page']);
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

        $this->withToken('pic-format-token')->patchJson('/api/manage/competitions/'.$assigned->id.'/format', [
            'participation_type'=>'team', 'team_size'=>4, 'official_count'=>2,
        ])->assertOk()->assertJsonPath('team_size', 4)->assertJsonPath('official_count', 2);
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

    public function test_large_team_players_and_documents_can_be_saved_across_multiple_sessions(): void
    {
        Storage::fake('public');
        $competition = $this->competition();
        $competition->update([
            'participation_type'=>'team',
            'team_size'=>12,
            'official_count'=>2,
            'team_update_deadline_at'=>now()->addDay(),
            'document_upload_deadline_at'=>now()->addDays(2),
            'fee'=>0,
        ]);

        $this->postJson('/api/registrations', [
            'competition_id'=>$competition->id,
            'full_name'=>'Ketua Tim Dua Belas',
            'school_name'=>'SMA Tim Besar',
            'email'=>'ketua-dua-belas@test.id',
            'whatsapp'=>'081234567800',
            'password'=>'password123',
            'password_confirmation'=>'password123',
            'consent'=>true,
        ])->assertCreated();

        $registration = Registration::firstOrFail();
        User::where('email', 'ketua-dua-belas@test.id')->firstOrFail()
            ->update(['api_token'=>hash('sha256', 'large-team-token')]);

        $this->withToken('large-team-token')->putJson('/api/participant/registrations/'.$registration->id.'/team-profile', [
            'team_name'=>'Tim Dua Belas',
            'school_name'=>'SMA Tim Besar',
            'school_city'=>'Jakarta',
            'school_address'=>'Jl. Tim Besar No. 12',
        ])->assertOk()
            ->assertJsonPath('registration.completion_progress.team_size', 12)
            ->assertJsonPath('registration.completion_progress.saved_members', 0)
            ->assertJsonPath('registration.completion_progress.school_profile_complete', true)
            ->assertJsonPath('registration.completion_progress.teacher_complete', false)
            ->assertJsonPath('registration.completion_progress.team_profile_complete', false);

        $this->withToken('large-team-token')->putJson('/api/participant/registrations/'.$registration->id.'/teacher', [
            'teacher_name'=>'Guru Tim Besar',
            'teacher_contact'=>'081298765400',
        ])->assertOk()
            ->assertJsonPath('registration.completion_progress.teacher_complete', true)
            ->assertJsonPath('registration.completion_progress.team_profile_complete', true)
            ->assertJsonPath('registration.completion_progress.official_saved', 0);

        $this->withToken('large-team-token')->putJson('/api/participant/registrations/'.$registration->id.'/official-slots/2', [
            'full_name'=>'Official Kedua',
            'position'=>'Asisten Pelatih',
            'whatsapp'=>'081298765402',
        ])->assertOk()
            ->assertJsonPath('registration.completion_progress.official_saved', 1)
            ->assertJsonPath('registration.completion_progress.official_slots.0.complete', false)
            ->assertJsonPath('registration.completion_progress.official_slots.1.name', 'Official Kedua');

        $memberOne = [
            'full_name'=>'Pemain Pertama', 'email'=>'pemain-01@test.id', 'whatsapp'=>'081234567801',
            'nisn'=>'7000000001', 'birth_place'=>'Jakarta', 'birth_date'=>'2009-01-01',
            'grade'=>'XI', 'mother_name'=>'Ibu Pemain Pertama',
        ];
        $memberTwelve = [
            'full_name'=>'Pemain Kedua Belas', 'email'=>'pemain-12@test.id', 'whatsapp'=>'081234567812',
            'nisn'=>'7000000012', 'birth_place'=>'Bogor', 'birth_date'=>'2009-12-01',
            'grade'=>'X', 'mother_name'=>'Ibu Pemain Kedua Belas',
        ];

        $this->withToken('large-team-token')->putJson('/api/participant/registrations/'.$registration->id.'/member-slots/1', $memberOne)
            ->assertOk()
            ->assertJsonPath('registration.completion_progress.saved_members', 1)
            ->assertJsonPath('registration.completion_progress.member_slots.0.name', 'Pemain Pertama');
        $this->withToken('large-team-token')->putJson('/api/participant/registrations/'.$registration->id.'/member-slots/12', $memberTwelve)
            ->assertOk()
            ->assertJsonPath('registration.completion_progress.saved_members', 2)
            ->assertJsonPath('registration.completion_progress.member_slots.11.data_complete', true);

        $resumed = $this->withToken('large-team-token')->getJson('/api/participant/registrations')
            ->assertOk()
            ->assertJsonPath('0.completion_progress.saved_members', 2)
            ->assertJsonPath('0.completion_progress.documented_members', 0);
        $firstMemberId = $resumed->json('0.completion_progress.member_slots.0.member_id');

        $this->withToken('large-team-token')->post('/api/participant/registrations/'.$registration->id.'/member-slots/6/documents')
            ->assertUnprocessable()
            ->assertJsonPath('errors.documents.0', 'Pilih minimal satu file: kartu pelajar atau pas foto.');
        $this->assertDatabaseMissing('registration_members', ['registration_id'=>$registration->id, 'member_order'=>6]);

        $this->withToken('large-team-token')->post('/api/participant/registrations/'.$registration->id.'/member-slots/5/documents', [
            'student_card'=>UploadedFile::fake()->create('kartu-pemain-5.pdf', 100, 'application/pdf'),
        ])->assertOk()
            ->assertJsonPath('registration.completion_progress.saved_members', 2)
            ->assertJsonPath('registration.completion_progress.member_slots.4.student_card_uploaded', true)
            ->assertJsonPath('registration.completion_progress.member_slots.4.data_complete', false);

        $this->withToken('large-team-token')->post('/api/participant/registrations/'.$registration->id.'/members/'.$firstMemberId.'/documents', [
            'student_card'=>UploadedFile::fake()->create('kartu-pemain-1.pdf', 100, 'application/pdf'),
        ])->assertOk()
            ->assertJsonPath('registration.completion_progress.member_slots.0.student_card_uploaded', true)
            ->assertJsonPath('registration.completion_progress.member_slots.0.photo_uploaded', false)
            ->assertJsonPath('registration.completion_progress.documented_members', 0);

        $this->withToken('large-team-token')->post('/api/participant/registrations/'.$registration->id.'/members/'.$firstMemberId.'/documents', [
            'photo'=>UploadedFile::fake()->create('foto-pemain-1.jpg', 100, 'image/jpeg'),
        ])->assertOk()
            ->assertJsonPath('registration.completion_progress.documented_members', 1)
            ->assertJsonPath('team_completed', false);

        $this->withToken('large-team-token')->post('/api/participant/registrations/'.$registration->id.'/documents', [
            'school_logo'=>UploadedFile::fake()->create('logo.png', 100, 'image/png'),
        ])->assertOk()
            ->assertJsonPath('registration.documents_completed_at', null)
            ->assertJsonPath('registration.completion_progress.shared_documents.uploaded', 1);
        $this->withToken('large-team-token')->post('/api/participant/registrations/'.$registration->id.'/documents', [
            'statement_letter'=>UploadedFile::fake()->create('pernyataan.pdf', 100, 'application/pdf'),
        ])->assertOk()
            ->assertJsonPath('registration.completion_progress.shared_documents.uploaded', 2);
        $this->withToken('large-team-token')->post('/api/participant/registrations/'.$registration->id.'/documents', [
            'school_recommendation_letter'=>UploadedFile::fake()->create('rekomendasi.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertOk()
            ->assertJsonPath('registration.completion_progress.shared_documents.complete', true);

        $this->assertNotNull($registration->fresh()->documents_completed_at);
        $this->assertDatabaseHas('registration_members', [
            'registration_id'=>$registration->id,
            'member_order'=>12,
            'full_name'=>'Pemain Kedua Belas',
        ]);
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

    public function test_pic_and_spv_accounts_are_saved_without_competition_assignment(): void
    {
        $competition = $this->competition();
        User::create([
            'name'=>'Admin Akun Kota','email'=>'admin-akun-kota@test.id','password'=>'password123',
            'role'=>'super_admin','api_token'=>hash('sha256','admin-akun-kota-token'),
        ]);

        $picResponse = $this->withToken('admin-akun-kota-token')->postJson('/api/manage/accounts', [
            'name'=>'PIC Belum Ditugaskan','email'=>'pic-belum-ditugaskan@test.id','whatsapp'=>'081234567820',
            'password'=>'password123','role'=>'pic','competition_id'=>$competition->id,'is_active'=>true,
        ])->assertCreated()->assertJsonPath('competition_id', null);
        $spvResponse = $this->withToken('admin-akun-kota-token')->postJson('/api/manage/accounts', [
            'name'=>'SPV Belum Ditugaskan','email'=>'spv-belum-ditugaskan@test.id','whatsapp'=>'081234567821',
            'password'=>'password123','role'=>'spv','competition_id'=>$competition->id,'is_active'=>true,
        ])->assertCreated()->assertJsonPath('competition_id', null);

        $this->assertDatabaseHas('users', ['id'=>$picResponse->json('id'),'role'=>'pic','competition_id'=>null]);
        $this->assertDatabaseHas('users', ['id'=>$spvResponse->json('id'),'role'=>'spv','competition_id'=>null]);

        $this->withToken('admin-akun-kota-token')->putJson('/api/manage/accounts/'.$picResponse->json('id'), [
            'name'=>'PIC Belum Ditugaskan','email'=>'pic-belum-ditugaskan@test.id','whatsapp'=>'081234567820',
            'password'=>'','role'=>'pic','competition_id'=>$competition->id,'is_active'=>true,
        ])->assertOk()->assertJsonPath('competition_id', null);
    }

    public function test_selecting_campus_does_not_automatically_assign_pic_or_spv(): void
    {
        User::create([
            'name'=>'Admin Pilih Petugas','email'=>'admin-pilih-petugas@test.id','password'=>'password123',
            'role'=>'super_admin','api_token'=>hash('sha256','admin-pilih-petugas-token'),
        ]);
        $pic=User::create(['name'=>'PIC Bawaan Kampus','email'=>'pic-bawaan-kampus@test.id','whatsapp'=>'081234567830','password'=>'password123','role'=>'pic']);
        $supervisor=User::create(['name'=>'SPV Bawaan Kampus','email'=>'spv-bawaan-kampus@test.id','whatsapp'=>'081234567831','password'=>'password123','role'=>'spv']);
        $venue=CompetitionVenue::create([
            'slug'=>'kampus-pilih-manual','name'=>'Kampus Pilih Manual','city'=>'Bekasi','address'=>'Jl. Pendidikan',
            'activity_start_date'=>'2030-03-10','activity_end_date'=>'2030-03-11',
            'pic_user_id'=>$pic->id,'supervisor_user_id'=>$supervisor->id,'is_active'=>true,
        ]);
        $payload=[
            'title'=>'Lomba Pilih Petugas Manual','category'=>'Science Competition',
            'short_description'=>'Petugas dipilih langsung oleh pengguna.','description'=>'Pemilihan kampus tidak menentukan petugas lomba.',
            'guides'=>[['title'=>'Panduan','content'=>'Ikuti ketentuan yang berlaku.']],
            'participation_type'=>'individual','team_size'=>1,'official_count'=>0,
            'sessions'=>[[
                'venue_id'=>$venue->id,'quota'=>20,'fee'=>0,'team_update_deadline_at'=>'2030-03-01 23:59:00',
                'timeline'=>[['label'=>'Pelaksanaan','type'=>'single','date'=>'2030-03-10']],
                'is_active'=>true,
            ]],
        ];

        $this->withToken('admin-pilih-petugas-token')->postJson('/api/manage/competitions',array_replace_recursive($payload,[
            'sessions'=>[array_merge($payload['sessions'][0],['pic_slots'=>1,'supervisor_slots'=>1,'pic_ids'=>[],'supervisor_ids'=>[]])],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['sessions.0.pic_ids','sessions.0.supervisor_ids']);

        $response=$this->withToken('admin-pilih-petugas-token')->postJson('/api/manage/competitions',$payload)
            ->assertCreated()->assertJsonPath('sessions.0.pic_user_id',null)
            ->assertJsonPath('sessions.0.supervisor_user_id',null)
            ->assertJsonCount(0,'sessions.0.pics')->assertJsonCount(0,'sessions.0.supervisors');
        $this->assertDatabaseHas('competition_sessions',[
            'competition_id'=>$response->json('id'),'venue_id'=>$venue->id,
            'pic_user_id'=>null,'supervisor_user_id'=>null,
        ]);
        $this->assertDatabaseMissing('competition_session_staff',['competition_session_id'=>$response->json('sessions.0.id')]);
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
        CompetitionSession::create([
            'competition_id'=>$competition->id,'venue_id'=>$venue->id,'city'=>'Kota Bogor','venue'=>$venue->name,
            'activity_start_date'=>now()->addDays(12),'activity_end_date'=>now()->addDays(13),
            'competition_start_date'=>now()->addDays(12),'competition_end_date'=>now()->addDays(13),
            'is_active'=>false,
        ]);

        $this->withToken('admin-venue-token')->deleteJson('/api/manage/venues/'.$venue->id)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Tempat masih digunakan pada sesi lomba. Nonaktifkan tempat atau pindahkan sesi terlebih dahulu.');

        $this->withToken('admin-venue-token')->getJson('/api/manage/venues')
            ->assertOk()
            ->assertJsonPath('0.sessions_count', 2)
            ->assertJsonPath('0.active_sessions_count', 1)
            ->assertJsonPath('0.inactive_sessions_count', 1);
        $this->getJson('/api/venues')->assertOk()->assertJsonPath('0.city', 'Kota Bogor');
        $this->getJson('/api/venues/'.$venue->fresh()->slug)
            ->assertOk()
            ->assertJsonCount(1, 'sessions')
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

        $session = $competition->sessions()->firstOrFail();
        $this->withToken('admin-kota-token')->getJson('/api/manage/venues?with_assignments=1')
            ->assertOk()
            ->assertJsonPath('0.sessions.0.competition.title', 'Futsal Putra Kota')
            ->assertJsonCount(2, '0.sessions.0.pics')
            ->assertJsonCount(2, '0.sessions.0.supervisors');
        $this->withToken('admin-kota-token')->putJson('/api/manage/venues/'.$venue->id.'/staff-assignments', [
            'assignments'=>[[
                'session_id'=>$session->id,
                'pic_slots'=>1,
                'supervisor_slots'=>1,
                'pic_ids'=>[$picSecond->id],
                'supervisor_ids'=>[$supervisorSecond->id],
            ]],
        ])->assertOk()
            ->assertJsonPath('sessions.0.pic_user_id', $picSecond->id)
            ->assertJsonPath('sessions.0.supervisor_user_id', $supervisorSecond->id)
            ->assertJsonCount(1, 'sessions.0.pics')
            ->assertJsonCount(1, 'sessions.0.supervisors');
        $this->assertDatabaseHas('competition_sessions', [
            'id'=>$session->id,
            'pic_user_id'=>$picSecond->id,
            'supervisor_user_id'=>$supervisorSecond->id,
            'pic_slots'=>1,
            'supervisor_slots'=>1,
        ]);
        $this->assertDatabaseMissing('competition_session_staff', [
            'competition_session_id'=>$session->id,
            'user_id'=>$pic->id,
        ]);
    }

    public function test_same_pic_and_spv_can_be_assigned_to_multiple_places_and_competitions(): void
    {
        User::create([
            'name'=>'Admin Multi Lokasi','email'=>'admin-multi-lokasi@test.id','password'=>'password123',
            'role'=>'super_admin','api_token'=>hash('sha256','admin-multi-lokasi-token'),
        ]);
        $pic = User::create([
            'name'=>'PIC Multi Lokasi','email'=>'pic-multi-lokasi@test.id','whatsapp'=>'081234567810',
            'password'=>'password123','role'=>'pic','competition_id'=>null,'is_active'=>true,
            'api_token'=>hash('sha256','pic-multi-lokasi-token'),
        ]);
        $supervisor = User::create([
            'name'=>'SPV Multi Lokasi','email'=>'spv-multi-lokasi@test.id','whatsapp'=>'081234567811',
            'password'=>'password123','role'=>'spv','competition_id'=>null,'is_active'=>true,
            'api_token'=>hash('sha256','spv-multi-lokasi-token'),
        ]);

        $venues = collect([
            ['slug'=>'makassar-multi','name'=>'Kampus BSI Makassar','city'=>'Makassar'],
            ['slug'=>'bogor-multi','name'=>'Kampus BSI Bogor','city'=>'Bogor'],
            ['slug'=>'bekasi-multi','name'=>'Kampus BSI Bekasi','city'=>'Bekasi'],
        ])->map(fn (array $venue) => CompetitionVenue::create($venue + [
            'address'=>'Jl. Pendidikan',
            'activity_start_date'=>'2027-01-13',
            'activity_end_date'=>'2027-01-19',
            'pic_user_id'=>$pic->id,
            'supervisor_user_id'=>$supervisor->id,
            'is_active'=>true,
        ]));

        $sessionPayload = fn (CompetitionVenue $venue) => [
            'venue_id'=>$venue->id,
            'pic_slots'=>1,
            'supervisor_slots'=>1,
            'pic_ids'=>[$pic->id],
            'supervisor_ids'=>[$supervisor->id],
            'quota'=>30,
            'fee'=>0,
            'team_update_deadline_at'=>'2027-01-10 23:59:00',
            'timeline'=>[
                ['label'=>'Pendaftaran','type'=>'range','start_date'=>'2026-12-01','end_date'=>'2027-01-10'],
                ['label'=>'Pelaksanaan','type'=>'single','date'=>'2027-01-15'],
            ],
            'is_active'=>true,
        ];
        $competitionPayload = fn (string $title, array $sessions) => [
            'title'=>$title,
            'category'=>'Science Competition',
            'short_description'=>'Lomba dengan petugas yang dapat menangani beberapa lokasi.',
            'description'=>'Penugasan PIC dan SPV disimpan per lokasi pelaksanaan.',
            'guides'=>[['title'=>'Panduan','content'=>'Ikuti ketentuan lomba yang berlaku.']],
            'participation_type'=>'individual',
            'team_size'=>1,
            'official_count'=>0,
            'sessions'=>$sessions,
        ];

        $firstResponse = $this->withToken('admin-multi-lokasi-token')->postJson('/api/manage/competitions', $competitionPayload(
            'Olimpiade Multi Kota',
            [$sessionPayload($venues[0]), $sessionPayload($venues[1])],
        ))->assertCreated()->assertJsonCount(2, 'sessions');
        $secondResponse = $this->withToken('admin-multi-lokasi-token')->postJson('/api/manage/competitions', $competitionPayload(
            'Karya Ilmiah Multi Kota',
            [$sessionPayload($venues[0]), $sessionPayload($venues[2])],
        ))->assertCreated()->assertJsonCount(2, 'sessions');

        $firstCompetition = Competition::findOrFail($firstResponse->json('id'));
        $secondCompetition = Competition::findOrFail($secondResponse->json('id'));
        $unrelatedCompetition = $firstCompetition->replicate();
        $unrelatedCompetition->title = 'Lomba Tanpa Penugasan';
        $unrelatedCompetition->slug = 'lomba-tanpa-penugasan';
        $unrelatedCompetition->save();

        $sharedVenueSessions = CompetitionSession::where('venue_id', $venues[0]->id)->orderBy('id')->get();
        $this->assertCount(2, $sharedVenueSessions);
        $this->withToken('admin-multi-lokasi-token')->putJson('/api/manage/venues/'.$venues[0]->id.'/staff-assignments', [
            'assignments'=>$sharedVenueSessions->map(fn (CompetitionSession $session) => [
                'session_id'=>$session->id,
                'pic_slots'=>1,
                'supervisor_slots'=>1,
                'pic_ids'=>[$pic->id],
                'supervisor_ids'=>[$supervisor->id],
            ])->all(),
        ])->assertOk();

        $this->assertSame(4, $pic->assignedCompetitionSessions()->wherePivot('role', 'pic')->count());
        $this->assertSame(4, $supervisor->assignedCompetitionSessions()->wherePivot('role', 'spv')->count());

        foreach (['pic-multi-lokasi-token', 'spv-multi-lokasi-token'] as $token) {
            $competitionsResponse = $this->withToken($token)->getJson('/api/manage/competitions')
                ->assertOk()
                ->assertJsonCount(2);
            $this->assertEqualsCanonicalizing(
                [$firstCompetition->id, $secondCompetition->id],
                collect($competitionsResponse->json())->pluck('id')->all(),
            );
            $this->withToken($token)->getJson('/api/manage/registration-competitions')
                ->assertOk()
                ->assertJsonCount(2);
            $this->withToken($token)->getJson('/api/manage/judging')
                ->assertOk()
                ->assertJsonCount(2, 'competitions');
            $this->withToken($token)->getJson('/api/manage/tournaments')
                ->assertOk()
                ->assertJsonCount(2, 'competitions');
            $this->withToken($token)->getJson('/api/manage/schedules')
                ->assertOk()
                ->assertJsonCount(2, 'competitions');
        }

        $this->withToken('pic-multi-lokasi-token')->postJson('/api/manage/notifications', [
            'competition_id'=>$firstCompetition->id,
            'competition_session_id'=>$firstCompetition->sessions()->where('venue_id', $venues[0]->id)->value('id'),
            'title'=>'Informasi Olimpiade',
            'message'=>'Informasi untuk lokasi Olimpiade yang dipilih.',
        ])->assertCreated();
        $this->withToken('spv-multi-lokasi-token')->postJson('/api/manage/notifications', [
            'competition_id'=>$secondCompetition->id,
            'competition_session_id'=>$secondCompetition->sessions()->where('venue_id', $venues[0]->id)->value('id'),
            'title'=>'Informasi Karya Ilmiah',
            'message'=>'Informasi untuk lokasi karya ilmiah.',
        ])->assertCreated();
        $this->withToken('pic-multi-lokasi-token')->postJson('/api/manage/notifications', [
            'competition_id'=>$unrelatedCompetition->id,
            'title'=>'Tidak Berhak','message'=>'Notifikasi ini harus ditolak.',
        ])->assertForbidden();

        foreach (['pic-multi-lokasi-token', 'spv-multi-lokasi-token'] as $token) {
            $this->withToken($token)->getJson('/api/manage/notifications')
                ->assertOk()
                ->assertJsonCount(2)
                ->assertJsonMissing(['competition_id'=>$unrelatedCompetition->id]);
        }
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
            ->assertUnprocessable()
            ->assertJsonValidationErrors('competition_session_id')
            ->assertJsonPath('error.code', 'UNPROCESSABLE_DATA')
            ->assertJsonPath('error.location.module', 'Pendaftaran peserta')
            ->assertJsonPath('error.fields.0.label', 'Lokasi dan jadwal');

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
