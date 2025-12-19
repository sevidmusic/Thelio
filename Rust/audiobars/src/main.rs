use cpal::traits::{DeviceTrait, HostTrait, StreamTrait};
use ratatui::{
    backend::CrosstermBackend,
    style::{Color, Style},
    widgets::{BarChart, Block, Borders},
    Terminal,
    layout::{Layout, Constraint, Direction},
};
use spectrum_analyzer::{samples_fft_to_spectrum, FrequencyLimit};
use std::sync::{Arc, Mutex};
use std::time::Duration;

struct ChannelData {
    left: Vec<f32>,
    right: Vec<f32>,
    responsiveness: f32,
    zen_mode: bool,
    height_limit: u16, // New field for dynamic height limit
}

fn main() -> Result<(), Box<dyn std::error::Error>> {
    let host = cpal::default_host();
    let device = host.default_input_device().expect("No input device found");
    let config = device.default_input_config()?;
    let sample_rate = config.sample_rate().0;

    let shared_state = Arc::new(Mutex::new(ChannelData {
        left: vec![0.0f32; 2048],
        right: vec![0.0f32; 2048],
        responsiveness: 0.12,
        zen_mode: false,
        height_limit: 87, // Default starting limit
    }));
    let state_clone = Arc::clone(&shared_state);

    let stream = device.build_input_stream(
        &config.into(),
        move |data: &[f32], _| {
            if let Ok(mut buffer) = state_clone.lock() {
                for (i, frame) in data.chunks(2).enumerate() {
                    if i >= 2048 { break; }
                    buffer.left[i] = frame[0];
                    if frame.len() > 1 { buffer.right[i] = frame[1]; }
                }
            }
        },
        |err| eprintln!("Stream error: {}", err),
        None,
    )?;
    stream.play()?;

    crossterm::terminal::enable_raw_mode()?;
    let mut stdout = std::io::stdout();
    crossterm::execute!(stdout, crossterm::terminal::EnterAlternateScreen, crossterm::cursor::Hide)?;
    let mut terminal = Terminal::new(CrosstermBackend::new(stdout))?;

    let mut l_heights = vec![0.0f32; 256];
    let mut r_heights = vec![0.0f32; 256];
    let mut l_velocities = vec![0.0f32; 256];
    let mut r_velocities = vec![0.0f32; 256];
    let mut l_max = 0.1f32;
    let mut r_max = 0.1f32;

    loop {
        let (l_samples, r_samples, current_resp, zen, limit) = {
            let buffer = shared_state.lock().unwrap();
            (buffer.left.clone(), buffer.right.clone(), buffer.responsiveness, buffer.zen_mode, buffer.height_limit)
        };

        let left_data = process_fft_physics(&l_samples, sample_rate, 64, &mut l_heights, &mut l_velocities, &mut l_max, current_resp);
        let right_data = process_fft_physics(&r_samples, sample_rate, 64, &mut r_heights, &mut r_velocities, &mut r_max, current_resp);

        terminal.draw(|f| {
            let main_chunks = Layout::default()
                .direction(Direction::Horizontal)
                .constraints([Constraint::Percentage(50), Constraint::Percentage(50)])
                .split(f.size());

            let info = if !zen {
                format!(" [↑/↓] Sens: {:.2} [j/k] Limit: {}% [Z] Zen ", current_resp, limit)
            } else { "".to_string() };

            for (i, data) in [
                (&left_data, Color::Cyan, format!(" LEFT CHANNEL{}", info)),
                (&right_data, Color::Magenta, " RIGHT CHANNEL ".to_string())
            ].iter().enumerate() {

                // Dynamic vertical layout based on the 'limit' variable
                let vertical_chunks = Layout::default()
                    .direction(Direction::Vertical)
                    .constraints([
                        Constraint::Percentage(100 - limit), // Top gap
                        Constraint::Percentage(limit),     // Bar container
                    ])
                    .split(main_chunks[i]);

                let mut block = Block::default();
                if !zen {
                    block = block.title(data.2.clone()).borders(Borders::ALL);
                }

                let barchart = BarChart::default()
                    .block(block)
                    .data(data.0)
                    .bar_width(vertical_chunks[1].width / 22) // Adjusted for cleaner density
                    .bar_style(Style::default().fg(data.1));

                f.render_widget(barchart, vertical_chunks[1]);
            }
        })?;
        if crossterm::event::poll(Duration::from_millis(16))? {
            if let crossterm::event::Event::Key(key) = crossterm::event::read()? {
                let mut buffer = shared_state.lock().unwrap();
                use crossterm::event::KeyCode; // Import KeyCode for cleaner matching

                match key.code {
                    KeyCode::Up => buffer.responsiveness = (buffer.responsiveness + 0.02).min(1.0),
                    KeyCode::Down => buffer.responsiveness = (buffer.responsiveness - 0.02).max(0.01),
                    KeyCode::Char('k') => buffer.height_limit = (buffer.height_limit + 1).min(100),
                    KeyCode::Char('j') => buffer.height_limit = (buffer.height_limit - 1).max(10),
                    KeyCode::Char('z') | KeyCode::Char('Z') => buffer.zen_mode = !buffer.zen_mode,
                    _ => break, // Exit on any other key
                }
            }
        }
    }

    crossterm::execute!(std::io::stdout(), crossterm::cursor::Show, crossterm::terminal::LeaveAlternateScreen)?;
    crossterm::terminal::disable_raw_mode()?;
    Ok(())
}

fn process_fft_physics<'a>(
    samples: &[f32],
    rate: u32,
    num_bars: usize,
    heights: &mut Vec<f32>,
    velocities: &mut Vec<f32>,
    channel_max: &mut f32,
    resp: f32,
) -> Vec<(&'a str, u64)> {
    let spectrum = samples_fft_to_spectrum(samples, rate, FrequencyLimit::Range(20.0, 10000.0), None).unwrap();
    let data = spectrum.data();

    let frame_max = data.iter().map(|(_, v)| v.val()).fold(0.0, f32::max);
    *channel_max = if frame_max > *channel_max {
        *channel_max * 0.7 + frame_max * 0.3
    } else {
        (*channel_max * 0.95).max(0.01)
    };

    let mut out = Vec::new();
    for i in 0..num_bars {
        let frac_low = (i as f32 / num_bars as f32).powf(2.0);
        let frac_high = ((i + 1) as f32 / num_bars as f32).powf(2.0);
        let start = (frac_low * data.len() as f32) as usize;
        let end = ((frac_high * data.len() as f32) as usize).clamp(start + 1, data.len());

        let bin_max = data[start..end].iter().map(|(_, v)| v.val()).fold(0.0, f32::max);
        let treble_boost = 1.0 + (i as f32 / num_bars as f32).powf(1.5) * 10.0;
        let normalized = (bin_max * treble_boost) / channel_max.max(0.001);
        let mut target_h = (normalized * 8.0).ln_1p() * 45.0;
        if target_h < 4.0 { target_h = 0.0; }

        let current_h = heights[i];
        if target_h > current_h {
            velocities[i] += (target_h - current_h) * resp;
        } else {
            velocities[i] -= 1.8;
        }

        velocities[i] *= 0.80;
        heights[i] += velocities[i];

        if heights[i] > 100.0 { heights[i] = 100.0; velocities[i] = 0.0; }
        if heights[i] < 0.0 { heights[i] = 0.0; velocities[i] = 0.0; }

        out.push(("", heights[i] as u64));
    }
    out
}
