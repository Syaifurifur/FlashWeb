<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->replaceBrand([
            'Kompetisi Kreativitas Siswa Indonesia (KREASI) 2026' => 'BSI Flash 2027',
            'KREASI UNM 2026' => 'BSI FLASH 2027',
            'Kreasi UNM 2026' => 'BSI Flash 2027',
            'KREASI UNM' => 'BSI FLASH 2027',
            'Kreasi UNM' => 'BSI Flash 2027',
            'KREASIUNM2026' => 'BSIFLASH2027',
            'KREASI-' => 'BSIFLASH-',
            'kreasi.nusamandiri.info' => 'bsiflash2027.id',
        ]);

        $this->renameEmail('admin@kreasiunm.id', 'admin@bsiflash2027.id');
        $this->renameEmail('pic@kreasiunm.id', 'pic@bsiflash2027.id');
    }

    public function down(): void
    {
        $this->replaceBrand([
            'BSI FLASH 2027' => 'KREASI UNM 2026',
            'BSI Flash 2027' => 'Kreasi UNM 2026',
            'BSIFLASH2027' => 'KREASIUNM2026',
            'BSIFLASH-' => 'KREASI-',
            'bsiflash2027.id' => 'kreasi.nusamandiri.info',
        ]);

        $this->renameEmail('admin@bsiflash2027.id', 'admin@kreasiunm.id');
        $this->renameEmail('pic@bsiflash2027.id', 'pic@kreasiunm.id');
    }

    private function replaceBrand(array $replacements): void
    {
        $columns = [
            'competitions' => ['title', 'short_description', 'description', 'requirements', 'guides', 'downloadable_documents'],
            'site_contents' => ['content'],
            'competition_notifications' => ['title', 'message'],
        ];

        foreach ($columns as $table => $tableColumns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($tableColumns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)
                    ->select('id', $column)
                    ->whereNotNull($column)
                    ->orderBy('id')
                    ->chunkById(100, function ($rows) use ($table, $column, $replacements) {
                        foreach ($rows as $row) {
                            $updated = str_replace(
                                array_keys($replacements),
                                array_values($replacements),
                                $row->{$column}
                            );

                            if ($updated !== $row->{$column}) {
                                DB::table($table)->where('id', $row->id)->update([$column => $updated]);
                            }
                        }
                    });
            }
        }
    }

    private function renameEmail(string $from, string $to): void
    {
        if (
            Schema::hasTable('users')
            && DB::table('users')->where('email', $from)->exists()
            && !DB::table('users')->where('email', $to)->exists()
        ) {
            DB::table('users')->where('email', $from)->update([
                'email' => $to,
                'updated_at' => now(),
            ]);
        }
    }
};
