<?php

namespace App\Service;

class WifParser
{
    private array $sections = [];

    public function parse(string $wifContent): array
    {
        try {
            $lines = preg_split('/\r?\n/', $wifContent);
            $currentSection = null;

            foreach ($lines as $line) {
                $line = trim($line);

                // Skip empty lines and full-line comments
                if (empty($line) || str_starts_with($line, ';')) {
                    continue;
                }

                // Check for section header
                if (preg_match('/^\[([^\]]+)\]$/', $line, $matches)) {
                    $currentSection = strtoupper($matches[1]);
                    $this->sections[$currentSection] = [];
                    continue;
                }

                // Parse key-value pairs
                if ($currentSection && str_contains($line, '=')) {
                    $parts = explode('=', $line, 2);
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);

                    // Remove comments from value (after semicolon for numeric/boolean values)
                    // Per spec: comments allowed after numeric/boolean entries, not text strings
                    if ($this->isNumericOrBoolean($value)) {
                        $commentIndex = strpos($value, ';');
                        if ($commentIndex !== false) {
                            $value = trim(substr($value, 0, $commentIndex));
                        }
                    }

                    $this->sections[$currentSection][$key] = $value;
                }
            }

            // Validate required sections per WIF spec
            $this->validateWifFile();

            return $this->extractWeavingData();
        } catch (\Exception $error) {
            throw new \InvalidArgumentException('Failed to parse WIF file: ' . $error->getMessage());
        }
    }

    private function isNumericOrBoolean(string $value): bool
    {
        $trimmedValue = trim($value);
        
        // Check if it's numeric (integer, float, or comma-separated numbers)
        if (is_numeric($trimmedValue) || preg_match('/^[\d\s,.-]+$/', $trimmedValue)) {
            return true;
        }
        
        // Check if it's boolean
        $lowerValue = strtolower($trimmedValue);
        return in_array($lowerValue, ['true', 'false', 'yes', 'no', 'on', 'off', '1', '0']);
    }

    private function validateWifFile(): void
    {
        // Per WIF spec: WIF section is required
        if (!isset($this->sections['WIF'])) {
            throw new \InvalidArgumentException('WIF file must contain a [WIF] section');
        }

        // Per WIF spec: CONTENTS section lists which sections are present
        if (isset($this->sections['CONTENTS'])) {
            $contents = $this->sections['CONTENTS'];
            
            // Validate that claimed sections actually exist
            foreach ($contents as $sectionName => $present) {
                if ($this->parseBoolean($present) && !isset($this->sections[strtoupper($sectionName)])) {
                    // Log warning but don't fail - some flexibility for real-world files
                    error_log("Warning: CONTENTS claims section [$sectionName] is present but it was not found");
                }
            }
        }
    }

    private function extractWeavingData(): array
    {
        $data = [
            'metadata' => $this->extractMetadata(),
            'weaving' => $this->extractWeavingInfo(),
            'colors' => $this->extractColors(),
            'threading' => $this->extractThreading(),
            'treadling' => $this->extractTreadling(),
            'tieup' => $this->extractTieup(),
            'warp' => $this->extractWarp(),
            'weft' => $this->extractWeft(),
            'pattern' => null
        ];

        // Generate the weaving pattern
        $data['pattern'] = $this->generatePattern($data);

        return $data;
    }

    private function extractMetadata(): array
    {
        $wif = $this->sections['WIF'] ?? [];
        $text = $this->sections['TEXT'] ?? [];

        return [
            'version' => $wif['Version'] ?? '1.1',
            'date' => $wif['Date'] ?? '',
            'developers' => $wif['Developers'] ?? '',
            'source' => $wif['Source Program'] ?? '',
            'title' => $text['Title'] ?? 'Untitled Weaving',
            'author' => $text['Author'] ?? '',
            'address' => $text['Address'] ?? '',
            'email' => $text['EMail'] ?? '',
            'telephone' => $text['Telephone'] ?? '',
            'notes' => $text['Notes'] ?? ''
        ];
    }

    private function extractWeavingInfo(): array
    {
        $weaving = $this->sections['WEAVING'] ?? [];

        return [
            'shafts' => (int)($weaving['Shafts'] ?? 4),
            'treadles' => (int)($weaving['Treadles'] ?? 4),
            // Per WIF spec: Rising Shed is optional, defaults to true for most looms
            'risingShed' => $this->parseBoolean($weaving['Rising Shed'] ?? '', true)
        ];
    }

    private function extractColors(): array
    {
        // First, get the color palette metadata to understand the range
        $colorPalette = $this->sections['COLOR PALETTE'] ?? [];
        $colorTable = $this->sections['COLOR TABLE'] ?? [];
        
        // Parse the color range from COLOR PALETTE section
        $range = $this->parseColorRange($colorPalette['Range'] ?? '0,255');
        $maxRange = $range['max'];
        
        $colors = [];

        // Extract individual colors from COLOR TABLE
        foreach ($colorTable as $key => $colorValue) {
            if (preg_match('/^(\d+)$/', $key, $matches)) {
                $colorIndex = (int)$matches[1];

                // Parse RGB values (format: R,G,B)
                if (str_contains($colorValue, ',')) {
                    $rgbValues = array_map('intval', array_map('trim', explode(',', $colorValue)));
                    if (count($rgbValues) >= 3) {
                        $colors[$colorIndex] = [
                            'r' => round(($rgbValues[0] / $maxRange) * 255),
                            'g' => round(($rgbValues[1] / $maxRange) * 255),
                            'b' => round(($rgbValues[2] / $maxRange) * 255)
                        ];
                    }
                }
            }
        }

        // Default colors if none specified
        if (empty($colors)) {
            $colors[1] = ['r' => 0, 'g' => 0, 'b' => 0]; // Black
            $colors[2] = ['r' => 255, 'g' => 255, 'b' => 255]; // White
        }

        return [
            'colors' => $colors,
            'palette' => [
                'entries' => (int)($colorPalette['Entries'] ?? count($colors)),
                'form' => $colorPalette['Form'] ?? 'RGB',
                'range' => $range
            ]
        ];
    }

    private function parseColorRange(string $rangeString): array
    {
        // Parse range like "0,255" or "0,999"
        if (str_contains($rangeString, ',')) {
            $parts = explode(',', $rangeString);
            return [
                'min' => (int)trim($parts[0]),
                'max' => (int)trim($parts[1])
            ];
        }
        
        // Default to 0-255 if not specified or malformed
        return ['min' => 0, 'max' => 255];
    }

    private function extractThreading(): array
    {
        $threading = $this->sections['THREADING'] ?? [];
        $threadingData = [];

        foreach ($threading as $key => $value) {
            $threadNum = (int)$key;
            if ($threadNum > 0) {
                $threadingData[$threadNum] = (int)$value;
            }
        }

        return $threadingData;
    }

    private function extractTreadling(): array
    {
        $treadling = $this->sections['TREADLING'] ?? [];
        $treadlingData = [];

        foreach ($treadling as $key => $value) {
            $pickNum = (int)$key;
            if ($pickNum > 0) {
                $treadlingData[$pickNum] = (int)$value;
            }
        }

        return $treadlingData;
    }

    private function extractTieup(): array
    {
        $tieup = $this->sections['TIEUP'] ?? [];
        $tieupData = [];

        foreach ($tieup as $key => $value) {
            $treadleNum = (int)$key;
            if ($treadleNum > 0) {
                $shafts = array_filter(
                    array_map('intval', array_map('trim', explode(',', $value))),
                    fn($s) => $s > 0
                );
                $tieupData[$treadleNum] = $shafts;
            }
        }

        return $tieupData;
    }

    private function extractWarp(): array
    {
        $warp = $this->sections['WARP'] ?? [];
        $warpColors = $this->sections['WARP COLORS'] ?? [];

        return [
            'threads' => (int)($warp['Threads'] ?? 0),
            'defaultColor' => (int)($warp['Color'] ?? 1),
            'colors' => $this->extractThreadColors($warpColors)
        ];
    }

    private function extractWeft(): array
    {
        $weft = $this->sections['WEFT'] ?? [];
        $weftColors = $this->sections['WEFT COLORS'] ?? [];

        return [
            'threads' => (int)($weft['Threads'] ?? 0),
            'defaultColor' => (int)($weft['Color'] ?? 1),
            'colors' => $this->extractThreadColors($weftColors)
        ];
    }

    private function extractThreadColors(array $colorSection): array
    {
        $colors = [];

        foreach ($colorSection as $key => $value) {
            $threadNum = (int)$key;
            if ($threadNum > 0) {
                $colors[$threadNum] = (int)$value;
            }
        }

        return $colors;
    }

    private function generatePattern(array $data): ?array
    {
        $threading = $data['threading'];
        $treadling = $data['treadling'];
        $tieup = $data['tieup'];
        $warp = $data['warp'];
        $weft = $data['weft'];

        if (empty($threading) || empty($treadling) || empty($tieup)) {
            return null;
        }

        $pattern = [];
        $maxPick = !empty($treadling) ? max(array_keys($treadling)) : 0;
        $maxThread = !empty($threading) ? max(array_keys($threading)) : 0;

        // Generate pattern grid
        for ($pick = 1; $pick <= $maxPick; $pick++) {
            $row = [];
            $treadle = $treadling[$pick] ?? null;
            $activeShafts = $tieup[$treadle] ?? [];

            for ($thread = 1; $thread <= $maxThread; $thread++) {
                $shaft = $threading[$thread] ?? null;
                $isUp = in_array($shaft, $activeShafts);

                // Determine color - use specific color assignments if available, otherwise use defaults
                $warpColor = $warp['colors'][$thread] ?? $warp['defaultColor'] ?? 1;
                $weftColor = $weft['colors'][$pick] ?? $weft['defaultColor'] ?? 1;

                $row[] = [
                    'isUp' => $isUp,
                    'warpColor' => $warpColor,
                    'weftColor' => $weftColor,
                    'displayColor' => $isUp ? $warpColor : $weftColor
                ];
            }
            $pattern[] = $row;
        }

        return $pattern;
    }

    private function parseBoolean(string $value, bool $defaultValue = false): bool
    {
        if (empty($value)) {
            return $defaultValue;
        }

        $lowerValue = strtolower($value);
        return in_array($lowerValue, ['true', 'yes', 'on', '1']);
    }
} 