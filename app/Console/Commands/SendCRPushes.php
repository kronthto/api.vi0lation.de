<?php

namespace App\Console\Commands;

use App\Models\PushSub;
use App\Services\PushSendService;
use Illuminate\Console\Command;

class SendCRPushes extends Command
{
    protected $signature = 'cr:push {type} {map}';

    /** @var PushSendService */
    protected $pushSrv;

    protected $maps = [];

    public function __construct()
    {
        parent::__construct();
        $this->pushSrv = app(PushSendService::class);

        foreach (file(__DIR__.'/mapinfo.txt') as $map) {
            $splitMap = explode("\t", $map);
            $this->maps[(int) $splitMap[0]] = $splitMap[1];
        }
    }

    public function handle()
    {
        switch ($this->argument('type')) {
            case 2:
                $this->handleSP($this->argument('map'));
                break;
            case 3:
                $this->handleOp($this->argument('map'));
                break;
            case 4:
                $this->handleMs($this->argument('map'));
                break;
            case 27:
                $this->handleNke($this->argument('map'));
                break;
        }
    }

    protected function mapMap(int $mapIdx): string
    {
        return $this->maps[$mapIdx] ?? $mapIdx;
    }

    protected function eachSubs(string $config, string $payload)
    {
        PushSub::query()->each(function(PushSub $pushSub) use ($payload, $config) {
            // TODO: Error handling for json access?
            if (!in_array($config, $pushSub->config['crevents'], true)) {
                return;
            }
            $this->pushSrv->queueMessage($pushSub, $payload);
        });
        $res = $this->pushSrv->sendQueued();
        if ($res) {
            iterator_to_array($res);
        }
    }

    protected function handleSp(int $mapIndex)
    {
        $this->eachSubs('sp', 'SP in '.$this->mapMap($mapIndex));
    }

    protected function handleOp(int $mapIndex)
    {
        $this->eachSubs('op', 'OP soon in '.$this->mapMap($mapIndex));
    }

    protected function handleMs(int $mapIndex)
    {
        $this->eachSubs('ms', 'MS soon in '.$this->mapMap($mapIndex));
    }

    protected function handleNke(int $mapIndex)
    {
        $this->eachSubs('nke', 'NKE in '.$this->mapMap($mapIndex));
    }
}
