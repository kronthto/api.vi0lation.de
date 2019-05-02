<?php

namespace App\Http\Controllers\Ace;

use App\Http\Controllers\Controller;
use App\Services\OmiService;
use Illuminate\Http\Request;
use Kronthto\AOArchive\Omi\Reader\OmiReadResult;

class OmiQueryController extends Controller
{
    protected function getOmi(string $game): OmiReadResult
    {
        switch ($game) {
            case 'cr':
                $ns = OmiService::NS_CR;
                $path = config('cr.omiPath');
            break;
            default:
                abort(404, 'Game not found');
        }

        $service = app(OmiService::class, compact('path', 'ns'));

        return $service->getData();
    }

    public function query(Request $request)
    {
        $category = $request->get('category');
        if (!$category) {
            abort(400, 'Need to provide the data category (item,monster,..)');
        }

        $game = $request->route()->getAction('game');
        $omiData = $this->getOmi($game);

        if (!isset($omiData->{$category})) {
            abort(400, 'Invalid category');
        }

        $categoryData = collect($omiData->{$category});

        $filters = $request->get('where');
        if ($filters) {
            foreach (explode(',', $filters) as $filter) {
                $categoryData = $categoryData->where(...explode(':', $filter));
            }
        }

        return response()->json($categoryData)->setPublic()->setMaxAge(86400);
    }
}
