<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

// Run this hourly after ranking insert .. like :05?

class XFeedCheck extends Command
{
    protected $signature = 'cr:xfeed';

    public function handle()
    {
        // Needs improvement when people start at :50 until :10

        $db = \DB::connection('chromerivals');

        $until = Carbon::now()->subHour();

        $rows = collect($db->select('SELECT SUM(diff) AS kills,player_id FROM cr_playerfame_diffs WHERE DATE(`to`) = \'' . $until->toDateString() . '\' AND HOUR(`to`)=' . $until->hour . ' GROUP BY player_id ORDER BY kills desc'));

        if ($rows->isEmpty() || $rows->first()->kills < 30) {
            return 0;
        }

        $numPlayers = $rows->count();
        $totalKills = $rows->sum('kills');

        $percentile = 0.4 * $totalKills; // todo: in abh. wieviele online sind statt immer 40%? Weniger wenn 1k Leute..
        $ave = $totalKills / $numPlayers;
        $aveCap = $ave * 4.5;

        $plMap = [];
        $db->table('cr_player_ids')->select('id', 'data')
            ->whereIn('id', $rows->pluck('player_id'))->get()->each(function ($row) use (&$plMap) {
                $data = json_decode($row->data);
                $plMap[$row->id] = [
                    'name' => sprintf('%s (plid %d created %s)', $data->name, $row->id, $data->startTime),
                    'totalKills' => $data->fame
                ];
            });


        $rows->each(function ($row) use ($percentile, $aveCap, $numPlayers, &$plMap) {
            $rowKills = $row->kills;

            if ($rowKills < 29) {
                return;
            }

            $id = sprintf('%s: %d kills last hour', $plMap[$row->player_id]['name'], $rowKills);
            $previousPlayerKills = $plMap[$row->player_id]['totalKills'] - $rowKills;
            if ($previousPlayerKills < 0) {
                throw new \LogicException('Player had previously negative kills: ' . $id);
            }
            $killsPlausibleForPlayer = $previousPlayerKills > 70 * $rowKills;

            if ($rowKills > 180) {
                $this->line($id . ' - over 180');
            }
            if ($rowKills > $percentile) {
                $this->line($id . ' - almost all total kills alone');
            }
            if ($rowKills > $aveCap && !$killsPlausibleForPlayer) {
                $this->line($id . ' - way above average');
            }
            if ($numPlayers < 10 && $rowKills > 65) {
                $this->line($id . ' - a lot of kills for few ppl online');
            }
            if ($previousPlayerKills < 10 * $rowKills) {
                $this->line($id . ' - not in proportion with previous ' . $previousPlayerKills . ' total kills');
            }
        });

        return 0;
    }
}
