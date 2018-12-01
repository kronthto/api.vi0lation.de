<?php

namespace App\Http\Controllers\Ranking;

use App\Http\Controllers\Controller;
use App\Services\ChromeRivalsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class CRController extends Controller
{
    /** @var ChromeRivalsService */
    protected $service;

    public function __construct(ChromeRivalsService $crService)
    {
        $this->service = $crService;
    }

    public function rankingDates()
    {
        $timestamps = \Cache::remember('cr_playerfame_timestamps', 4, function () : Collection {
            $tbl = \DB::connection('chromerivals')->table('cr_ranking_crawl');
            return $tbl->select(['timestamp'])->groupBy('timestamp')->pluck('timestamp')->map(function ($ts): \DateTime {
                return Carbon::parse($ts)->second(0);
            })->unique(function (\DateTime $dt): int {
                return $dt->getTimestamp();
            })->sort()->reverse()->values();
        });

        $response = response([
            'playerfame' => $timestamps->map(function (\DateTime $dt): string {
                return $dt->format('c');
            }),
        ]);

        $response->setPublic();
        $response->setMaxAge(150);

        return $response;
    }

    public function topKillsBetween(Request $request)
    {
        $data = $this->service->getTopKillsBetween(Carbon::parse($request->get('from')), Carbon::parse($request->get('to')));

        $ig = $data->where('gear', 'I');
        $mg = $data->where('gear', 'M');
        $bg = $data->where('gear', 'B');
        $ag = $data->where('gear', 'A');

        $stats = [
            'byNation' => [
                'BCU' => $data->where('nation', 'BCU')->sum('diff'),
                'ANI' => $data->where('nation', 'ANI')->sum('diff'),
            ],
            'byGear' => [
                'I' => $ig->sum('diff'),
                'M' => $mg->sum('diff'),
                'B' => $bg->sum('diff'),
                'A' => $ag->sum('diff'),
            ],
            'counts' => [
                'BCU' => [
                    'I' => $ig->where('nation', 'BCU')->count(),
                    'M' => $mg->where('nation', 'BCU')->count(),
                    'B' => $bg->where('nation', 'BCU')->count(),
                    'A' => $ag->where('nation', 'BCU')->count(),
                ],
                'ANI' => [
                    'I' => $ig->where('nation', 'ANI')->count(),
                    'M' => $mg->where('nation', 'ANI')->count(),
                    'B' => $bg->where('nation', 'ANI')->count(),
                    'A' => $ag->where('nation', 'ANI')->count(),
                ],
            ],
        ];

        $response = response(compact('stats', 'data'));
        $response->setPublic();
        $response->setMaxAge(86400);

        return $response;
    }

    public function playerFame(Request $request)
    {
        $name = (string) $request->get('name');
        if (!$name) {
            return response('name is required', Response::HTTP_BAD_REQUEST);
        }

        $data = $this->service->getPlayerFameHistory($name);

        if ($data->isEmpty()) {
            return response('no data found', Response::HTTP_NOT_FOUND);
        }

        $dataFormatted = $data->map(function ($fameRow): array {
            return [
                'fame' => (int) $fameRow->fame,
                'timestamp' => (new Carbon($fameRow->timestamp))->format('c'),
            ];
        });

        $sample = $data->last();

        $response = response(['name' => $sample->name, 'data' => $dataFormatted]);
        $response->setPublic();
        $response->setMaxAge(600);

        return $response;
    }

    public function onlinePlayers()
    {
        $data = $this->service->getOnlinePlayersHistory();

        $response = response(array_values($data));
        $response->setPublic();
        $response->setMaxAge(120);

        return $response;
    }

    public function aggregatedFameHistory(Request $request)
    {
        $data = $this->service->getAggrFameHistoryCached($request->get('days', 10), $request->get('groupMinutes', 60));

        $stats = [
            'byNation' => array_map(function ($rows) {
                $ret = [
                    'BCU' => 0,
                    'ANI' => 0,
                ];

                foreach ($rows as $row) {
                    $ret[$row->nation] += $row->diff;
                }

                return $ret;
            }, $data),
            'byGear' => array_map(function ($rows) {
                $ret = [
                    'I' => 0,
                    'M' => 0,
                    'B' => 0,
                    'A' => 0,
                ];

                foreach ($rows as $row) {
                    $ret[$row->gear] += $row->diff;
                }

                return $ret;
            }, $data),
        ];

        $response = response($stats);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
