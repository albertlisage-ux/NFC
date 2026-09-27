<?php
/**
 * QR Code generation, implemented in PHP with no external service.
 *
 * Rendering locally matters for two reasons: a QR code is generated for every
 * tag URL, and sending those URLs to a third-party image API would leak which
 * tags exist. See ISO/IEC 18004 for the algorithm this follows.
 *
 * Supported: byte mode (UTF-8), error correction levels L and M, versions
 * 1 to 15. That covers the tag URLs used here with a wide margin.
 */

if (!defined('LINKTEC_SECURE')) {
    http_response_code(403);
    exit('Access Denied');
}

final class DatQrCode
{
    private const ECC_FORMAT_BITS = ['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2];
    private const MIN_VERSION = 1;
    private const MAX_VERSION = 15;

    /**
     * Error correction block layout.
     *
     * version => [level => [ec codewords per block, group1 blocks, group1 data
     *                       codewords, group2 blocks, group2 data codewords]]
     */
    private const BLOCKS = [
        1  => ['L' => [7, 1, 19, 0, 0],   'M' => [10, 1, 16, 0, 0]],
        2  => ['L' => [10, 1, 34, 0, 0],  'M' => [16, 1, 28, 0, 0]],
        3  => ['L' => [15, 1, 55, 0, 0],  'M' => [26, 1, 44, 0, 0]],
        4  => ['L' => [20, 1, 80, 0, 0],  'M' => [18, 2, 32, 0, 0]],
        5  => ['L' => [26, 1, 108, 0, 0], 'M' => [24, 2, 43, 0, 0]],
        6  => ['L' => [18, 2, 68, 0, 0],  'M' => [16, 4, 27, 0, 0]],
        7  => ['L' => [20, 2, 78, 0, 0],  'M' => [18, 4, 31, 0, 0]],
        8  => ['L' => [24, 2, 97, 0, 0],  'M' => [22, 2, 38, 2, 39]],
        9  => ['L' => [30, 2, 116, 0, 0], 'M' => [22, 3, 36, 2, 37]],
        10 => ['L' => [18, 2, 68, 2, 69], 'M' => [26, 4, 43, 1, 44]],
        11 => ['L' => [20, 4, 81, 0, 0],  'M' => [30, 1, 50, 4, 51]],
        12 => ['L' => [24, 2, 92, 2, 93], 'M' => [22, 6, 36, 2, 37]],
        13 => ['L' => [26, 4, 107, 0, 0], 'M' => [22, 8, 37, 1, 38]],
        14 => ['L' => [30, 3, 115, 1, 116], 'M' => [24, 4, 40, 5, 41]],
        15 => ['L' => [22, 5, 87, 1, 88], 'M' => [24, 5, 41, 5, 42]],
    ];

    private int $version;
    private string $ecl;
    private int $size;
    /** @var array<int, array<int, bool>> row-major: [y][x] */
    private array $modules = [];
    /** @var array<int, array<int, bool>> */
    private array $isFunction = [];

    private function __construct(int $version, string $ecl)
    {
        $this->version = $version;
        $this->ecl = $ecl;
        $this->size = $version * 4 + 17;

        for ($y = 0; $y < $this->size; $y++) {
            $this->modules[$y] = array_fill(0, $this->size, false);
            $this->isFunction[$y] = array_fill(0, $this->size, false);
        }
    }

    /* ---------------------------------------------------------------------
     * Encoding entry points
     * ------------------------------------------------------------------ */

    public static function encodeText(string $text, string $ecl = 'M', ?int $minVersion = null): self
    {
        $ecl = strtoupper($ecl);
        if (!isset(self::ECC_FORMAT_BITS[$ecl]) || !isset(self::BLOCKS[1][$ecl])) {
            throw new InvalidArgumentException('Unsupported error correction level: ' . $ecl);
        }

        $bytes = array_values(unpack('C*', $text) ?: []);
        $version = null;
        for ($v = max(self::MIN_VERSION, (int) ($minVersion ?? self::MIN_VERSION)); $v <= self::MAX_VERSION; $v++) {
            if (count($bytes) <= self::byteCapacity($v, $ecl)) {
                $version = $v;
                break;
            }
        }

        if ($version === null) {
            throw new InvalidArgumentException('Text is too long for a version ' . self::MAX_VERSION . ' QR code at level ' . $ecl . '.');
        }

        $qr = new self($version, $ecl);
        $codewords = self::buildCodewords($bytes, $version, $ecl);
        $qr->drawFunctionPatterns();
        $qr->drawCodewords($codewords);

        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $qr->applyMask($mask);
            $qr->drawFormatBits($mask);
            $penalty = $qr->penaltyScore();
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
            }
            $qr->applyMask($mask); // XOR again to undo
        }

        $qr->applyMask($bestMask);
        $qr->drawFormatBits($bestMask);

        return $qr;
    }

    public static function byteCapacity(int $version, string $ecl = 'M'): int
    {
        [$ecPerBlock, $g1, $g1Data, $g2, $g2Data] = self::BLOCKS[$version][$ecl];
        $dataCodewords = $g1 * $g1Data + $g2 * $g2Data;

        // 4 bit mode indicator + 8 bit (versions 1-9) or 16 bit length field.
        $headerBits = $version <= 9 ? 12 : 20;

        return intdiv($dataCodewords * 8 - $headerBits, 8);
    }

    /* ---------------------------------------------------------------------
     * Bit stream, padding, error correction, interleaving
     * ------------------------------------------------------------------ */

    private static function buildCodewords(array $bytes, int $version, string $ecl): array
    {
        [$ecPerBlock, $g1, $g1Data, $g2, $g2Data] = self::BLOCKS[$version][$ecl];
        $dataCodewords = $g1 * $g1Data + $g2 * $g2Data;

        $bits = [];
        $append = static function (int $value, int $length) use (&$bits): void {
            for ($i = $length - 1; $i >= 0; $i--) {
                $bits[] = (($value >> $i) & 1) === 1;
            }
        };

        $append(0b0100, 4);                        // byte mode
        $append(count($bytes), $version <= 9 ? 8 : 16);
        foreach ($bytes as $byte) {
            $append($byte, 8);
        }

        // Terminator, then pad to the codeword boundary.
        $capacityBits = $dataCodewords * 8;
        $append(0, min(4, $capacityBits - count($bits)));
        $append(0, (8 - count($bits) % 8) % 8);

        // Pad codewords alternate 0xEC / 0x11.
        for ($padByte = 0xEC; count($bits) < $capacityBits; $padByte ^= 0xEC ^ 0x11) {
            $append($padByte, 8);
        }

        $dataBytes = [];
        for ($i = 0; $i < $dataCodewords; $i++) {
            $value = 0;
            for ($j = 0; $j < 8; $j++) {
                $value = ($value << 1) | ($bits[$i * 8 + $j] ? 1 : 0);
            }
            $dataBytes[] = $value;
        }

        // Split into blocks and compute the Reed-Solomon codewords.
        $blocks = [];
        $offset = 0;
        for ($i = 0; $i < $g1; $i++) {
            $blocks[] = self::addEcc(array_slice($dataBytes, $offset, $g1Data), $ecPerBlock);
            $offset += $g1Data;
        }
        for ($i = 0; $i < $g2; $i++) {
            $blocks[] = self::addEcc(array_slice($dataBytes, $offset, $g2Data), $ecPerBlock);
            $offset += $g2Data;
        }

        return self::interleave($blocks, $ecPerBlock);
    }

    /** Append Reed-Solomon error correction codewords to one block. */
    private static function addEcc(array $data, int $ecLength): array
    {
        $generator = self::rsGeneratorPoly($ecLength);
        $coefficients = array_slice($generator, 1);
        $remainder = array_fill(0, $ecLength, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ $remainder[0];
            array_shift($remainder);
            $remainder[] = 0;
            foreach ($coefficients as $index => $coefficient) {
                $remainder[$index] ^= self::gfMultiply($coefficient, $factor);
            }
        }

        return array_merge($data, $remainder);
    }

    /** Generator polynomial for the given number of error correction codewords. */
    private static function rsGeneratorPoly(int $degree): array
    {
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $root = self::gfPower(2, $i);
            $next = array_fill(0, count($poly) + 1, 0);
            foreach ($poly as $index => $coefficient) {
                $next[$index] ^= $coefficient;
                $next[$index + 1] ^= self::gfMultiply($coefficient, $root);
            }
            $poly = $next;
        }

        return $poly;
    }

    /** Interleave data codewords, then error correction codewords. */
    private static function interleave(array $blocks, int $ecLength): array
    {
        $result = [];
        $shortest = min(array_map(static fn ($block) => count($block) - $ecLength, $blocks));

        for ($i = 0; $i < $shortest; $i++) {
            foreach ($blocks as $block) {
                $result[] = $block[$i];
            }
        }
        // Longer blocks contribute one extra data codeword.
        foreach ($blocks as $block) {
            if (count($block) - $ecLength > $shortest) {
                $result[] = $block[$shortest];
            }
        }
        for ($i = 0; $i < $ecLength; $i++) {
            foreach ($blocks as $block) {
                $result[] = $block[count($block) - $ecLength + $i];
            }
        }

        return $result;
    }

    private static function gfMultiply(int $x, int $y): int
    {
        $product = 0;
        for ($i = 7; $i >= 0; $i--) {
            $product = (($product << 1) ^ ((($product >> 7) & 1) * 0x11D)) & 0xFF;
            $product ^= ((($y >> $i) & 1) * $x);
        }

        return $product & 0xFF;
    }

    private static function gfPower(int $base, int $exponent): int
    {
        $result = 1;
        for ($i = 0; $i < $exponent; $i++) {
            $result = self::gfMultiply($result, $base);
        }

        return $result;
    }

    /**
     * Reed-Solomon syndromes of a codeword block. Used by the test suite: a
     * correct block evaluates to all zeroes at every root of the generator.
     */
    public static function syndromes(array $codewords, int $ecLength): array
    {
        $syndromes = [];
        for ($i = 0; $i < $ecLength; $i++) {
            $root = self::gfPower(2, $i);
            $value = 0;
            foreach ($codewords as $codeword) {
                $value = self::gfMultiply($value, $root) ^ $codeword;
            }
            $syndromes[] = $value;
        }

        return $syndromes;
    }

    /** Exposed for tests: the raw codeword sequence of a text payload. */
    public static function rawCodewords(string $text, string $ecl = 'M'): array
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        $ecl = strtoupper($ecl);
        $version = null;
        for ($v = self::MIN_VERSION; $v <= self::MAX_VERSION; $v++) {
            if (count($bytes) <= self::byteCapacity($v, $ecl)) {
                $version = $v;
                break;
            }
        }
        if ($version === null) {
            throw new InvalidArgumentException('Text is too long');
        }

        return self::buildCodewords($bytes, $version, $ecl);
    }

    /**
     * Exposed for tests: the data + error correction blocks of a payload, so
     * the suite can verify the Reed-Solomon codewords independently.
     *
     * @return array<int, array{data: array<int,int>, codewords: array<int,int>, ec: int}>
     */
    public static function blocks(string $text, string $ecl = 'M'): array
    {
        $ecl = strtoupper($ecl);
        $version = self::versionFor($text, $ecl);
        [$ecPerBlock, $g1, $g1Data, $g2, $g2Data] = self::BLOCKS[$version][$ecl];

        $bytes = array_values(unpack('C*', $text) ?: []);
        $interleaved = self::buildCodewords($bytes, $version, $ecl);

        // Rebuild the blocks from the interleaved sequence.
        $lengths = array_merge(array_fill(0, $g1, $g1Data), array_fill(0, $g2, $g2Data));
        $blockCount = count($lengths);
        $dataBlocks = array_fill(0, $blockCount, []);

        $shortest = min($lengths);
        $index = 0;
        for ($i = 0; $i < $shortest; $i++) {
            for ($b = 0; $b < $blockCount; $b++) {
                $dataBlocks[$b][] = $interleaved[$index++];
            }
        }
        for ($b = 0; $b < $blockCount; $b++) {
            if ($lengths[$b] > $shortest) {
                $dataBlocks[$b][] = $interleaved[$index++];
            }
        }

        $ecBlocks = array_fill(0, $blockCount, []);
        for ($i = 0; $i < $ecPerBlock; $i++) {
            for ($b = 0; $b < $blockCount; $b++) {
                $ecBlocks[$b][] = $interleaved[$index++];
            }
        }

        $blocks = [];
        for ($b = 0; $b < $blockCount; $b++) {
            $blocks[] = [
                'data' => $dataBlocks[$b],
                'codewords' => array_merge($dataBlocks[$b], $ecBlocks[$b]),
                'ec' => $ecPerBlock,
            ];
        }

        return $blocks;
    }

    public static function versionFor(string $text, string $ecl = 'M'): int
    {
        $length = strlen($text);
        for ($v = self::MIN_VERSION; $v <= self::MAX_VERSION; $v++) {
            if ($length <= self::byteCapacity($v, $ecl)) {
                return $v;
            }
        }
        throw new InvalidArgumentException('Text is too long');
    }

    /* ---------------------------------------------------------------------
     * Module layout
     * ------------------------------------------------------------------ */

    private function setFunctionModule(int $x, int $y, bool $isDark): void
    {
        if ($x < 0 || $y < 0 || $x >= $this->size || $y >= $this->size) {
            return;
        }
        $this->modules[$y][$x] = $isDark;
        $this->isFunction[$y][$x] = true;
    }

    private function drawFunctionPatterns(): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunctionModule(6, $i, $i % 2 === 0);
            $this->setFunctionModule($i, 6, $i % 2 === 0);
        }

        $this->drawFinderPattern(3, 3);
        $this->drawFinderPattern($this->size - 4, 3);
        $this->drawFinderPattern(3, $this->size - 4);

        $alignment = self::alignmentPatternPositions($this->version, $this->size);
        $count = count($alignment);
        for ($i = 0; $i < $count; $i++) {
            for ($j = 0; $j < $count; $j++) {
                $isCorner = ($i === 0 && $j === 0)
                    || ($i === 0 && $j === $count - 1)
                    || ($i === $count - 1 && $j === 0);
                if (!$isCorner) {
                    $this->drawAlignmentPattern($alignment[$i], $alignment[$j]);
                }
            }
        }

        $this->drawFormatBits(0); // reserves the format area with placeholder bits
        if ($this->version >= 7) {
            $this->drawVersionBits();
        }
    }

    private function drawFinderPattern(int $x, int $y): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $distance = max(abs($dx), abs($dy));
                $this->setFunctionModule($x + $dx, $y + $dy, $distance !== 2 && $distance !== 4);
            }
        }
    }

    private function drawAlignmentPattern(int $x, int $y): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunctionModule($x + $dx, $y + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    private static function alignmentPatternPositions(int $version, int $size): array
    {
        if ($version === 1) {
            return [];
        }

        $numAlign = intdiv($version, 7) + 2;
        $step = ($version === 32)
            ? 26
            : intdiv($version * 4 + $numAlign * 2 + 1, $numAlign * 2 - 2) * 2;

        $positions = [6];
        for ($i = $numAlign - 1, $pos = $size - 7; $i >= 1; $i--, $pos -= $step) {
            $positions[$i] = $pos;
        }
        ksort($positions);

        return array_values($positions);
    }

    private function drawFormatBits(int $mask): void
    {
        $data = (self::ECC_FORMAT_BITS[$this->ecl] << 3) | $mask;
        $remainder = $data;
        for ($i = 0; $i < 10; $i++) {
            $remainder = ($remainder << 1) ^ ((($remainder >> 9) & 1) * 0x537);
        }
        $bits = (($data << 10) | $remainder) ^ 0x5412;

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunctionModule(8, $i, self::bit($bits, $i));
        }
        $this->setFunctionModule(8, 7, self::bit($bits, 6));
        $this->setFunctionModule(8, 8, self::bit($bits, 7));
        $this->setFunctionModule(7, 8, self::bit($bits, 8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunctionModule(14 - $i, 8, self::bit($bits, $i));
        }

        for ($i = 0; $i < 8; $i++) {
            $this->setFunctionModule($this->size - 1 - $i, 8, self::bit($bits, $i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunctionModule(8, $this->size - 15 + $i, self::bit($bits, $i));
        }
        $this->setFunctionModule(8, $this->size - 8, true); // always dark
    }

    private function drawVersionBits(): void
    {
        $remainder = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $remainder = ($remainder << 1) ^ ((($remainder >> 11) & 1) * 0x1F25);
        }
        $bits = ($this->version << 12) | $remainder;

        for ($i = 0; $i < 18; $i++) {
            $bit = self::bit($bits, $i);
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunctionModule($a, $b, $bit);
            $this->setFunctionModule($b, $a, $bit);
        }
    }

    private function drawCodewords(array $codewords): void
    {
        $totalBits = count($codewords) * 8;
        $bitIndex = 0;

        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5; // skip the vertical timing pattern column
            }
            for ($vertical = 0; $vertical < $this->size; $vertical++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $this->size - 1 - $vertical : $vertical;

                    if (!$this->isFunction[$y][$x] && $bitIndex < $totalBits) {
                        $this->modules[$y][$x] = self::bit($codewords[$bitIndex >> 3], 7 - ($bitIndex & 7));
                        $bitIndex++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                if (self::maskBit($mask, $x, $y)) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    private static function maskBit(int $mask, int $x, int $y): bool
    {
        switch ($mask) {
            case 0: return ($x + $y) % 2 === 0;
            case 1: return $y % 2 === 0;
            case 2: return $x % 3 === 0;
            case 3: return ($x + $y) % 3 === 0;
            case 4: return (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0;
            case 5: return ($x * $y) % 2 + ($x * $y) % 3 === 0;
            case 6: return (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0;
            case 7: return (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0;
        }

        return false;
    }

    /** Standard penalty rules N1..N4, used to pick the most readable mask. */
    private function penaltyScore(): int
    {
        $score = 0;

        // N1: runs of five or more identical modules.
        for ($y = 0; $y < $this->size; $y++) {
            $runLength = 1;
            for ($x = 1; $x < $this->size; $x++) {
                if ($this->modules[$y][$x] === $this->modules[$y][$x - 1]) {
                    $runLength++;
                    if ($runLength === 5) {
                        $score += 3;
                    } elseif ($runLength > 5) {
                        $score++;
                    }
                } else {
                    $runLength = 1;
                }
            }
        }
        for ($x = 0; $x < $this->size; $x++) {
            $runLength = 1;
            for ($y = 1; $y < $this->size; $y++) {
                if ($this->modules[$y][$x] === $this->modules[$y - 1][$x]) {
                    $runLength++;
                    if ($runLength === 5) {
                        $score += 3;
                    } elseif ($runLength > 5) {
                        $score++;
                    }
                } else {
                    $runLength = 1;
                }
            }
        }

        // N2: 2x2 blocks of one colour.
        for ($y = 0; $y < $this->size - 1; $y++) {
            for ($x = 0; $x < $this->size - 1; $x++) {
                $colour = $this->modules[$y][$x];
                if ($colour === $this->modules[$y][$x + 1]
                    && $colour === $this->modules[$y + 1][$x]
                    && $colour === $this->modules[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }

        // N3: finder-like 1:1:3:1:1 patterns.
        $patternA = [true, false, true, true, true, false, true, false, false, false, false];
        $patternB = [false, false, false, false, true, false, true, true, true, false, true];
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x + 11 <= $this->size; $x++) {
                $window = [];
                for ($k = 0; $k < 11; $k++) {
                    $window[] = $this->modules[$y][$x + $k];
                }
                if ($window === $patternA || $window === $patternB) {
                    $score += 40;
                }
            }
        }
        for ($x = 0; $x < $this->size; $x++) {
            for ($y = 0; $y + 11 <= $this->size; $y++) {
                $window = [];
                for ($k = 0; $k < 11; $k++) {
                    $window[] = $this->modules[$y + $k][$x];
                }
                if ($window === $patternA || $window === $patternB) {
                    $score += 40;
                }
            }
        }

        // N4: deviation from a 50/50 dark to light balance.
        $dark = 0;
        foreach ($this->modules as $row) {
            foreach ($row as $module) {
                if ($module) {
                    $dark++;
                }
            }
        }
        $total = $this->size * $this->size;
        $ratio = (int) floor(abs($dark * 20 - $total * 10) / $total);
        $score += $ratio * 10;

        return $score;
    }

    /* ---------------------------------------------------------------------
     * Output
     * ------------------------------------------------------------------ */

    public function size(): int
    {
        return $this->size;
    }

    public function moduleAt(int $x, int $y): bool
    {
        return $this->modules[$y][$x] ?? false;
    }

    public function isDark(int $x, int $y): bool
    {
        return $this->moduleAt($x, $y);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function errorCorrectionLevel(): string
    {
        return $this->ecl;
    }

    /** SVG markup, scalable and crisp at any size. */
    public function toSvg(int $scale = 8, int $border = 4, string $dark = '#0f172a', string $light = '#ffffff'): string
    {
        $scale = max(1, $scale);
        $border = max(0, $border);
        $dimension = ($this->size + $border * 2) * $scale;

        $path = [];
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->modules[$y][$x]) {
                    $path[] = 'M' . (($x + $border) * $scale) . ' ' . (($y + $border) * $scale)
                        . 'h' . $scale . 'v' . $scale . 'h-' . $scale . 'z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dimension . '" height="' . $dimension . '"'
            . ' viewBox="0 0 ' . $dimension . ' ' . $dimension . '" shape-rendering="crispEdges" role="img">'
            . '<rect width="' . $dimension . '" height="' . $dimension . '" fill="' . e($light) . '"/>'
            . '<path fill="' . e($dark) . '" d="' . implode('', $path) . '"/>'
            . '</svg>';
    }

    /** PNG bytes via GD, or null when the GD extension is missing. */
    public function toPng(int $scale = 8, int $border = 4, string $dark = '#0f172a', string $light = '#ffffff'): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $scale = max(1, $scale);
        $border = max(0, $border);
        $dimension = ($this->size + $border * 2) * $scale;

        $image = imagecreatetruecolor($dimension, $dimension);
        if ($image === false) {
            return null;
        }

        [$lightR, $lightG, $lightB] = self::hexToRgb($light);
        [$darkR, $darkG, $darkB] = self::hexToRgb($dark);
        $lightColour = imagecolorallocate($image, $lightR, $lightG, $lightB);
        $darkColour = imagecolorallocate($image, $darkR, $darkG, $darkB);

        imagefilledrectangle($image, 0, 0, $dimension, $dimension, $lightColour);
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if (!$this->modules[$y][$x]) {
                    continue;
                }
                $x0 = ($x + $border) * $scale;
                $y0 = ($y + $border) * $scale;
                imagefilledrectangle($image, $x0, $y0, $x0 + $scale - 1, $y0 + $scale - 1, $darkColour);
            }
        }

        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        self::freeImage($image);

        return is_string($bytes) ? $bytes : null;
    }

    /** imagedestroy() is a no-op since PHP 8.0 and deprecated in 8.5. */
    private static function freeImage($image): void
    {
        if (PHP_VERSION_ID < 80500 && function_exists('imagedestroy')) {
            imagedestroy($image);
        }
    }

    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return [15, 23, 42];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function bit(int $value, int $index): bool
    {
        return (($value >> $index) & 1) !== 0;
    }
}

/** Encode text into a QR code object, or null when it cannot fit. */
if (!function_exists('dat_qr')) {
    function dat_qr($text, $ecl = 'M')
    {
        try {
            return DatQrCode::encodeText((string) $text, $ecl);
        } catch (Throwable $e) {
            dat_log_error('QR encoding failed: ' . $e->getMessage(), []);
            return null;
        }
    }
}

/** Inline SVG for a QR code, ready to embed in a page. */
if (!function_exists('dat_qr_svg')) {
    function dat_qr_svg($text, $scale = 8, $border = 4, $ecl = 'M')
    {
        $qr = dat_qr($text, $ecl);
        return $qr === null ? '' : $qr->toSvg($scale, $border);
    }
}
