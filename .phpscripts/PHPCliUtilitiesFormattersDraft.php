<?php
declare(strict_types=1);

/**
 * --------------------------------------------------------------------------
 * TUI TOOLKIT - PHP 8.5
 * --------------------------------------------------------------------------
 * A strict-typed, PHPStan-compatible suite for terminal formatting.
 */

/**
 * Class CliColorizer
 * Provides 24-bit TrueColor ANSI styling for terminal text.
 */
class CliColorizer
{
    private const string ESC = "\033[";
    private const string RESET = "\033[0m";

    /**
     * Applies 24-bit RGB colors to a string.
     *
     * @param string $text The text to be colored.
     * @param array{0: int, 1: int, 2: int} $bg Background RGB values [0-255].
     * @param array{0: int, 1: int, 2: int} $fg Foreground RGB values [0-255].
     * @return string ANSI escaped colored string.
     */
    public static function applyRgbColor(string $text, array $bg, array $fg = [255, 255, 255]): string
    {
        $bgSequence = self::ESC . "48;2;" . implode(';', $bg) . "m";
        $fgSequence = self::ESC . "38;2;" . implode(';', $fg) . "m";
        return "{$bgSequence}{$fgSequence} {$text} " . self::RESET;
    }

    /**
     * Helper to convert Hex strings to RGB and apply colors.
     *
     * @param string $text The text to be colored.
     * @param string $hexBg Hexadecimal background color (e.g., #FF0000).
     * @param string $hexFg Hexadecimal foreground color (e.g., #FFFFFF).
     * @return string ANSI escaped colored string.
     */
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

/**
 * Defines a table header.
 */
readonly class CliTableHeader
{
    /**
     * @param string $title The text to display in the header cell.
     */
    public function __construct(public string $title) {}
}

/**
 * Defines a table row containing multiple cells.
 */
readonly class CliTableRow
{
    /**
     * @param array<int, string|int|float> $cells The data for each column in this row.
     */
    public function __construct(public array $cells) {}
}

/**
 * Defines a node in a tree hierarchy.
 */
readonly class TreeNode
{
    /**
     * @param string $label The display name of the node.
     * @param array<int, TreeNode> $children Nested child nodes.
     */
    public function __construct(public string $label, public array $children = []) {}
}

/**
 * Defines a column within a grid layout row.
 */
readonly class GridColumn
{
    /**
     * @param string $content The text or formatted content for this column.
     * @param int $span The proportional width this column should occupy.
     */
    public function __construct(public string $content, public int $span = 1) {}
}

/**
 * TRAIT: Shared Measurement Logic
 */
trait TerminalMeasurementTrait {
    /**
     * Calculates the visual length of a string, ignoring ANSI escape sequences.
     *
     * @param string|int|float $value The content to measure.
     * @return int The length of the string as seen by a human user.
     */
    private function getVisibleLength(string|int|float $value): int {
        $text = "{$value}";
        $cleanText = preg_replace('/\033\[[0-9;]*m/', '', $text) ?? $text;
        return mb_strlen($cleanText);
    }
}

/**
 * FORMATTERS
 */

/**
 * Wraps text content in a decorative ASCII/Unicode box.
 */
class CliPanelFormatter {
    use TerminalMeasurementTrait;

    /**
     * Formats text inside a bordered panel.
     *
     * @param string $content The body text (can be multi-line).
     * @param string $title An optional label for the top border.
     * @return string The boxed content.
     */
    public function format(string $content, string $title = ""): string {
        $lines = explode(PHP_EOL, $content);
        $width = 0;
        foreach ($lines as $line) {
            $width = max($width, $this->getVisibleLength($line));
        }
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

/**
 * Manages side-by-side column layouts.
 */
class CliGridFormatter {
    use TerminalMeasurementTrait;

    /**
     * Renders a single row of columns.
     *
     * @param array<int, GridColumn> $columns The columns to render.
     * @param int $totalWidth The total character width available.
     * @return string The formatted row.
     */
    public function renderRow(array $columns, int $totalWidth = 80): string {
        $colCount = count($columns);
        $widthPerUnit = (int) floor($totalWidth / $colCount);
        $row = "";

        foreach ($columns as $col) {
            $colWidth = $widthPerUnit * $col->span;
            $visible = $this->getVisibleLength($col->content);
            $padding = max(0, $colWidth - $visible);
            $row .= $col->content . str_repeat(" ", $padding);
        }
        return $row;
    }
}

/**
 * Renders hierarchical data as a tree.
 */
class CliTreeFormatter {
    /**
     * Recursively renders a tree structure.
     *
     * @param TreeNode $node The root or current node.
     * @param string $prefix Internal use for indentation lines.
     * @return string The formatted tree.
     */
    public function render(TreeNode $node, string $prefix = ""): string {
        $output = "{$prefix}└── {$node->label}" . PHP_EOL;
        foreach ($node->children as $child) {
            $output .= $this->render($child, $prefix . "    ");
        }
        return $output;
    }
}

/**
 * Renders data into a bordered table grid.
 */
class CliTableFormatter {
    use TerminalMeasurementTrait;

    /**
     * Generates a full table string.
     *
     * @param array<int, CliTableHeader> $headers The column headers.
     * @param array<int, CliTableRow> $rows The data rows.
     * @return string The rendered table.
     */
    public function generateTable(array $headers, array $rows): string {
        $widths = $this->calculateWidths($headers, $rows);
        $sep = $this->line($widths);

        $output = $sep;
        $output .= $this->row(array_map(fn(CliTableHeader $h): string => $h->title, $headers), $widths);
        $output .= $sep;

        foreach ($rows as $row) {
            $output .= $this->row($row->cells, $widths);
        }
        return $output . $sep;
    }

    /**
     * @param array<int, CliTableHeader> $headers
     * @param array<int, CliTableRow> $rows
     * @return array<int, int>
     */
    private function calculateWidths(array $headers, array $rows): array {
        $widths = array_map(fn(CliTableHeader $h): int => $this->getVisibleLength($h->title), $headers);
        foreach ($rows as $row) {
            foreach ($row->cells as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, $this->getVisibleLength($cell));
            }
        }
        return $widths;
    }

    /**
     * @param array<int, int> $widths
     */
    private function line(array $widths): string {
        return "+" . implode("+", array_map(fn(int $w): string => str_repeat("-", $w + 2), $widths)) . "+" . PHP_EOL;
    }

    /**
     * @param array<int, string|int|float> $data
     * @param array<int, int> $widths
     */
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

/**
 * Orchestrates multiple components to build a complete TUI frame.
 */
class TuiComponentManager
{
    /** @var array<int, string> The vertical stack of rendered components. */
    private array $buffer = [];

    /**
     * @param CliPanelFormatter $panel
     * @param CliTableFormatter $table
     * @param CliGridFormatter $grid
     * @param CliTreeFormatter $tree
     */
    public function __construct(
        private readonly CliPanelFormatter $panel,
        private readonly CliTableFormatter $table,
        private readonly CliGridFormatter $grid,
        private readonly CliTreeFormatter $tree
    ) {}

    /**
     * Adds a rendered component string to the display buffer.
     *
     * @param string $renderedComponent
     */
    public function addComponent(string $renderedComponent): void {
        $this->buffer[] = $renderedComponent;
    }

    /**
     * Clears the terminal screen and resets the cursor.
     */
    public function clearScreen(): void {
        echo "\033[2J\033[H";
    }

    /**
     * Outputs all buffered components and clears the buffer.
     */
    public function render(): void {
        echo implode(PHP_EOL, $this->buffer) . PHP_EOL;
        $this->buffer = [];
    }

    /**
     * Helper to add a grid row to the buffer.
     *
     * @param GridColumn ...$columns
     */
    public function addColumns(GridColumn ...$columns): void {
        $this->buffer[] = $this->grid->renderRow($columns);
    }
}

/**
 * --------------------------------------------------------------------------
 * SAMPLE DASHBOARD EXECUTION
 * --------------------------------------------------------------------------
 */

$panel     = new CliPanelFormatter();
$table     = new CliTableFormatter();
$grid      = new CliGridFormatter();
$tree      = new CliTreeFormatter();

$tui = new TuiComponentManager($panel, $table, $grid, $tree);

$tui->clearScreen();

// 1. Header
$tui->addComponent($panel->format(
    CliColorizer::applyHexColor("Formatter Examples", "#222222", "#00FF00"),
    "v0.0.0-dev"
));

// 2. Grid Stats
$tui->addColumns(
    new GridColumn(CliColorizer::applyHexColor(" Grid Column 1 ", "#111111")),
    new GridColumn(CliColorizer::applyHexColor(" Grid Column 2 ", "#111111")),
    new GridColumn(CliColorizer::applyHexColor(" Grid Column 3 ", "#111111"))
);

$tui->addComponent(""); // new line

// 3. Process Table
$headers = [new CliTableHeader("Header 1"), new CliTableHeader("Header 2"), new CliTableHeader("Header 3")];
$rows = [
    new CliTableRow([1, "Row 1 Value", CliColorizer::applyHexColor("Row 1 Value", "#0000FF")]),
    new CliTableRow([2, "Row 2 Value", CliColorizer::applyHexColor("Row 2 Value", "#0000FF")]),
    new CliTableRow([3, "Row 3 Value", CliColorizer::applyHexColor("Row 3 Value", "#0000FF")]),
];
$tui->addComponent($table->generateTable($headers, $rows));

// 4. Tree Structure
$rootNode = new TreeNode("Tree Node Root 1", [
    new TreeNode("Sub Node 1", [
        new TreeNode("Sub Sub Node 1"),
        new TreeNode("Sub Sub Node 2")
    ]),
    new TreeNode("Tree Node Root 2")
]);
$tui->addComponent("Tree Node:");
$tui->addComponent($tree->render($rootNode));

$tui->render();
