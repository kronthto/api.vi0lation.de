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
