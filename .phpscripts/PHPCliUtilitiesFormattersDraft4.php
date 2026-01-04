<?php
declare(strict_types=1);

/**
 * --------------------------------------------------------------------------
 * TUI TOOLKIT - PHP 8.5 (Interactive & Real-time)
 * --------------------------------------------------------------------------
 */

/**
 * Handles low-level ANSI cursor movements.
 */
class CliCursor
{
    private const string ESC = "\033[";

    public static function moveTo(int $x, int $y): void { echo self::ESC . "{$y};{$x}H"; }
    public static function hide(): void { echo self::ESC . "?25l"; }
    public static function show(): void { echo self::ESC . "?25h"; }
    public static function clearLine(): void { echo self::ESC . "K"; }
}

/**
 * Manages terminal input modes and key detection.
 */
class CliInput
{
    /**
     * Sets the terminal to 'raw' mode to intercept keys instantly.
     */
    public static function setRawMode(): void
    {
        shell_exec('stty -icanon -echo');
    }

    /**
     * Resets terminal to standard 'cooked' mode.
     */
    public static function resetMode(): void
    {
        shell_exec('stty icanon echo');
    }

    /**
     * Non-blocking check for a single keypress.
     * @return string|null The character pressed, or null if none.
     */
    public static function getKeyPress(): ?string
    {
        $read = [STDIN];
        $write = null;
        $except = null;

        // Wait 0 seconds (non-blocking)
        if (stream_select($read, $write, $except, 0) > 0) {
            return fread(STDIN, 1);
        }

        return null;
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

    public static function applyRgbColor(string $text, array $bg, array $fg = [255, 255, 255]): string
    {
        $bgSequence = self::ESC . "48;2;" . implode(';', $bg) . "m";
        $fgSequence = self::ESC . "38;2;" . implode(';', $fg) . "m";
        return "{$bgSequence}{$fgSequence} {$text} " . self::RESET;
    }

    public static function applyHexColor(string $text, string $hexBg, string $hexFg = "#FFFFFF"): string
    {
        $parse = function(string $hex): array {
            $cleaned = ltrim($hex, '#');
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

/**
 * COMPONENT MANAGER
 */
class TuiComponentManager
{
    private array $buffer = [];

    public function __construct(
        public readonly CliPanelFormatter $panel,
        public readonly CliGridFormatter $grid
    ) {}

    public function addComponent(string|Renderable $component): void {
        $this->buffer[] = ($component instanceof Renderable) ? $component->render() : $component;
    }

    public function clearScreen(): void { echo "\033[2J\033[H"; }

    public function renderFixed(int $x = 1, int $y = 1): void {
        CliCursor::moveTo($x, $y);
        echo implode(PHP_EOL, $this->buffer) . PHP_EOL;
        $this->buffer = [];
    }
}

/**
 * --------------------------------------------------------------------------
 * INTERACTIVE EXECUTION
 * --------------------------------------------------------------------------
 */

$tui = new TuiComponentManager(new CliPanelFormatter(), new CliGridFormatter());

// Setup Terminal
$tui->clearScreen();
CliCursor::hide();
CliInput::setRawMode();

$running = true;
$counter = 0;
$lastKey = '';

try {
    while ($running) {
        // 1. Process Input
        $key = CliInput::getKeyPress();
        if ($key !== null) {
            $lastKey = $key;
            if ($key === 'q') $running = false;
        }

        // 2. Build Interface
        $tui->addComponent($tui->panel->format(
            CliColorizer::applyHexColor(" INTERACTIVE TUI ", "#1A1A1A", "#00FF00"),
            "Press 'q' to quit"
        ));

        $tui->addComponent("Current Counter: " . $counter++);
        $tui->addComponent("Last Key Pressed: " . $lastKey);

        $tui->addComponent("");
        $tui->addComponent(CliColorizer::applyHexColor(" [Q] Quit ", "#330000", "#FF0000"));

        // 3. Render
        $tui->renderFixed();

        // 4. Sleep to prevent 100% CPU usage
        usleep(50000);
    }
} finally {
    // Always reset the terminal!
    CliInput::resetMode();
    CliCursor::show();
    echo "TUI Closed Cleanly." . PHP_EOL;
}
