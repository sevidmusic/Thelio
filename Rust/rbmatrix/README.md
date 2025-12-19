# Rust Matrix Rain

A high-performance, smooth-scrolling cmatrix clone written in Rust.
This project utilizes the crossterm library to create a terminal-based
digital rain effect with a focus on performance, color depth,
and responsiveness.

Key Features

    Flicker-Free Animation: Uses delta-rendering (erasing only the tail)
                            instead of full-screen clears to ensure
                            "buttery" smooth movement.

    Dynamic Gradients: Every column generates a unique color profile
                       that fades from a bright white "head" to a deep
                       colored tail.

    Real-time Speed Control: Dynamically adjust the frame rate using
                             keyboard shortcuts.

    Responsive Layout: Automatically re-calculates and re-seeds the
                       matrix when the terminal window is resized.

    Character Mutation: Characters randomly "glitch" as they fall,
                        mimicking the original Matrix digital rain
                        effect.

# Controls
Key	        Action
Up Arrow / k	Increase falling speed (higher FPS)
Down Arrow / j	Decrease falling speed (lower FPS)
q / Esc	        Exit the program safely

# Installation Prerequisites

You must have the Rust toolchain installed. If you don't have it, visit
[rust-lang.org]('https://rust-lang.org/tools/install/').

Building from Source

Clone the repository:

```
git clone https://github.com/yourusername/rust-matrix-rain.git
cd rust-matrix-rain
```

Build the binary:

```
cargo build --release
```

Note: Using the `--release` flag is highly recommended for smooth
performance.

Run the application:

```
./target/release/rust-matrix-rain
```

# Project Structure

    Column Struct: Encapsulates the state of a single vertical
                   stream, including its position, characters,
                   and unique color gradient.

    update() Logic: Manages the movement timing and random
                    character mutations.

    main() Loop: A non-blocking event loop that handles:

        Input: Key presses for speed and quitting.

        Signals: Terminal resize events.

        Rendering: Drawing only necessary characters to stdout
                   to minimize I/O overhead.

# Notes:

To bundle the project into a standalone binary:

1. To create the binary:

```
cargo build --release
```

2. To install it globally:

```
cargo install --path .
```

3. Cross-Compilation (Optional).
   To build a version of for a different operating system use cross:

    Install `cross` if not already installed:

    ```
    cargo install cross
    ```

    Then build for target os, for example, to build for Windows:

    ```
    cross build --target x86_64-pc-windows-gnu --release
    ```

# A Note on Performance

If there is any lag on large screens (like a 4K monitor stretched wide),
can change the `.step_by(3)` in `main.rs` to `.step_by(4)` or higher.
This will reduce the number of active columns, and lowers the number
of terminal write calls per frame.

