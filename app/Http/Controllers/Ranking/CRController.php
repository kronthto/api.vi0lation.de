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
        $timestamps = \Cache::remember('cr_playerfame_timestamps', 2, function () : Collection {
            $tbl = \DB::connection('chromerivals')->table('cr_crawl_dates');
            return $tbl->select(['timestamp'])->distinct()->pluck('timestamp')->map(function ($ts): \DateTime {
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
        $response->setMaxAge(90);

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

    public function brigKillsBetween(Request $request)
    {
        $data = $this->service->getTopKillsBetweenBrigade(Carbon::parse($request->get('from')), Carbon::parse($request->get('to')));

        $stats = [
            'byNation' => [
                'BCU' => $data->where('nation', 'BCU')->sum('diff'),
                'ANI' => $data->where('nation', 'ANI')->sum('diff'),
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

        $from = $request->has('from') ? Carbon::parse($request->get('from')) : null;
        $to = $request->has('to') ? Carbon::parse($request->get('to')) : null;

        $data = $this->service->getPlayerFameHistory($name, $from, $to);

        if ($data->isEmpty()) {
            return response()->json('no data found', Response::HTTP_NOT_FOUND);
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
        $response->setMaxAge($to ? 86400 : 600);

        return $response;
    }

    public function brigadeFame(Request $request)
    {
        $name = (string) $request->get('name');
        if (!$name) {
            return response('name is required', Response::HTTP_BAD_REQUEST);
        }

        $from = $request->has('from') ? Carbon::parse($request->get('from')) : null;
        $to = $request->has('to') ? Carbon::parse($request->get('to')) : null;

        $data = $this->service->getBrigadeFameHistory($name, $from, $to);

        if ($data->isEmpty()) {
            return response()->json('no data found', Response::HTTP_NOT_FOUND);
        }

        $dataFormatted = $data->map(function ($fameRow): array {
            return [
                'fame' => (int) $fameRow->fame,
                'mfame' => (int) $fameRow->mfame,
                'timestamp' => (new Carbon($fameRow->timestamp))->format('c'),
            ];
        });

        $sample = $data->last();

        $response = response(['name' => $sample->name, 'data' => $dataFormatted]);
        $response->setPublic();
        $response->setMaxAge($to ? 86400 : 600);

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

                $uniqueActivePlayers = collect($rows)->where('diff', '>', 0)->unique('name'); // suboptimal, we now also have player_id, but not for all rows
                $ret['BCU_Players'] = $uniqueActivePlayers->where('nation', 'BCU')->count();
                $ret['ANI_Players'] = $uniqueActivePlayers->where('nation', 'ANI')->count();

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

    protected function brigLogoResp(string $binaryContent): Response
    {
        return response($binaryContent, 200, [
            'Content-Type' => 'image/png',
        ])
            ->setMaxAge(50000)
            ->setPublic();
    }

    public function brigLogo(Request $request)
    {
        $brigName = $request->get('name');
        if (!$brigName) {
            abort(400, 'Brig name required');
        }

        $binaryContent = \Cache::remember(sprintf('CR_Briglogo_%s', sha1($brigName)), 180, function() use ($brigName) {
            $data = $this->service->fetchBriglogo($brigName);
            if (!$data) {
                abort(404, 'Brigade not found or no logo');
            }

            $im = new \Imagick();
            $im->readImageBlob($data);
            $im->setImageFormat('png');

            $binaryContent = $im->getImageBlob();
            if (!$binaryContent) {
                throw new \RuntimeException('Empty PNG image blob returned for '.$brigName);
            }
            return $binaryContent;
        });

        return $this->brigLogoResp($binaryContent);
    }

    public function gotoLast24h()
    {
        $tbl = \DB::connection('chromerivals')->table('cr_crawl_dates');
        $latest = $tbl->select(['timestamp'])->orderByDesc('timestamp')->limit(1)->first()->timestamp;
        $closest24hAgo = $tbl->select(['timestamp'])->where('timestamp','<=', Carbon::now()->subHours(24))->orderByDesc('timestamp')->limit(1)->first()->timestamp;

        $format = 'Y-m-d H:i';

        return redirect('https://beta.vi0lation.de/ranking/chromerivals/topkillsinterval?'.http_build_query(['from' => Carbon::parse($closest24hAgo)->format($format), 'to' => Carbon::parse($latest)->format($format)], null, '&', PHP_QUERY_RFC3986));
    }
}
