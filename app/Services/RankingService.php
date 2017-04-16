<?php

namespace App\Services;

use App\Exceptions\RankingDateNotFoundException;
use Cache;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

class RankingService
{
    /** @var ConnectionInterface */
    protected $connection;

    public function __construct()
    {
        $this->connection = \DB::connection('rankingpro');
    }

    /**
     * Gets the highscore data for a given date range (cached).
     *
     * @param mixed $from
     * @param mixed $to
     * @return Collection
     */
    public function getHighscoresDataCompare($from, $to): Collection
    {
        $cacheKey = sprintf('rnk_main_%s_%s', $from, $to);

        return Cache::rememberForever($cacheKey, function() use ($to, $from) {
            $fromExists = $this->dateExists($from, 'level');
            if (!$fromExists) {
                throw new RankingDateNotFoundException($from, 'from');
            }
            $toExists = $this->dateExists($to, 'level');
            if (!$toExists) {
                throw new RankingDateNotFoundException($to, 'to');
            }

            $this->initializeTempTables(['level', 'persoenlicher_ruhm', 'pvp'], [$from, $to]);

            $data = $this->connection
                ->table(static::generateTmpTableName('level', $to).' AS lv2')
                ->select([
                    'lv2.Name AS name',
                    'lv2.Gear AS gear',
                    'lv2.Level AS to_level',
                    'lv1.Level AS from_level',
                    'lv1.EP AS from_ep',
                    'lv2.EP AS to_ep',
                    'lv2.Brigade AS brigade',
                    'lv2.Nation AS nation',
                    'fa1.Ruhm AS from_fame',
                    'fa2.Ruhm AS to_fame',
                    'pv1.Siege AS from_wins',
                    'pv1.Tode AS from_deaths',
                    'pv1.Punkte AS from_pvppoints',
                    'pv2.Siege AS to_wins',
                    'pv2.Tode AS to_deaths',
                    'pv2.Punkte AS to_pvppoints',
                ])
                ->leftJoin(static::generateTmpTableName('level', $from).' AS lv1', function(JoinClause $join) {
                    $join
                        ->on('lv2.Name', '=', 'lv1.Name')
                        ->on('lv2.Level', '>=', 'lv1.Level')
                        ->on('lv2.Gear', '=', 'lv1.Gear');
                })
                ->leftJoin(static::generateTmpTableName('persoenlicher_ruhm', $to).' AS fa2', function(JoinClause $join) {
                    $join
                        ->on('lv2.Name', '=', 'fa2.Name')
                        ->on('lv2.Level', '=', 'fa2.Level')
                        ->on('lv2.Gear', '=', 'fa2.Gear')
                        ->on(function(JoinClause $where) {
                            $where
                                ->on('lv2.Brigade', '=', 'fa2.Brigade')
                                ->orOn(function(JoinClause $nullWhere) {
                                    $nullWhere
                                        ->whereNull('lv2.Brigade')
                                        ->whereNull('fa2.Brigade');
                                });
                        })
                        ->on('lv2.Nation', '=', 'fa2.Nation');
                })
                ->leftJoin(static::generateTmpTableName('persoenlicher_ruhm', $from).' AS fa1', function(JoinClause $join) {
                    $join
                        ->on('lv2.Name', '=', 'fa1.Name')
                        ->on('lv2.Level', '>=', 'fa1.Level')
                        ->on('lv2.Gear', '=', 'fa1.Gear')
                        ->on('fa2.Ruhm', '>=', 'fa1.Ruhm');
                })
                ->leftJoin(static::generateTmpTableName('pvp', $to).' AS pv2', function(JoinClause $join) {
                    $join
                        ->on('lv2.Name', '=', 'pv2.Name')
                        ->on('lv2.Level', '=', 'pv2.Level')
                        ->on('lv2.Gear', '=', 'pv2.Gear')
                        ->on(function(JoinClause $where) {
                            $where
                                ->on('lv2.Brigade', '=', 'pv2.Brigade')
                                ->orOn(function(JoinClause $nullWhere) {
                                    $nullWhere
                                        ->whereNull('lv2.Brigade')
                                        ->whereNull('pv2.Brigade');
                                });
                        })
                        ->on('lv2.Nation', '=', 'pv2.Nation');
                })
                ->leftJoin(static::generateTmpTableName('pvp', $from).' AS pv1', function(JoinClause $join) {
                    $join
                        ->on('lv2.Name', '=', 'pv1.Name')
                        ->on('lv2.Level', '>=', 'pv1.Level')
                        ->on('lv2.Gear', '=', 'pv1.Gear')
                        ->on('pv2.Siege', '>=', 'pv1.Siege')
                        ->on('pv2.Tode', '>=', 'pv1.Tode');
                })
                ->orderByDesc('lv2.EP')
                ->orderByDesc('fa2.Ruhm')
                ->orderByDesc('pv2.Punkte')
                ->orderByDesc('pv2.Siege')
                ->orderBy('pv1.Tode', 'asc')
                ->orderBy('lv2.Name')
                ->get();

            if ($data->isEmpty()) {
                throw new \DomainException('Highscore data empty for '.$from.' to '.$to);
            }

            return $data;
        });
    }

    /**
     * Gets the highscore data for a given date (cached).
     *
     * @param mixed $date
     * 
     * @return Collection
     */
    public function getHighscoresData($date): Collection
    {
        $cacheKey = sprintf('rnk_main_%s', $date);

        return Cache::rememberForever($cacheKey, function() use ($date) {
            $toExists = $this->dateExists($date, 'level');
            if (!$toExists) {
                throw new RankingDateNotFoundException($date, 'date');
            }

            $this->initializeTempTables(['level', 'persoenlicher_ruhm', 'pvp'], [$date]);

            $data = $this->connection
                ->table(static::generateTmpTableName('level', $date).' AS lv')
                ->select([
                    'lv.Name AS name',
                    'lv.Gear AS gear',
                    'lv.Level AS data_level',
                    'lv.EP AS data_ep',
                    'lv.Brigade AS brigade',
                    'lv.Nation AS nation',
                    'fa.Ruhm AS data_fame',
                    'pv.Siege AS data_wins',
                    'pv.Tode AS data_deaths',
                    'pv.Punkte AS data_pvppoints',
                ])
                ->leftJoin(static::generateTmpTableName('persoenlicher_ruhm', $date).' AS fa', function(JoinClause $join) {
                    $join
                        ->on('lv.Name', '=', 'fa.Name')
                        ->on('lv.Level', '=', 'fa.Level')
                        ->on('lv.Gear', '=', 'fa.Gear')
                        ->on(function(JoinClause $where) {
                            $where
                                ->on('lv.Brigade', '=', 'fa.Brigade')
                                ->orOn(function(JoinClause $nullWhere) {
                                    $nullWhere
                                        ->whereNull('lv.Brigade')
                                        ->whereNull('fa.Brigade');
                                });
                        })
                        ->on('lv.Nation', '=', 'fa.Nation');
                })
                ->leftJoin(static::generateTmpTableName('pvp', $date).' AS pv', function(JoinClause $join) {
                    $join
                        ->on('lv.Name', '=', 'pv.Name')
                        ->on('lv.Level', '=', 'pv.Level')
                        ->on('lv.Gear', '=', 'pv.Gear')
                        ->on(function(JoinClause $where) {
                            $where
                                ->on('lv.Brigade', '=', 'pv.Brigade')
                                ->orOn(function(JoinClause $nullWhere) {
                                    $nullWhere
                                        ->whereNull('lv.Brigade')
                                        ->whereNull('pv.Brigade');
                                });
                        })
                        ->on('lv.Nation', '=', 'pv.Nation');
                })
                ->orderByDesc('lv.EP')
                ->orderByDesc('fa.Ruhm')
                ->orderByDesc('pv.Punkte')
                ->orderByDesc('pv.Siege')
                ->orderBy('pv.Tode', 'asc')
                ->orderBy('lv.Name')
                ->get();

            if ($data->isEmpty()) {
                throw new \DomainException('Highscore data empty for '.$date);
            }

            return $data;
        });
    }

    /**
     * Checks for the existance of data for a certain date and category.
     *
     * @param mixed $date
     * @param string $table
     * @return bool
     */
    public function dateExists($date, string $table = 'level'): bool
    {
        return (bool) $this->connection->selectOne('select exists(select 1 from `'.$table.'` where Datum="'.$date.'") as `exists`')->exists;
    }

    /**
     * Initializes the temp tables containing only data for given dates.
     *
     * @param array|string[] $tables
     * @param array $dates
     */
    protected function initializeTempTables(array $tables, array $dates)
    {
        $queries = [];
        foreach ($tables as $table) {
            foreach ($dates as $date) {
                $queries[] = 'CREATE TEMPORARY TABLE IF NOT EXISTS `'.static::generateTmpTableName($table, $date).'` AS (SELECT * FROM `'.$table.'` WHERE Datum = "'.$date.'");';
            }
        }

        $this->connection->unprepared($this->connection->raw(implode(PHP_EOL, $queries)));
    }

    /**
     * Helper for the naming convention of temp tables.
     *
     * @param string $table
     * @param mixed $date
     * @return string
     */
    public static function generateTmpTableName(string $table, $date): string
    {
        return sprintf('tmp_%s_%s', $date, $table);
    }
}
