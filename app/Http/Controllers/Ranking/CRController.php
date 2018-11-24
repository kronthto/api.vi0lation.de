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
        $timestamps = \Cache::remember('cr_playerfame_timestamps', 6, function () : Collection {
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
        $response->setMaxAge(300);

        return $response;
    }

    public function topKillsBetween(Request $request)
    {
        $data = $this->service->getTopKillsBetween(Carbon::parse($request->get('from')), Carbon::parse($request->get('to')));

        $stats = [
            'byNation' => [
                'BCU' => $data->where('nation', 'BCU')->sum('diff'),
                'ANI' => $data->where('nation', 'ANI')->sum('diff'),
            ],
            'byGear' => [
                'I' => $data->where('gear', 'I')->sum('diff'),
                'M' => $data->where('gear', 'M')->sum('diff'),
                'B' => $data->where('gear', 'B')->sum('diff'),
                'A' => $data->where('gear', 'A')->sum('diff'),
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
}
