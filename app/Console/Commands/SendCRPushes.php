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

    public function __construct()
    {
        parent::__construct();
        $this->pushSrv = app(PushSendService::class);
    }

    public function handle()
    {
        switch ($this->argument('type')) {
            case 2:
                $this->handleSP($this->argument('map'));
                break;
        }
    }

    protected function handleSp(int $mapIndex)
    {
        PushSub::query()->each(function(PushSub $pushSub) use ($mapIndex) {
            // TODO: Error handling for json access?
            if (!in_array('sp', $pushSub->config['crevents'], true)) {
                return;
            }
            $this->pushSrv->queueMessage($pushSub, $mapIndex);
        });
        iterator_to_array($this->pushSrv->flush());
    }
}
