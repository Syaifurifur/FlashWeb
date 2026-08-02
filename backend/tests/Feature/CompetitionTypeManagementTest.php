<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionType;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitionTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_manage_competition_types_and_in_use_type_is_protected(): void
    {
        User::create([
            'name' => 'Admin Jenis Lomba',
            'email' => 'admin-jenis@test.id',
            'password' => 'password123',
            'role' => 'super_admin',
            'api_token' => hash('sha256', 'admin-jenis-token'),
        ]);

        $this->withToken('admin-jenis-token')->getJson('/api/manage/competition-types')
            ->assertOk()
            ->assertJsonCount(3);

        $created = $this->withToken('admin-jenis-token')->postJson('/api/manage/competition-types', [
            'name' => 'Futsal Putra',
            'category_group' => 'Sport Competition',
            'description' => 'Kompetisi futsal putra tingkat sekolah.',
            'sort_order' => 4,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('name', 'Futsal Putra')
            ->assertJsonPath('competitions_count', 0);

        $type = CompetitionType::findOrFail($created->json('id'));
        $competition = Competition::create([
            'title' => 'Futsal Test',
            'slug' => 'futsal-test',
            'category' => 'Sport Competition',
            'competition_type_id' => $type->id,
            'short_description' => 'Lomba untuk pengujian jenis.',
            'description' => 'Deskripsi lomba.',
            'quota' => 20,
            'fee' => 0,
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addDays(5),
            'event_date' => now()->addDays(10),
            'location' => 'Bogor',
            'requirements' => [],
            'timeline' => [],
        ]);

        $this->withToken('admin-jenis-token')->putJson('/api/manage/competition-types/'.$type->id, [
            'name' => 'Futsal Sekolah',
            'category_group' => 'Talent Competition',
            'description' => 'Jenis lomba yang telah diperbarui.',
            'sort_order' => 8,
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('name', 'Futsal Sekolah')
            ->assertJsonPath('is_active', false)
            ->assertJsonPath('competitions_count', 1);

        $this->assertSame('Talent Competition', $competition->fresh()->category);

        $this->withToken('admin-jenis-token')->deleteJson('/api/manage/competition-types/'.$type->id)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Jenis lomba masih digunakan. Pindahkan lomba ke jenis lain sebelum menghapusnya.');

        $competition->update(['competition_type_id' => null]);
        $this->withToken('admin-jenis-token')->deleteJson('/api/manage/competition-types/'.$type->id)
            ->assertNoContent();
        $this->assertDatabaseMissing('competition_types', ['id' => $type->id]);
    }

    public function test_public_competition_types_are_ranked_by_real_registration_count(): void
    {
        $type = CompetitionType::where('category_group', 'Sport Competition')->firstOrFail();
        $competition = Competition::create([
            'title' => 'Basket Populer',
            'slug' => 'basket-populer',
            'category' => $type->category_group,
            'competition_type_id' => $type->id,
            'short_description' => 'Kompetisi dengan pendaftar.',
            'description' => 'Deskripsi lomba.',
            'quota' => 20,
            'fee' => 0,
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addDays(5),
            'event_date' => now()->addDays(10),
            'location' => 'Jakarta',
            'requirements' => [],
            'timeline' => [],
        ]);
        Registration::create([
            'competition_id' => $competition->id,
            'ticket_code' => 'BSIFLASH-TYPE01',
            'full_name' => 'Peserta Statistik',
            'whatsapp' => '081234567890',
            'email' => 'statistik@test.id',
            'birth_place' => 'Jakarta',
            'birth_date' => '2009-01-01',
            'grade' => 'XI',
            'nisn' => '9876543210',
            'mother_name' => 'Ibu Peserta',
            'school_name' => 'SMA Statistik',
            'teacher_name' => 'Guru Statistik',
            'teacher_contact' => '081298765432',
            'student_card_path' => 'student.pdf',
            'delegation_letter_path' => 'delegation.pdf',
            'photo_path' => 'photo.jpg',
            'consent' => true,
        ]);

        $this->getJson('/api/competition-types')
            ->assertOk()
            ->assertJsonPath('0.id', $type->id)
            ->assertJsonPath('0.competitions_count', 1)
            ->assertJsonPath('0.registrations_count', 1);
    }
}
