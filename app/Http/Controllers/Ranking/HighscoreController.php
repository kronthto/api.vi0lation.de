<?php

namespace App\Http\Controllers\Ranking;

use App\Exceptions\RankingDateNotFoundException;
use App\Services\RankingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HighscoreController extends RankingController
{
    const FIELD_MAPPING = [
        'from' => 'cmppast',
        'to' => 'date',
    ];

    /** @var RankingService */
    protected $service;

    public function __construct(RankingService $rankingService)
    {
        $this->service = $rankingService;
    }

    public function get(Request $request)
    {
        $validator = static::validateFromTo($request->query());
        if ($validator->fails()) {
            return response($validator->getMessageBag(), Response::HTTP_BAD_REQUEST);
        }

        $from = $request->query('cmppast');
        $to = $request->query('date');
        if ($from && $from >= $to) {
            return response(['date' => [trans('The date field must be greater than cmppast.')]], Response::HTTP_BAD_REQUEST);
        }

        $data = null;
        try {
            if ($from) {
                $data = $this->service->getHighscoresDataCompare($from, $to);
                $data->transform(function ($row) use ($from, $to) {
                    $row->cmpdata = [];
                    $row->data = [];
                    foreach ($row as $key => $value) {
                        if (starts_with($key, 'from_')) {
                            $row->cmpdata[substr($key, 5)] = $value;
                            unset($row->$key);
                        }
                        if (starts_with($key, 'to_')) {
                            $row->data[substr($key, 3)] = $value;
                            unset($row->$key);
                        }
                    }
                    return $row;
                });
            } else {
                $data = $this->service->getHighscoresData($to);
                $data->transform(function ($row) use ($to) {
                    $row->data = [];
                    foreach ($row as $key => $value) {
                        if (starts_with($key, 'data_')) {
                            $row->data[substr($key, 5)] = $value;
                            unset($row->$key);
                        }
                    }
                    return $row;
                });
            }
        } catch (RankingDateNotFoundException $rankingException) {
            $field = $rankingException->getField();

            return response([static::FIELD_MAPPING[$field] ?? $field => [$rankingException->getMessage()]], Response::HTTP_NOT_FOUND);
        }

        $response = response($data);
        $response->setPublic();
        $response->setMaxAge(1728000);
        return $response;
    }
}
