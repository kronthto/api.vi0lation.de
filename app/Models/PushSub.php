<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushSub extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;
    protected $table = 'pushsubs';
    protected $fillable = ['id', 'endpoint', 'token', 'key', 'origin', 'config'];
    protected $casts = ['config' => 'json'];
    protected $hidden = ['token', 'key', 'endpoint'];

    public static function createIdByEndpoint(string $endpoint)
    {
        return sha1($endpoint);
    }
}
