<?php

namespace App\Http\Controllers\Ranking;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Validation\Validator as ValidatorInterface;
use Validator;

abstract class RankingController extends Controller
{
    /**
     * Creates a validator instance checking from and to.
     *
     * @param array $input
     * @return ValidatorInterface
     */
    public static function validateFromTo(array $input): ValidatorInterface
    {
        return Validator::make($input, [
            'cmppast' => 'date_format:Y-m-d|before:today',
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
        ]);
    }
}
