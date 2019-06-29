<?php

namespace App\Console\Commands;

use App\Services\ChromeRivalsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

// Out of order
class AggregateCRFameStats extends Command
{
    protected $signature = 'cr:aggregatefame {day}';

    public function handle()
    {
        $day = (new Carbon($this->argument('day')))->startOfDay();
        $end = $day->copy()->endOfDay();
        $prev = $day->copy()->subDay()->hour(23);
        $next = $day->copy()->addDay()->hour(1);


        \DB::connection('chromerivals')->table('cr_ranking_crawl')
            ->select(['name'])
            ->where('timestamp', '>=', $day->toDateTimeString())
            ->where('timestamp', '<=', $end->toDateTimeString())
            ->groupBy(['name'])
            ->orderBy('name')
            ->each(function ($nameRow) use ($prev, $next, $day) {
                $name = $nameRow->name;
                $this->line('Start player ' . $name);

                $rows = \DB::connection('chromerivals')->table('cr_ranking_crawl')
                    ->select()
                    ->where('name', '=', $name)
                    ->where('timestamp', '>', $prev->toDateTimeString())
                    ->where('timestamp', '<', $next->toDateTimeString())
                    ->orderBy('timestamp', 'asc')
                    ->get();

                if ($rows->last()->fame === $rows->first()->fame) {
                    return;
                }

                $prevRow = null;

                \DB::connection('chromerivals')->beginTransaction();

                foreach ($rows as $row) {
                    if ($prevRow === null) {
                        $prevRow = $row;
                        continue;
                    }

                    if ($row->fame > $prevRow->fame && $prevRow->fame) {
                        if ((new Carbon($row->timestamp))->isSameDay($day) || (new Carbon($prevRow->timestamp))->isSameDay($day)) {
                            $rowExtra = json_decode($row->extra);
                            $rowNation = ChromeRivalsService::determineNation($rowExtra);
                            $rowGear = ChromeRivalsService::determineGear($rowExtra);
                            if ($rowGear && $rowNation) {
                                $prevrowExtra = json_decode($prevRow->extra);
                                $prevrowNation = ChromeRivalsService::determineNation($prevrowExtra);
                                $prevrowGear = ChromeRivalsService::determineGear($prevrowExtra);
                                if ($rowGear === $prevrowGear && $rowNation === $prevrowNation) {
                                    try {
                                        \DB::connection('chromerivals')->table('cr_playerfame_diffs')
                                            ->insert([
                                                'from' => $prevRow->timestamp,
                                                'to' => $row->timestamp,
                                                'diff' => $row->fame - $prevRow->fame,
                                                'name' => $name,
                                                'nation' => $rowNation,
                                                'gear' => $rowGear,
                                            ]);
                                    } catch (QueryException $e) {
                                        if ($e->getCode() != 23000) {
                                            throw $e;
                                        }
                                    }

                                }
                            }
                        }
                    }

                    $prevRow = $row;
                }

                \DB::connection('chromerivals')->commit();
            });
    }
}
