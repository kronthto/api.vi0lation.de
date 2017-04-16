<?php

namespace App\Exceptions;

class RankingDateNotFoundException extends RankingException
{
    protected $field;

    public function __construct($date, string $field)
    {
        parent::__construct('No data found for date '.$date);
        $this->field = $field;
    }

    public function getField()
    {
        return $this->field;
    }
}
