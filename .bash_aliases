#!/bin/bash

# Terminal Command Shortcuts
alias bat='batcat'
alias r='history -c; clear'
alias c='clear'
alias ls='exa -alG --group-directories-first'
alias q='exit'
alias tls='tmux ls'
alias tna='tmux a -t'
alias tns='tmux new -s'
alias vim='nvim'
# Open all files listed in .vimfilelist in vim
alias voa='xargs --delimiter "\\n" --arg-file="./.vimfilelist" nvim --'

# App Image Shortcuts
alias audacity="~/Downloads/AppImages/Audacity &"
alias gimp="~/Downloads/AppImages/Gimp &"
alias musescore="~/Downloads/AppImages/MuseScore &"
# Note: nvim is being used as an App image, but is
#       installed in ~/.local/bin/nvim
alias v='nvim'

# git aliases
alias gst="git status"
alias gdf="git diff"
alias gbr="git branch"
# aliases to check on bare Thelio repo at ~/.Thelio
alias thst="thelio_status"
alias thbr="thelio_branch"
alias gs='echo -e "\033[1;93m gs is disabled via alias defined in .bash_aliases \033[0m"'
# End
