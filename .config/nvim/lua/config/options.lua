-- Options are automatically loaded before lazy.nvim startup
-- Default options that are always set: https://github.com/LazyVim/LazyVim/blob/main/lua/lazyvim/config/options.lua
-- Add any additional options here

---------------------------------------------------------------------
-------------------------- Darling Settings -------------------------
---------------------------------------------------------------------
-- IMPORTANT: Settings must be defined before return {} block

-- Prevent undo history from persisting into future sessions.
vim.opt.undofile = false

-- Disable mouse
vim.opt.mouse = ""

-- Prefered Coloscheme | Set down below
-- vim.cmd.colorscheme("darling")

-- Enable vim's built in syntax highlighting
vim.opt.syntax = "on"

-- Enable a cursor column
vim.opt.cursorcolumn = true

-- Enable cursor line
vim.opt.cursorline = true

-- Highlight column 70 as a visual aide
vim.opt.colorcolumn = "70"

-- Enable relative line numbers
vim.opt.relativenumber = true

-- Convert tabs to spaces
vim.opt.expandtab = true

-- Set tabstop to 8, as is recommended by the docs
-- "Note: Setting 'tabstop' to any other value than 8 can make your
--  file appear wrong in many places."
vim.opt.tabstop = 8

-- Disable softtabstop
vim.opt.softtabstop = 0

-- Enable expandtab
vim.opt.expandtab = true

-- Set shiftwidth to 4 which will determine the Number of spaces to
-- use for each step of (auto)indent.
-- Used for |'cindent'|, |>>|, |<<|, etc.
vim.opt.shiftwidth = 4

-- Enable smart tab to so that a <Tab> in front of a line inserts
-- blanks according to the 'shiftwidth' value.
vim.opt.smarttab = true

-- Don't wrap long lines
vim.opt.wrap = false

-- Turn on spellcheck
vim.opt.spell = true
