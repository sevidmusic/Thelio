use crossterm::{
    cursor,
    event::{self, Event, KeyCode},
    execute,
    style::{Color, Print, SetForegroundColor},
    terminal::{self, EnterAlternateScreen, LeaveAlternateScreen},
};
use rand::Rng;
use std::io::{self, stdout, Write};
use std::time::Duration;

/// Represents a single vertical stream of characters in the matrix rain.
/// Each column manages its own position, speed, and character set.
struct Column {
    /// Horizontal position (column index) on the terminal.
    x: u16,
    /// Current vertical position of the "head" of the stream.
    y: i16,
    /// Counter to track when the column should move based on speed_threshold.
    speed_counter: u16,
    /// How many ticks must pass before the column moves down by one row.
    speed_threshold: u16,
    /// The specific characters currently visible in this column's stream.
    chars: Vec<char>,
    /// Pre-calculated color gradient for each character in the stream.
    colors: Vec<Color>,
    /// Total number of characters trailing behind the head.
    len: usize,
}

impl Column {
    /// Creates a new column at a specific x-coordinate.
    ///
    /// # Arguments
    /// * `x` - The horizontal position.
    /// * `height` - Used to randomize the initial starting height above the screen.
    fn new(x: u16, height: u16) -> Self {
        let mut rng = rand::thread_rng();
        let len = rng.gen_range(10..25);

        // A unique hue allows each column to have a slightly different color profile
        let base_hue = rng.gen_range(0..255);

        Self {
            x,
            y: rng.gen_range(-(height as i16)..0),
            speed_counter: 0,
            speed_threshold: rng.gen_range(2..5),
            len,
            chars: (0..len).map(|_| Self::random_char()).collect(),
            colors: (0..len).map(|i| Self::gradient_color(i, len, base_hue)).collect(),
        }
    }

    /// Returns a random character typically used in the Matrix digital rain.
    fn random_char() -> char {
        let mut rng = rand::thread_rng();
        let chars = ['0','1','2','3','4','5','6','7','8','9','A','B','C','D','V','X','Z','$', '+', '-', '*', '=', '<', '>', ':'];
        chars[rng.gen_range(0..chars.len())]
    }

    /// Calculates a color based on the character's position in the stream.
    ///
    /// The "head" (index 0) is always white. Subsequent characters fade
    /// in intensity based on their distance from the head.
    fn gradient_color(index: usize, total: usize, hue: u8) -> Color {
        if index == 0 { return Color::White; }
        let intensity = (255 - (index * 255 / total)) as u8;

        Color::Rgb {
            r: (intensity as u16 * hue as u16 / 255) as u8,
            g: intensity,
            b: (intensity as u16 * (255 - hue) as u16 / 255) as u8
        }
    }

    /// Updates the column state.
    ///
    /// If the speed threshold is met, the column moves down.
    /// Returns `Some(u16)` containing the y-coordinate of the old tail
    /// that needs to be erased from the terminal.
    fn update(&mut self, max_y: u16) -> Option<u16> {
        let mut old_tail = None;
        self.speed_counter += 1;

        if self.speed_counter >= self.speed_threshold {
            // Mark the very last character of the trail for erasure
            old_tail = Some((self.y - (self.len as i16 - 1)) as u16);

            self.y += 1;
            self.speed_counter = 0;

            // Mutation logic: occasionally swap a character to create the flickering effect
            let mut rng = rand::thread_rng();
            if rng.gen_bool(0.05) {
                let idx = rng.gen_range(0..self.len);
                self.chars[idx] = Self::random_char();
            }
        }

        // Reset the column to the top if it has completely cleared the bottom of the screen
        if self.y > (max_y + self.len as u16) as i16 {
            self.y = 0;
        }
        old_tail
    }
}

fn main() -> io::Result<()> {
    let mut stdout = stdout();

    // Initialize the terminal into "Raw Mode" (captures keys immediately)
    // and switch to the Alternate Screen to preserve user scrollback.
    terminal::enable_raw_mode()?;
    execute!(stdout, EnterAlternateScreen, cursor::Hide, terminal::Clear(terminal::ClearType::All))?;

    let (mut width, mut height) = terminal::size()?;

    // Create columns spaced by 3 units to prevent a solid wall of text
    let mut columns: Vec<Column> = (0..width).step_by(3).map(|x| Column::new(x, height)).collect();

    // Initial animation speed (16ms is roughly 60 updates per second)
    let mut tick_rate = Duration::from_millis(16);

    loop {
        // Event Handling: Process all events (Keys, Resizing) currently in the buffer
        while event::poll(Duration::from_millis(0))? {
            match event::read()? {
                // Handle Keyboard Input
                Event::Key(key) => match key.code {
                    KeyCode::Char('q') | KeyCode::Esc => {
                        // Cleanup: Show cursor and return to main terminal screen
                        execute!(stdout, cursor::Show, LeaveAlternateScreen)?;
                        terminal::disable_raw_mode()?;
                        return Ok(());
                    }
                    KeyCode::Up | KeyCode::Char('k') => {
                        tick_rate = tick_rate.saturating_sub(Duration::from_millis(2));
                    }
                    KeyCode::Down | KeyCode::Char('j') => {
                        tick_rate += Duration::from_millis(2);
                    }
                    _ => {}
                },
                // Handle Terminal Window Resize
                Event::Resize(new_width, new_height) => {
                    width = new_width;
                    height = new_height;
                    // Re-calculate column layout to match new dimensions
                    columns = (0..width).step_by(3).map(|x| Column::new(x, height)).collect();
                    execute!(stdout, terminal::Clear(terminal::ClearType::All))?;
                }
                _ => {}
            }
        }

        // Logic & Rendering Loop
        for col in &mut columns {
            // Step 1: Clean up the previous frame's tail to avoid "streaking"
            if let Some(old_y) = col.update(height) {
                if old_y < height {
                    execute!(stdout, cursor::MoveTo(col.x, old_y), Print(" "))?;
                }
            }

            // Step 2: Draw each character in the column's current stream
            for i in 0..col.len {
                let char_y = col.y - i as i16;
                // Only draw characters that are currently visible on the screen
                if char_y >= 0 && char_y < height as i16 {
                    execute!(
                        stdout,
                        cursor::MoveTo(col.x, char_y as u16),
                        SetForegroundColor(col.colors[i]),
                        Print(col.chars[i])
                    )?;
                }
            }
        }

        // Flush all drawing commands to the terminal at once for smooth animation
        stdout.flush()?;
        std::thread::sleep(tick_rate);
    }
}
