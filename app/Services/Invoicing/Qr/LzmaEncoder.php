<?php

namespace App\Services\Invoicing\Qr;

/**
 * Minimalistický LZMA1 kompresor v čistom PHP.
 *
 * Prečo vlastný? Štandard PAY by square predpisuje, že dáta v QR kóde musia
 * byť skomprimované surovým LZMA1 streamom (lc=3, lp=0, pb=2). PHP nemá
 * LZMA v jadre a ťahať kvôli 150 bajtom binárnu extenziu je nezmysel.
 *
 * Trik: LZMA stream nemusí obsahovať žiadne zhody (match). Stačí, ak sa
 * každý bajt zakóduje ako literál cez range coder. Výsledok je plne
 * dekódovateľný ktorýmkoľvek LZMA dekodérom – vrátane bankových appiek –
 * len je zhruba o percento väčší než vstup. Pri 200 bajtoch je to jedno.
 *
 * Implementácia zodpovedá referenčnému LzmaEnc.c od Igora Pavlova.
 */
class LzmaEncoder
{
    private const BIT_MODEL_TOTAL = 1 << 11;
    private const MOVE_BITS = 5;
    private const TOP_VALUE = 1 << 24;

    /** Literal context bits – koľko horných bitov predošlého bajtu tvorí kontext. */
    private const LC = 3;

    /** Literal position bits. */
    private const LP = 0;

    /** Position bits. */
    private const PB = 2;

    /** @var array<int, int> */
    private array $literalProbs = [];

    /** @var array<int, int> */
    private array $isMatchProbs = [];

    private int $low = 0;

    private int $range = 0xFFFFFFFF;

    private int $cache = 0;

    private int $cacheSize = 1;

    private string $out = '';

    /**
     * Skomprimuje dáta na surový LZMA1 stream (bez 13-bajtovej hlavičky).
     * Dekodér musí poznať lc/lp/pb a dĺžku výstupu z vonkajšieho kontajnera –
     * presne tak, ako to definuje by square.
     */
    public function compress(string $data): string
    {
        $this->reset();

        $litContexts = 1 << (self::LC + self::LP);
        $this->literalProbs = array_fill(0, $litContexts * 0x300, self::BIT_MODEL_TOTAL >> 1);
        $this->isMatchProbs = array_fill(0, 12 << 4, self::BIT_MODEL_TOTAL >> 1);

        $posMask = (1 << self::PB) - 1;
        $litPosMask = (1 << self::LP) - 1;

        $state = 0;      // po literáli zostáva vždy 0
        $previous = 0;
        $length = strlen($data);

        for ($pos = 0; $pos < $length; $pos++) {
            // Príznak "toto nie je zhoda" – vždy 0, lebo emitujeme len literály.
            $this->encodeBit($this->isMatchProbs, ($state << 4) + ($pos & $posMask), 0);

            $context = ((($pos & $litPosMask) << self::LC) + ($previous >> (8 - self::LC))) * 0x300;
            $byte = ord($data[$pos]);

            $this->encodeLiteral($context, $byte);

            $previous = $byte;
            $state = $state < 4 ? 0 : ($state < 10 ? $state - 3 : $state - 6);
        }

        return $this->flush();
    }

    /** Bajt vlastností tak, ako ho očakáva klasická LZMA hlavička: (pb*5 + lp)*9 + lc. */
    public static function propertiesByte(): int
    {
        return (self::PB * 5 + self::LP) * 9 + self::LC;
    }

    private function reset(): void
    {
        $this->low = 0;
        $this->range = 0xFFFFFFFF;
        $this->cache = 0;
        $this->cacheSize = 1;
        $this->out = '';
    }

    private function encodeLiteral(int $context, int $byte): void
    {
        $symbol = $byte | 0x100;

        do {
            $this->encodeBit($this->literalProbs, $context + ($symbol >> 8), ($symbol >> 7) & 1);
            $symbol <<= 1;
        } while ($symbol < 0x10000);
    }

    /**
     * Jeden bit cez adaptívny binárny range coder.
     *
     * @param  array<int, int>  $probs
     */
    private function encodeBit(array &$probs, int $index, int $bit): void
    {
        $bound = ($this->range >> 11) * $probs[$index];

        if ($bit === 0) {
            $this->range = $bound;
            $probs[$index] += (self::BIT_MODEL_TOTAL - $probs[$index]) >> self::MOVE_BITS;
        } else {
            $this->low += $bound;
            $this->range -= $bound;
            $probs[$index] -= $probs[$index] >> self::MOVE_BITS;
        }

        while ($this->range < self::TOP_VALUE) {
            $this->range = ($this->range << 8) & 0xFFFFFFFF;
            $this->shiftLow();
        }
    }

    /**
     * Posun spodných 8 bitov na výstup vrátane prenosu.
     * `low` je zámerne 33-bitové – 33. bit je carry.
     */
    private function shiftLow(): void
    {
        if ($this->low < 0xFF000000 || $this->low > 0xFFFFFFFF) {
            $carry = $this->low > 0xFFFFFFFF ? 1 : 0;
            $temp = $this->cache;

            do {
                $this->out .= chr(($temp + $carry) & 0xFF);
                $temp = 0xFF;
            } while (--$this->cacheSize);

            $this->cache = ($this->low >> 24) & 0xFF;
        }

        $this->cacheSize++;
        $this->low = ($this->low << 8) & 0xFFFFFFFF;
    }

    private function flush(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $this->shiftLow();
        }

        return $this->out;
    }
}
