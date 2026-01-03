<?php

declare(strict_types=1);

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

class ProgressBar {
    private int $total;
    private int $width;

    public function __construct(int $total, int $width = 40)
    {
        $this->total = $total;
        $this->width = $width;
    }

    /**
     * Updates the terminal display with a colored progress bar.
     * * @param int $current The current progress value.
     * @param string $label Optional text to display next to the bar.
     */
    public function update(int $current, string $label = ''): void
    {
        $percent = $current / $this->total;
        $filledWidth = (int)($percent * $this->width);
        $emptyWidth = $this->width - $filledWidth;

        // Create the bar components
        $filledPart = str_repeat(' ', $filledWidth);
        $emptyPart = str_repeat('░', $emptyWidth);

        // Colorize the filled part (Green background for success/progress)
        $progressBar = CliColorizer::applyHexColor($filledPart, '#000000', '#ffffff');

        // Build the full line: [████░░░] 50% Label
        $output = sprintf(
            "\r[%s%s] %3d%% %s",
            $progressBar,
            $emptyPart,
            $percent * 100,
            $label
        );

        echo $output;

        // Move to a new line if finished
        if ($current >= $this->total) {
            echo PHP_EOL;
        }
    }
    /**
     * Clears the entire terminal screen and moves cursor to top-left.
     */
    public function clearScreen(): void
    {
        // \033[2J clears the screen
        // \033[H moves the cursor to the home position (0,0)
        echo "\033[2J\033[H";
    }
}

// --- Implementation Example ---

$totalSteps = 50;
$progressBar = new ProgressBar($totalSteps);

for ($i = 1; $i <= $totalSteps; $i++) {
    usleep(100000);
    // Update the bar
    $progressBar->clearScreen();
    # Using RGB Arrays (Forest Green background, White text)
    echo CliColorizer::applyRgbColor("Nature Mode", [34, 139, 34], [255, 255, 255]) . PHP_EOL;
    # Using Hex Codes (Vibrant Orange background)
    echo CliColorizer::applyHexColor("Alert!", "#FF5733", "#000000") . PHP_EOL;
    $progressBar->update($i, "Processing item $i...");
}
