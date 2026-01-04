<?php

declare(strict_types=1);

/**
 * --------------------------------------------------------------------------
 * TUI TOOLKIT - PHP 8.5 (Interactive & Flicker-Free)
 * --------------------------------------------------------------------------
 */

/**
 * Handles low-level ANSI cursor movements.
 */
class CliCursor
{
    private const string ESC = "\033[";

    /**
     * Moves the cursor to a specific position.
     * 1,1 is the top-left corner.
     */
    public static function moveTo(int $x, int $y): void
    {
        echo self::ESC . "{$y};{$x}H";
    }

    /**
     * Hides the cursor (useful during rendering).
     */
    public static function hide(): void
    {
        echo self::ESC . "?25l";
    }

    /**
     * Shows the cursor.
     */
    public static function show(): void
    {
        echo self::ESC . "?25h";
    }

    /**
     * Clears from the cursor to the end of the line.
     */
    public static function clearLine(): void
    {
        echo self::ESC . "K";
    }
}

/**
 * Interface for any component that can be rendered to a string.
 */
interface Renderable {
    public function render(): string;
}

/**
 * Provides 24-bit TrueColor ANSI styling for terminal text.
 */
class CliColorizer
{
    private const string ESC = "\033[";
    private const string RESET = "\033[0m";

    /**
     * @param array{0: int, 1: int, 2: int} $bg
     * @param array{0: int, 1: int, 2: int} $fg
     */
    public static function applyRgbColor(string $text, array $bg, array $fg = [255, 255, 255]): string
    {
        $bgSequence = self::ESC . "48;2;" . implode(';', $bg) . "m";
        $fgSequence = self::ESC . "38;2;" . implode(';', $fg) . "m";
        return "{$bgSequence}{$fgSequence} {$text} " . self::RESET;
    }

    public static function applyHexColor(string $text, string $hexBg, string $hexFg = "#FFFFFF"): string
    {
        /** @var callable(string): array{0: int, 1: int, 2: int} $parse */
        $parse = function(string $hex): array {
            $cleaned = ltrim($hex, '#');
            /** @var array{0: int, 1: int, 2: int} */
            return array_map('hexdec', str_split($cleaned, 2));
        };
        return self::applyRgbColor($text, $parse($hexBg), $parse($hexFg));
    }
}

/**
 * VALUE OBJECTS
 */
readonly class CliTableHeader { public function __construct(public string $title) {} }
readonly class CliTableRow { public function __construct(public array $cells) {} }
readonly class TreeNode { public function __construct(public string $label, public array $children = []) {} }
readonly class GridColumn { public function __construct(public string $content, public int $span = 1) {} }

/**
 * TRAIT: Shared Measurement Logic
 */
trait TerminalMeasurementTrait {
    private function getVisibleLength(string|int|float $value): int {
        $text = "{$value}";
        $cleanText = preg_replace('/\033\[[0-9;]*m/', '', $text) ?? $text;
        return mb_strlen($cleanText);
    }

    private function getTerminalWidth(): int {
        return (int) (shell_exec('tput cols') ?: 80);
    }
}

/**
 * FORMATTERS
 */
class CliPanelFormatter {
    use TerminalMeasurementTrait;
    public function format(string $content, string $title = ""): string {
        $lines = explode(PHP_EOL, $content);
        $width = 0;
        foreach ($lines as $line) { $width = max($width, $this->getVisibleLength($line)); }
        $width = max($width, $this->getVisibleLength($title));
        $top = "┏━ " . ($title !== "" ? "{$title} " : "") . str_repeat("━", max(0, $width - $this->getVisibleLength($title))) . "┓";
        $bottom = "┗" . str_repeat("━", $width + 2) . "┛";
        $output = $top . PHP_EOL;
        foreach ($lines as $line) {
            $padding = str_repeat(" ", max(0, $width - $this->getVisibleLength($line)));
            $output .= "┃ {$line}{$padding} ┃" . PHP_EOL;
        }
        return $output . $bottom;
    }
}

class CliGridFormatter {
    use TerminalMeasurementTrait;
    public function renderRow(array $columns, ?int $totalWidth = null): string {
        $totalWidth ??= $this->getTerminalWidth();
        $totalSpans = array_reduce($columns, fn($carry, $col) => $carry + $col->span, 0);
        $widthPerSpan = (int) floor($totalWidth / $totalSpans);
        $row = "";
        foreach ($columns as $col) {
            $colWidth = $widthPerSpan * $col->span;
            $visible = $this->getVisibleLength($col->content);
            $padding = max(0, $colWidth - $visible);
            $row .= $col->content . str_repeat(" ", $padding);
        }
        return $row;
    }
}

class CliTreeFormatter {
    public function render(TreeNode $node, string $prefix = ""): string {
        $output = "{$prefix}└── {$node->label}" . PHP_EOL;
        foreach ($node->children as $child) { $output .= $this->render($child, $prefix . "    "); }
        return $output;
    }
}

class CliTableFormatter {
    use TerminalMeasurementTrait;
    public function generateTable(array $headers, array $rows): string {
        $widths = $this->calculateWidths($headers, $rows);
        $sep = $this->line($widths);
        $output = $sep;
        $output .= $this->row(array_map(fn(CliTableHeader $h): string => $h->title, $headers), $widths);
        $output .= $sep;
        foreach ($rows as $row) { $output .= $this->row($row->cells, $widths); }
        return $output . $sep;
    }
    private function calculateWidths(array $headers, array $rows): array {
        $widths = array_map(fn(CliTableHeader $h): int => $this->getVisibleLength($h->title), $headers);
        foreach ($rows as $row) {
            foreach ($row->cells as $i => $cell) { $widths[$i] = max($widths[$i] ?? 0, $this->getVisibleLength($cell)); }
        }
        return $widths;
    }
    private function line(array $widths): string {
        return "+" . implode("+", array_map(fn(int $w): string => str_repeat("-", $w + 2), $widths)) . "+" . PHP_EOL;
    }
    private function row(array $data, array $widths): string {
        $cells = [];
        foreach ($data as $i => $val) {
            $pad = str_repeat(" ", max(0, $widths[$i] - $this->getVisibleLength($val)));
            $cells[] = " {$val}{$pad} ";
        }
        return "|" . implode("|", $cells) . "|" . PHP_EOL;
    }
}

/**
 * COMPONENT MANAGER
 */
class TuiComponentManager
{
    private array $buffer = [];

    public function __construct(
        public readonly CliPanelFormatter $panel,
        public readonly CliTableFormatter $table,
        public readonly CliGridFormatter $grid,
        public readonly CliTreeFormatter $tree
    ) {}

    public function addComponent(string|Renderable $component): void {
        $this->buffer[] = ($component instanceof Renderable) ? $component->render() : $component;
    }

    public function addHorizontalRule(string $char = "─"): void {
        $width = (int) (shell_exec('tput cols') ?: 80);
        $this->buffer[] = str_repeat($char, $width);
    }

    public function clearScreen(): void {
        echo "\033[2J\033[H";
    }

    /**
     * Renders at the current cursor position.
     */
    public function render(): void {
        echo implode(PHP_EOL, $this->buffer) . PHP_EOL;
        $this->buffer = [];
    }

    /**
     * Renders at a fixed position to prevent flicker.
     */
    public function renderFixed(int $x = 1, int $y = 1): void {
        CliCursor::moveTo($x, $y);
        $this->render();
    }

    public function addColumns(GridColumn ...$columns): void {
        $this->buffer[] = $this->grid->renderRow($columns);
    }
}

/**
 * --------------------------------------------------------------------------
 * INTERACTIVE SAMPLE
 * --------------------------------------------------------------------------
 */

$tui = new TuiComponentManager(new CliPanelFormatter(), new CliTableFormatter(), new CliGridFormatter(), new CliTreeFormatter());

$tui->clearScreen();
CliCursor::hide();

// Simulate a live monitor
for ($i = 0; $i <= 10; $i++) {

    $tui->addComponent($tui->panel->format(
        CliColorizer::applyHexColor(" LIVE SYSTEM MONITOR ", "#1A1A1A", "#00FF00"),
        "Iteration: {$i}"
    ));

    $cpuColor = $i > 7 ? "#550000" : "#003300";
    $tui->addColumns(
        new GridColumn(CliColorizer::applyHexColor(" CPU: " . ($i * 9) . "% ", $cpuColor)),
        new GridColumn(CliColorizer::applyHexColor(" MEM: " . (2.0 + $i/10) . "G ", "#111111")),
        new GridColumn(CliColorizer::applyHexColor(" TICK: " . time() . " ", "#111111"))
    );

    $tui->addHorizontalRule("·");

    $tui->addComponent("Updating without screen flicker...");

    // Instead of clearScreen(), we use renderFixed()
    $tui->renderFixed(1, 1);

    usleep(300000); // 300ms delay
}

CliCursor::show();
