<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

class ChromeRivalsService
{
    /** @var ConnectionInterface */
    protected $connection;

    protected $timeframeCache = [];

    public function __construct()
    {
        $this->connection = \DB::connection('chromerivals');
    }

    /**
     * Gets the highscore/fame history data for a given player name.
     *
     * @param string $name
     * @param Carbon|null $from
     * @param Carbon|null $to
     *
     * @return Collection
     */
    public function getPlayerFameHistory(string $name, Carbon $from = null, Carbon $to = null): Collection
    {
        $uniqToLatestNameQuery = $this->connection->table('cr_players')->select(['uniq'])->where('name', '=', $name)->orderBy('timestamp_last', 'desc')->limit(1)->get();

        if ($uniqToLatestNameQuery->isEmpty()) {
            return new Collection();
        }

        $playerIdQuery = $this->connection->table('cr_player_ids')->select(['id', 'data'])->where('uniq', '=', $uniqToLatestNameQuery->first()->uniq)->get();

        if ($playerIdQuery->isEmpty()) {
            throw new \LogicException('No player_id found to known name/uniq');
        }

        $table = $this->connection->table('cr_player_ranking_history');

        $q = $table
            ->select(['fame', 'timestamp'])
            ->where('player_id', $playerIdQuery->first()->id)
            ->orderBy('timestamp', 'asc');

        if ($from) {
            $q->where('timestamp', '>=', $from->startOfMinute());
        }
        if ($to) {
            $q->where('timestamp', '<=', $to->endOfMinute());
        }

        $res = $q->get();

        // Hack in the name into the last element used as sample as we no longer fetch it to every row
        $res->last()->name = json_decode($playerIdQuery->first()->data)->name;

        return $res;
    }

    public function getTopKillsBetween(Carbon $from, Carbon $to, &$fromToUsed = null): Collection
    {
        $fromStart = $from->second(0);
        $toStart = $to->second(0);

        if (!$toStart->greaterThan($fromStart)) {
            abort(400, 'to needs to be greater than from');
        }

        $fromToUsed = sprintf('%d_%d', $fromStart->getTimestamp(), $toStart->getTimestamp());

        return \Cache::remember(
            'crtopkills_' . $fromToUsed,
            1000,
            function () use ($from, $to, $fromStart, $toStart): Collection {
                $fromEnd = $from->copy()->addMinute();
                $toEnd = $to->copy()->addMinute();

                $tmpFrom = sprintf('playerfame_%d', $fromStart->getTimestamp());
                $tmpTo = sprintf('playerfame_%d', $toStart->getTimestamp());
                $this->connection->unprepared('CREATE TEMPORARY TABLE IF NOT EXISTS `' . $tmpFrom . '` (INDEX (`player_id`)) AS (SELECT player_id,fame FROM `cr_player_ranking_history` WHERE `timestamp` >= "' . $fromStart->toDateTimeString() . '" AND `timestamp` < "' . $fromEnd->toDateTimeString() . '")');
                if (!$this->connection->table($tmpFrom)->count()) {
                    abort(404, 'No data for from date');
                }

                $this->connection->unprepared('CREATE TEMPORARY TABLE IF NOT EXISTS `' . $tmpTo . '` (INDEX (`player_id`)) AS (SELECT player_id,fame FROM `cr_player_ranking_history` WHERE `timestamp` >= "' . $toStart->toDateTimeString() . '" AND `timestamp` < "' . $toEnd->toDateTimeString() . '" and fame > 0)');
                if (!$this->connection->table($tmpTo)->count()) {
                    abort(404, 'No data for to date');
                }

                $res = $this->connection->table($tmpTo)
                    ->select(['cr_player_ids.data as extra'])
                    ->selectRaw("(CAST($tmpTo.fame AS SIGNED) - CAST($tmpFrom.fame AS SIGNED)) as diff")
                    ->join($tmpFrom, function (JoinClause $joinClause) use ($tmpFrom, $tmpTo) {
                        $joinClause
                            ->where(function (Builder $q) use ($tmpFrom, $tmpTo) {
                                $q
                                    ->whereColumn("$tmpTo.player_id", '=', "$tmpFrom.player_id");
                            });
                    })
                    ->join('cr_player_ids', "$tmpTo.player_id", '=', 'cr_player_ids.id')
                    ->having('diff', '!=', 0)
                    ->orderByDesc('diff')
                    ->get()
                    ->map(function ($row): array {
                        $extra = json_decode($row->extra);

                        return [
                            'name' => $extra->name,
                            'diff' => $row->diff,
                            'nation' => $this->determineNation($extra),
                            'gear' => $this->determineGear($extra),
                            'brigade' => $extra->brigade ?? null,
                        ];
                    });

                return $res;
            }
        );
    }

    public function getTopKillsBetweenBrigade(Carbon $from, Carbon $to, &$fromToUsed = null): Collection
    {
        $fromStart = $from->second(0);
        $toStart = $to->second(0);

        if (!$toStart->greaterThan($fromStart)) {
            abort(400, 'to needs to be greater than from');
        }

        $fromToUsed = sprintf('%d_%d', $fromStart->getTimestamp(), $toStart->getTimestamp());

        return \Cache::remember(
            'crtopkillsbrig_' . $fromToUsed,
            1000,
            function () use ($from, $to, $fromStart, $toStart): Collection {
                $fromEnd = $from->copy()->addMinute();
                $toEnd = $to->copy()->addMinute();

                $tmpFrom = sprintf('brigfame_%d', $fromStart->getTimestamp());
                $tmpTo = sprintf('brigfame_%d', $toStart->getTimestamp());

                $this->connection->unprepared('CREATE TEMPORARY TABLE IF NOT EXISTS `' . $tmpFrom . '` (INDEX (`name`)) AS (SELECT name,fame FROM `cr_brigranking_crawl` WHERE `timestamp` >= "' . $fromStart->toDateTimeString() . '" AND `timestamp` < "' . $fromEnd->toDateTimeString() . '")');
                if (!$this->connection->table($tmpFrom)->count()) {
                    abort(404, 'No data for from date');
                }

                $this->connection->unprepared('CREATE TEMPORARY TABLE IF NOT EXISTS `' . $tmpTo . '` (INDEX (`name`)) AS (SELECT name,fame,extra FROM `cr_brigranking_crawl` WHERE `timestamp` >= "' . $toStart->toDateTimeString() . '" AND `timestamp` < "' . $toEnd->toDateTimeString() . '" and fame > 0)');
                if (!$this->connection->table($tmpTo)->count()) {
                    abort(404, 'No data for to date');
                }

                $res = $this->connection->table($tmpTo)
                    ->select(["$tmpTo.name", "$tmpTo.extra"])
                    ->selectRaw("(CAST($tmpTo.fame AS SIGNED) - CAST($tmpFrom.fame AS SIGNED)) as diff")
                    ->join($tmpFrom, "$tmpTo.name", '=', "$tmpFrom.name")
                    ->having('diff', '!=', 0)
                    ->orderByDesc('diff')
                    ->get()
                    ->map(function ($row): array {
                        $extra = json_decode($row->extra);

                        return [
                            'name' => $row->name,
                            'diff' => $row->diff,
                            'nation' => $this->determineNation($extra),
                            'leader' => $extra->leader,
                            'outpost' => $extra->outpost,
                        ];
                    });

                return $res;
            }
        );
    }

    public static function determineNation($data): ?string
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

    public static function determineGear($data): ?string
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

    public function getAggrFameHistoryCached(int $backDays, int $aggregateMins): array
    {
        $cacheKey = sprintf('craggrfamehist_%d_%d', $aggregateMins, $backDays);

        return \Cache::remember($cacheKey, 20, function () use ($backDays, $aggregateMins): array {
            return $this->getAggrFameHistory($backDays, $aggregateMins);
        });
    }

    public function getAggrFameHistory(int $backDays, int $aggregateMins): array
    {
        if (($aggregateMins < 60 || $aggregateMins % 60 !== 0) && $aggregateMins !== 30) {
            abort(400, 'Can only aggregate by full hours');
        }

        $table = $this->connection->table('cr_playerfame_diffs');

        $since = Carbon::now()->subDays($backDays)->startOfDay();

        $res = [];

        $table->select(['from', 'to', 'diff', 'nation', 'gear', 'name'])
            ->where('from', '>=', $since->toDateTimeString())
            ->orderBy('from')
            ->each(function ($row) use (&$res, $aggregateMins) {
                $timeframe = $this->determineTimeframeCached($row, $aggregateMins);
                if ($timeframe === null) {
                    return;
                }
                $res[$timeframe->getTimestamp()][] = $row;
            });

        return $res;
    }

    protected function determineTimeframeCached($row, int $aggrMinutes): ?Carbon
    {
        $cacheKey = sprintf('%s_%s', $row->from, $row->to);

        if (!array_key_exists($cacheKey, $this->timeframeCache)) {
            $this->timeframeCache[$cacheKey] = $this->determineTimeframe($row, $aggrMinutes);
        }

        return $this->timeframeCache[$cacheKey];
    }

    protected function determineTimeframe($row, int $aggrMinutes): ?Carbon
    {
        $startDate = new Carbon($row->from);
        $endDate = new Carbon($row->to);

        $length = $startDate->diffInMinutes($endDate);
        if ($length > $aggrMinutes * 1.45) {
            return null;
        }

        $samplingRef = $startDate->copy()->subDay()->startOfDay()->getTimestamp();

        $diffToSamplingRefSecs = $startDate->getTimestamp() - $samplingRef;
        $diffToSamplingRefMins = $diffToSamplingRefSecs / 60;
        $wholeIntervals = (int) (($diffToSamplingRefMins + 0.15 * $length) / $aggrMinutes);

        $firstIntervalStartToCheck = Carbon::createFromTimestamp($samplingRef + 60 * $aggrMinutes * $wholeIntervals);
        $endOfInterval = $firstIntervalStartToCheck->copy()->addMinutes($aggrMinutes);
        $endOfNextInterval = $endOfInterval->copy()->addMinutes($aggrMinutes);

        //  echo 'Interval points: ',$firstIntervalStartToCheck->toDateTimeString(),' ',$endOfInterval->toDateTimeString(),' ',$endOfNextInterval->toDateTimeString(),PHP_EOL;

        \assert(
            $endDate->lessThan($endOfNextInterval),
            'Two intervals should cover a longer timeframe than the max allowed of 1.45x one timeframe'
        );

        if ($endDate->lessThanOrEqualTo($endOfInterval)) {
            return $firstIntervalStartToCheck;
        }

        $timeInFirstInterval = $endOfInterval->diffInMinutes($startDate);
        $timeInNextInterval = $endOfInterval->diffInMinutes($endDate);

        // echo $timeInFirstInterval,' ', $timeInNextInterval,' ',abs($length - ($timeInFirstInterval + $timeInNextInterval));

        \assert(
            abs($length - ($timeInFirstInterval + $timeInNextInterval)) <= 2,
            'the parts in the two intervals the row overlaps should add up to its total length'
        );

        if ($timeInFirstInterval > $timeInNextInterval) {
            return $firstIntervalStartToCheck;
        } else {
            return $endOfInterval;
        }
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

    public function fetchBriglogo(string $brigName): ?string
    {
        $hit = $this->connection
            ->table('cr_brig_emblems')
            ->select()
            ->where('name', '=', $brigName)
            ->first();

        if (!$hit) {
            return null;
        }

        return $hit->data;
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
