use crossterm::{
    cursor,
    event::{self, Event, KeyCode},
    execute,
    style::{Color, Print, SetForegroundColor},
    terminal::{self, EnterAlternateScreen, LeaveAlternateScreen},
};
use rand::Rng;
use std::io::{self, stdout, Write};
use std::time::{Duration, Instant};

#[derive(Clone, Copy)]
enum Reality {
    MatrixGreen,
    DeepBlue,
}

struct Column {
    x: u16,
    y: i16,
    speed_counter: u16,
    speed_threshold: u16,
    chars: Vec<char>,
    len: usize,
    base_hue: u8,
}

impl Column {
    fn new(x: u16, height: u16) -> Self {
        let mut rng = rand::thread_rng();
        let len = rng.gen_range(10..25);
        Self {
            x,
            y: rng.gen_range(-(height as i16)..0),
            speed_counter: 0,
            speed_threshold: rng.gen_range(2..5),
            len,
            chars: (0..len).map(|_| Self::random_char()).collect(),
            base_hue: rng.gen_range(0..100), // Used to vary the shade
        }
    }

    fn random_char() -> char {
        let mut rng = rand::thread_rng();
        let chars = ['0','1','2','3','4','5','6','7','8','9','A','B','C','D','V','X','Z','$', '+', '-', '*', '=', '<', '>', ':'];
        chars[rng.gen_range(0..chars.len())]
    }

    fn get_color(&self, index: usize, reality: Reality) -> Color {
        if index == 0 { return Color::White; }
        let intensity = (255 - (index * 255 / self.len)) as u8;

        match reality {
            Reality::MatrixGreen => Color::Rgb {
                r: (intensity / 5),
                g: intensity,
                b: (intensity / 5)
            },
            Reality::DeepBlue => Color::Rgb {
                r: (intensity / 10),
                g: (intensity / 2),
                b: intensity
            },
        }
    }

    fn update(&mut self, max_y: u16) -> Option<u16> {
        let mut old_tail = None;
        self.speed_counter += 1;
        if self.speed_counter >= self.speed_threshold {
            old_tail = Some((self.y - (self.len as i16 - 1)) as u16);
            self.y += 1;
            self.speed_counter = 0;
            if rand::thread_rng().gen_bool(0.05) {
                let idx = rand::thread_rng().gen_range(0..self.len);
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
    let mut columns: Vec<Column> = (0..width).step_by(3).map(|x| Column::new(x, height)).collect();
    let mut current_reality = Reality::MatrixGreen;
    let mut tick_rate = Duration::from_millis(16);

    loop {
        if event::poll(Duration::from_millis(0))? {
            if let Event::Key(key) = event::read()? {
                match key.code {
                    KeyCode::Char('q') | KeyCode::Esc => break,
                    KeyCode::Char('r') => current_reality = Reality::MatrixGreen,
                    KeyCode::Char('b') => current_reality = Reality::DeepBlue,
                    KeyCode::Up | KeyCode::Char('k') => tick_rate = tick_rate.saturating_sub(Duration::from_millis(2)),
                    KeyCode::Down | KeyCode::Char('j') => tick_rate += Duration::from_millis(2),
                    _ => {}
                }
            }
        }

        for col in &mut columns {
            if let Some(old_y) = col.update(height) {
                if old_y < height {
                    execute!(stdout, cursor::MoveTo(col.x, old_y), Print(" "))?;
                }
            }

            for i in 0..col.len {
                let char_y = col.y - i as i16;
                if char_y >= 0 && char_y < height as i16 {
                    execute!(
                        stdout,
                        cursor::MoveTo(col.x, char_y as u16),
                        SetForegroundColor(col.get_color(i, current_reality)),
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
