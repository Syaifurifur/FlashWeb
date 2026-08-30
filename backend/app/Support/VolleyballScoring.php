<?php

namespace App\Support;

use App\Models\Competition;
use App\Models\TournamentMatch;

class VolleyballScoring
{
    public const ALLOWED_BEST_OF = [1, 3, 5];

    public static function isCompetition(Competition $competition): bool
    {
        return str_contains(mb_strtolower($competition->slug.' '.$competition->title), 'voli');
    }

    public static function mode(Competition $competition): string
    {
        return self::isCompetition($competition) ? 'volleyball_sets' : 'points';
    }

    public static function apply(TournamentMatch $match, Competition $competition, array $data): array
    {
        if (! self::isCompetition($competition) || ! in_array($data['status'], ['ongoing', 'completed'], true)) return $data;

        $bestOf = (int) ($data['best_of_sets'] ?? $match->best_of_sets ?? 0);
        abort_unless(in_array($bestOf, self::ALLOWED_BEST_OF, true), 422, 'Pilih jumlah set pertandingan: 1, best of 3, atau best of 5.');
        if ($match->best_of_sets && in_array($match->status, ['ongoing', 'completed'], true) && $bestOf !== (int) $match->best_of_sets) {
            abort(422, 'Jumlah set tidak dapat diubah setelah pertandingan dimulai.');
        }

        $rawSets = array_key_exists('set_scores', $data) ? ($data['set_scores'] ?? []) : ($match->set_scores ?? []);
        abort_if(count($rawSets) > $bestOf, 422, "Skor hanya boleh diisi maksimal {$bestOf} set.");
        $requiredWins = intdiv($bestOf, 2) + 1;
        $winsA = 0;
        $winsB = 0;
        $waitingForNextSet = false;
        $matchDecided = false;
        $normalized = [];

        foreach ($rawSets as $index => $set) {
            $scoreA = array_key_exists('score_a', $set) && $set['score_a'] !== null && $set['score_a'] !== '' ? (int) $set['score_a'] : null;
            $scoreB = array_key_exists('score_b', $set) && $set['score_b'] !== null && $set['score_b'] !== '' ? (int) $set['score_b'] : null;
            $completed = (bool) ($set['completed'] ?? false);
            $hasData = $scoreA !== null || $scoreB !== null || $completed;

            abort_if(($waitingForNextSet || $matchDecided) && $hasData, 422, 'Selesaikan set sebelumnya sebelum mengisi set berikutnya.');
            if ($completed) {
                abort_unless($scoreA !== null && $scoreB !== null, 422, 'Skor kedua tim wajib diisi untuk menutup set '.($index + 1).'.');
                abort_if($scoreA === $scoreB, 422, 'Skor set '.($index + 1).' tidak boleh seri.');
                if ($scoreA > $scoreB) $winsA++; else $winsB++;
                $matchDecided = $winsA >= $requiredWins || $winsB >= $requiredWins;
            } elseif ($hasData) {
                $waitingForNextSet = true;
            }

            $normalized[] = ['score_a'=>$scoreA, 'score_b'=>$scoreB, 'completed'=>$completed];
        }

        if ($data['status'] === 'completed') {
            abort_if($waitingForNextSet, 422, 'Set yang sedang berjalan harus ditandai selesai terlebih dahulu.');
            abort_unless($matchDecided, 422, "Salah satu tim harus memenangkan {$requiredWins} set sebelum pertandingan diselesaikan.");
        }

        $data['best_of_sets'] = $bestOf;
        $data['set_scores'] = $normalized;
        $data['score_a'] = $winsA;
        $data['score_b'] = $winsB;
        $data['winner_id'] = $data['status'] === 'completed'
            ? ($winsA > $winsB ? $match->participant_a_id : $match->participant_b_id)
            : null;

        return $data;
    }
}
