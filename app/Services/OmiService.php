<?php

namespace App\Services;

use Kronthto\AOArchive\Omi\Reader\OmiReadResult;
use Kronthto\AOEncrypt\HexXorer;

class OmiService
{
    public const NS_CR = 'CR';
    protected const KEEP_CATS = [
        'monsters',
        'rareitems',
        'mysteryitemdrop',
        'item',
        'MIXING_INFO',
    ];

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
        return igbinary_unserialize(\Cache::remember(sprintf('omi.tex_'.$this->ns), 360, function(): OmiReadResult {
            $omiReader = new \Kronthto\AOArchive\Omi\Reader\OmiReader();

            $readOmi = $omiReader->parse(file_get_contents($this->path));

            // remove null vals / uninterssant
            foreach ($readOmi as $key => &$omiNsValue) {
                if (!in_array($key, static::KEEP_CATS)) {
                    unset($readOmi->{$key});
                }
            }

            if ($this->ns === self::NS_CR) {
                $xor = new HexXorer(config('cr.xorKeyHex'));
                $readOmi = $readOmi->map([$xor, 'doXor']);
            }

            $serialized = igbinary_serialize($readOmi->parseToEntities());

            if (\strlen($serialized) < 10000) {
                throw new \RuntimeException('Omi parsing / serialization error - too short');
            }

            return $serialized;
        }));
    }

    public function getData(): OmiReadResult
    {
        if ($this->data === null) {
            $this->data = $this->loadData();
        }
        return $this->data;
    }
}
