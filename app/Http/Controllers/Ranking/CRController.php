<?php

namespace App\Http\Controllers\Ranking;

use App\Http\Controllers\Controller;
use App\Services\ChromeRivalsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CRController extends Controller
{
    /** @var ChromeRivalsService */
    protected $service;

    public function __construct(ChromeRivalsService $crService)
    {
        $this->service = $crService;
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

        $response = response(['name' => $data->last()->name, 'data' => $dataFormatted]);
        $response->setPublic();
        $response->setMaxAge(1200);

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
