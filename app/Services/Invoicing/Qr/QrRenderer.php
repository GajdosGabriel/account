<?php

namespace App\Services\Invoicing\Qr;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Throwable;

/**
 * Vykreslenie QR matice do PNG dátovej URI.
 *
 * Zámerne nepoužívame Writer + SVG backend – dompdf vie SVG len čiastočne
 * a QR kód potrebuje ostré hrany. PNG cez GD je spoľahlivé v prehliadači,
 * v PDF aj v e-maile.
 */
class QrRenderer
{
    /** Je knižnica na kódovanie QR k dispozícii? */
    public function available(): bool
    {
        return class_exists(Encoder::class) && function_exists('imagecreatetruecolor');
    }

    /**
     * @return string|null  data:image/png;base64,… alebo null, ak sa nedá vykresliť
     */
    public function dataUri(string $text, int $size = 320, int $margin = 4): ?string
    {
        $png = $this->png($text, $size, $margin);

        return $png ? 'data:image/png;base64,'.base64_encode($png) : null;
    }

    public function png(string $text, int $size = 320, int $margin = 4): ?string
    {
        if (! $this->available()) {
            return null;
        }

        try {
            $matrix = $this->matrix($text);
        } catch (Throwable) {
            return null;
        }

        $modules = count($matrix);
        $total = $modules + 2 * $margin;

        // Celočíselná veľkosť modulu, inak QR "plávajú" a čítačky sa dusia.
        $scale = max(1, (int) floor($size / $total));
        $dimension = $total * $scale;

        $image = imagecreatetruecolor($dimension, $dimension);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        imagefilledrectangle($image, 0, 0, $dimension - 1, $dimension - 1, $white);

        foreach ($matrix as $y => $row) {
            foreach ($row as $x => $filled) {
                if (! $filled) {
                    continue;
                }

                $left = ($x + $margin) * $scale;
                $top = ($y + $margin) * $scale;

                imagefilledrectangle($image, $left, $top, $left + $scale - 1, $top + $scale - 1, $black);
            }
        }

        ob_start();
        imagepng($image, null, 9);
        $png = (string) ob_get_clean();

        imagedestroy($image);

        return $png;
    }

    /**
     * Matica true/false modulov.
     *
     * @return array<int, array<int, bool>>
     */
    protected function matrix(string $text): array
    {
        // bacon-qr-code 2.x používa statické metódy, 3.x natívny enum.
        $level = enum_exists(ErrorCorrectionLevel::class)
            ? constant(ErrorCorrectionLevel::class.'::M')
            : ErrorCorrectionLevel::M();

        $encoded = Encoder::encode($text, $level, 'UTF-8');
        $byteMatrix = $encoded->getMatrix();

        $matrix = [];

        for ($y = 0; $y < $byteMatrix->getHeight(); $y++) {
            for ($x = 0; $x < $byteMatrix->getWidth(); $x++) {
                $matrix[$y][$x] = $byteMatrix->get($x, $y) === 1;
            }
        }

        return $matrix;
    }
}
