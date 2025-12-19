use crossterm::{
    cursor,
    event::{self, Event, KeyCode},
    execute,
    style::{self, Color, Print, SetForegroundColor},
    terminal::{self, EnterAlternateScreen, LeaveAlternateScreen},
};
use rand::Rng;
use std::io::{self, stdout, Write};
use std::time::{Duration, Instant};

struct Column {
    x: u16,
    y: i16,
    speed_counter: u16,
    speed_threshold: u16,
    chars: Vec<char>,
    colors: Vec<Color>,
    len: usize,
}

impl Column {
    fn new(x: u16, height: u16) -> Self {
        let mut rng = rand::thread_rng();
        let len = rng.gen_range(10..25);

        // Generate a random base color for this specific column
        let base_hue = rng.gen_range(0..255);

        Self {
            x,
            y: rng.gen_range(-(height as i16)..0),
            speed_counter: 0,
            speed_threshold: rng.gen_range(2..5),
            len,
            chars: (0..len).map(|_| Self::random_char()).collect(),
            // Pre-calculate a gradient for this column
            colors: (0..len).map(|i| Self::gradient_color(i, len, base_hue)).collect(),
        }
    }

    fn random_char() -> char {
        let mut rng = rand::thread_rng();
        // Standard Matrix-style range (half-width katakana and symbols)
        let chars = ['0','1','2','3','4','5','6','7','8','9','A','B','C','D','V','X','Z','$', '+', '-', '*', '=', '<', '>', ':'];
        chars[rng.gen_range(0..chars.len())]
    }

    fn gradient_color(index: usize, total: usize, hue: u8) -> Color {
        if index == 0 { return Color::White; } // Bright head
        let intensity = (255 - (index * 255 / total)) as u8;
        // Cycles colors based on the column's unique hue
        Color::Rgb {
            r: (intensity as u16 * hue as u16 / 255) as u8,
            g: intensity,
            b: (intensity as u16 * (255 - hue) as u16 / 255) as u8
        }
    }

    fn update(&mut self, max_y: u16) -> Option<u16> {
        let mut old_tail = None;
        self.speed_counter += 1;

        if self.speed_counter >= self.speed_threshold {
            // Before moving, mark where the very last character was to erase it
            old_tail = Some((self.y - (self.len as i16 - 1)) as u16);

            self.y += 1;
            self.speed_counter = 0;

            // Slower glitch: only 5% chance to change a character per move
            let mut rng = rand::thread_rng();
            if rng.gen_bool(0.05) {
                let idx = rng.gen_range(0..self.len);
                self.chars[idx] = Self::random_char();
            }
        }

        if self.y > (max_y + self.len as u16) as i16 {
            self.y = 0;
        }
        old_tail
    }
}

fn main() -> io::Result<()> {
    let mut stdout = stdout();
    terminal::enable_raw_mode()?;
    execute!(stdout, EnterAlternateScreen, cursor::Hide, terminal::Clear(terminal::ClearType::All))?;

    let (width, height) = terminal::size()?;
    // Space out columns so it's not a solid wall of text
    let mut columns: Vec<Column> = (0..width).step_by(3).map(|x| Column::new(x, height)).collect();

    let mut tick_rate = Duration::from_millis(16); // ~60 FPS update check

    loop {
        if event::poll(Duration::from_millis(0))? {
            if let Event::Key(key) = event::read()? {
                match key.code {
                    KeyCode::Char('q') | KeyCode::Esc => break,
                    KeyCode::Up | KeyCode::Char('k') => tick_rate = tick_rate.saturating_sub(Duration::from_millis(2)),
                    KeyCode::Down | KeyCode::Char('j') => tick_rate += Duration::from_millis(2),
                    _ => {}
                }
            }
        }

        for col in &mut columns {
            // 1. Erase the old tail if the column moved
            if let Some(old_y) = col.update(height) {
                if old_y < height {
                    execute!(stdout, cursor::MoveTo(col.x, old_y), Print(" "))?;
                }
            }

            // 2. Draw the stream
            for i in 0..col.len {
                let char_y = col.y - i as i16;
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
        stdout.flush()?;
        std::thread::sleep(tick_rate);
    }

    execute!(stdout, cursor::Show, LeaveAlternateScreen)?;
    terminal::disable_raw_mode()?;
    Ok(())
}
