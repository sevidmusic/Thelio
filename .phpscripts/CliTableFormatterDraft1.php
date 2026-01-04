<?php

declare(strict_types=1);

namespace Darling\PHPCliUtilities;

class CliColorizer
{
    private const ESC = "\033[";
    private const RESET = "\033[0m";

    /**
     * Applies 24-bit RGB colors to a string.
     *
     * @param string $text The text to wrap.
     * @param array $bg RGB array for background [r, g, b].
     * @param array $fg RGB array for foreground [r, g, b].
     *
     * @return string
     */
    public static function applyRgbColor(string $text, array $bg, array $fg = [255, 255, 255]): string
    {
        // Background: ESC[48;2;R;G;Bm
        $bgSequence = self::ESC . "48;2;" . implode(';', $bg) . "m";

        // Foreground: ESC[38;2;R;G;Bm
        $fgSequence = self::ESC . "38;2;" . implode(';', $fg) . "m";

        return $bgSequence . $fgSequence . " " . $text . " " . self::RESET;
    }

    /**
     * Helper to convert Hex strings (e.g., #FF5733) to RGB for the rgb method.
     */
    public static function applyHexColor(string $text, string $hexBg, string $hexFg = "#FFFFFF"): string
    {
        $parse = function($hex) {
            return array_map('hexdec', str_split(ltrim($hex, '#'), 2));
        };

        return self::applyRgbColor($text, $parse($hexBg), $parse($hexFg));
    }
}

/**
 * Encapsulates a single table header.
 */
readonly class CliTableHeader
{
    public function __construct(
        public string $title
    ) {}
}

/**
 * Encapsulates a single table row.
 */
readonly class CliTableRow
{
    /**
     * @param array<int, string|int|float> $cells
     */
    public function __construct(
        public array $cells
    ) {}
}


/**
 * Class CliTableFormatter
 * * Formats terminal output using structured Value Objects for headers and rows.
 */
class CliTableFormatter
{
    private string $borderChar = "|";
    private string $horizontalChar = "-";
    private string $intersectionChar = "+";

    /**
     * @param array<int, CliTableHeader> $headers
     * @param array<int, CliTableRow> $rows
     */
    public function generateTable(array $headers, array $rows): string
    {
        $columnWidths = $this->calculateColumnWidths($headers, $rows);
        $output = "";

        $separator = $this->createSeparator($columnWidths);

        $output .= $separator;

        // Extract titles from CliTableHeaders for formatting
        $headerTitles = array_map(fn(CliTableHeader $h) => $h->title, $headers);
        $output .= $this->formatRow($headerTitles, $columnWidths);

        $output .= $separator;

        foreach ($rows as $row) {
            $output .= $this->formatRow($row->cells, $columnWidths);
        }

        $output .= $separator;

        return $output;
    }

    /**
     * @param array<int, CliTableHeader> $headers
     * @param array<int, CliTableRow> $rows
     * @return array<int, int>
     */
    private function calculateColumnWidths(array $headers, array $rows): array
    {
        $widths = [];
        foreach ($headers as $index => $header) {
            $widths[$index] = $this->getVisibleLength($header->title);
        }

        foreach ($rows as $row) {
            foreach ($row->cells as $index => $cell) {
                $cellLength = $this->getVisibleLength($cell);
                if ($cellLength > ($widths[$index] ?? 0)) {
                    $widths[$index] = $cellLength;
                }
            }
        }
        return $widths;
    }

    private function getVisibleLength(string|int|float $value): int
    {
        $text = "{$value}";
        $cleanText = preg_replace('/\033\[[0-9;]*m/', '', $text) ?? $text;
        return mb_strlen($cleanText);
    }

    /**
     * @param array<int, int> $widths
     */
    private function createSeparator(array $widths): string
    {
        $line = $this->intersectionChar;
        foreach ($widths as $width) {
            $line .= str_repeat($this->horizontalChar, $width + 2) . $this->intersectionChar;
        }
        return $line . PHP_EOL;
    }

    /**
     * @param array<int, string|int|float> $rowData
     * @param array<int, int> $widths
     */
    private function formatRow(array $rowData, array $widths): string
    {
        $line = $this->borderChar;
        foreach ($widths as $index => $width) {
            $value = $rowData[$index] ?? "";
            $textValue = "{$value}";

            $visibleLen = $this->getVisibleLength($value);
            $paddingNeeded = max(0, $width - $visibleLen);

            $line .= " " . $textValue . str_repeat(" ", $paddingNeeded) . " " . $this->borderChar;
        }
        return $line . PHP_EOL;
    }
}

// Define Headers
$headers = [
    new CliTableHeader("Id"),
    new CliTableHeader("Description"),
    new CliTableHeader("Status")
];

// Define Rows
$rows = [
    new CliTableRow([101, "Foo", CliColorizer::applyHexColor("Succeeded", "#00FF00")]),
    new CliTableRow([102, "Bar", CliColorizer::applyHexColor("Failed", "#FF0000")]),
];

$formatter = new CliTableFormatter();
echo $formatter->generateTable($headers, $rows);
