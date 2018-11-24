<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

class ChromeRivalsService
{
    /** @var ConnectionInterface */
    protected $connection;

    public function __construct()
    {
        $this->connection = \DB::connection('chromerivals');
    }

    /**
     * Gets the highscore/fame history data for a given player name.
     *
     * @param string $name
     *
     * @return Collection
     */
    public function getPlayerFameHistory(string $name): Collection
    {
        $table = $this->connection->table('cr_ranking_crawl');

        return $table
            ->select(['fame', 'timestamp', 'name'])
            ->where('name', '=', $name)
            ->orderBy('timestamp', 'asc')
            ->get();
    }

    public function getTopKillsBetween(Carbon $from, Carbon $to)
    {
        // todo: cache

        $fromStart = $from->second(0);
        $fromEnd = $from->copy()->addMinute();
        $toStart = $to->second(0);
        $toEnd = $to->copy()->addMinute();

        $tmpFrom = sprintf('playerfame_%d', $fromStart->getTimestamp());
        $tmpTo = sprintf('playerfame_%d', $toStart->getTimestamp());

        $this->connection->unprepared('CREATE TEMPORARY TABLE IF NOT EXISTS `'.$tmpFrom.'` AS (SELECT name,fame,extra FROM `cr_ranking_crawl` WHERE `timestamp` >= "'.$fromStart->toDateTimeString().'" AND `timestamp` < "'.$fromEnd->toDateTimeString().'")');
        $this->connection->unprepared('CREATE TEMPORARY TABLE IF NOT EXISTS `'.$tmpTo.'` AS (SELECT name,fame,extra FROM `cr_ranking_crawl` WHERE `timestamp` >= "'.$toStart->toDateTimeString().'" AND `timestamp` < "'.$toEnd->toDateTimeString().'")');

        $res = $this->connection->table($tmpTo)
            ->select(["$tmpTo.name", "$tmpTo.extra"])
            ->selectRaw("$tmpTo.fame - $tmpFrom.fame as diff")
            ->join($tmpFrom, "$tmpTo.name", '=', "$tmpFrom.name")
            ->having('diff', '>', 0)
            ->orderByDesc('diff')
        ->get()
            ->map(function ($row): array {
                $extra = json_decode($row->extra);
                return [
                    'name' => $row->name,
                    'diff' => $row->diff,
                    'nation' => $this->determineNation($extra),
                    'gear' => $this->determineGear($extra),
                    'brigade' => $extra->brigade ?? null,
                ];
            });

        dd($res);
    }

    protected function determineNation($data): ?string
    {
        if (isset($data->nation)) {
            switch ($data->nation) {
                case 2:
                    return 'BCU';
                case 4:
                    return 'ANI';
            }
        }
        if (isset($data->Nation)) {
            switch ($data->Nation) {
                case 'Bygeniou':
                    return 'BCU';
                case 'Arlington':
                    return 'ANI';
            }
        }
        return null;
    }

    protected function determineGear($data): ?string
    {
        if (isset($data->gear)) {
            switch ($data->gear) {
                case 1:
                    return 'B';
                case 256:
                    return 'A';
                case 4096:
                    return 'I';
                case 16:
                    return 'M';
            }
        }
        if (isset($data->Gear)) {
            return $data->Gear[0];
        }
        return null;
    }

    /**
     * Gets the online player history.
     *
     * @return array
     */
    public function getOnlinePlayersHistory(): array
    {
        return \Cache::remember('cr_OnlinePlayersHistory', 5, function (): array {
            $table = $this->connection->table('cr_online_crawl');

            $data = $table
                ->select()
                ->orderBy('timestamp', 'asc')
                ->get();

            $clustered = [];

            foreach ($data as $datum) {
                $ts = $this->closestMinute(new Carbon($datum->timestamp));
                $clustered[$ts->getTimestamp()][] = $datum;
            }

            return array_filter(array_map(function ($cluster, $ts): ?array {
                if (\count($cluster) !== 2) {
                    return null;
                }

                foreach ($cluster as $item) {
                    switch ($item->nation) {
                        case 'BCU':
                            $bcu = $item;
                            break;
                        case 'ANI':
                            $ani = $item;
                            break;
                    }
                }

                if (!isset($bcu, $ani)) {
                    return null;
                }

                return [
                    'timestamp' => Carbon::createFromTimestamp($ts)->format('c'),
                    'bcu' => $bcu->online,
                    'ani' => $ani->online,
                    'total' => $ani->online + $bcu->online,
                ];
            }, $clustered, array_keys($clustered)));
        });
    }

    protected function closestMinute(Carbon $dt): Carbon
    {
        $seconds = $dt->second;
        $dt = $dt->copy()->second(0);

        if ($seconds < 30) {
            return $dt;
        }

        return $dt->addMinute();
    }
}
