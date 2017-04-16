<?php

namespace App\Models\Ranking;

use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    protected $connection = 'rankingpro';
    protected $primaryKey = null;
    protected $keyType = null;
    public $incrementing = false;
    protected $dateFormat = 'Y-m-d';
    public $timestamps = false;

    protected $table = 'level';
}
