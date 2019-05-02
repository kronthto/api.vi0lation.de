<?php

namespace App\Services;

use Kronthto\AOArchive\Omi\Reader\OmiReadResult;
use Kronthto\AOEncrypt\HexXorer;

class OmiService
{
    public const NS_CR = 'CR';

    /** @var string */
    protected $path;
    /** @var string */
    protected $ns;

    /** @var OmiReadResult|null */
    protected $data;

    public function __construct(string $path, string $ns)
    {
        $this->path = $path;
        $this->ns = $ns;
    }

    protected function loadData(): OmiReadResult
    {
        return \Cache::remember(sprintf('omi.tex_'.$this->ns), 180, function(): OmiReadResult {
            $omiReader = new \Kronthto\AOArchive\Omi\Reader\OmiReader();

            $readOmi = $omiReader->parse(file_get_contents($this->path));

            if ($this->ns === self::NS_CR) {
                $xor = new HexXorer(config('cr.xorKeyHex'));
                $readOmi = $readOmi->map([$xor, 'doXor']);
            }

            return $readOmi->parseToEntities();
        });
    }

    public function getData(): OmiReadResult
    {
        if ($this->data === null) {
            $this->data = $this->loadData();
        }
        return $this->data;
    }
}
