#!/bin/bash

# --- System Functions --- #

# runSystemUpdate()
#
# Run updates for cargo, and apt
#
runSystemUpdate() {
  read -p "Do you really want to run system updates?" -n 1 -r
  echo # (optional) move to a new line
  if [[ $REPLY =~ ^[Yy]$ ]]; then
    rustup update && sudo apt update && sudo apt upgrade
  fi
}

# sizeOfHome()
#
# Print the size of the $HOME dir.
#
sizeOfHome() {
  du -sh "${HOME}" | awk '{print $1;}'
}

showPATH() {
  echo "${PATH}" | sed 's/:/\n/g'
}

# --- PyWal Functions --- #

# updateWal()
#
# Update pywal to use current desktop background
# image as color scheeme source
#
function updateWal() {
  currentWallpaper=$(gsettings get org.gnome.desktop.background picture-uri-dark | sed "s/'//g" | sed "s/file://g" | sed "s/\/\///g") &&
    [ -f "$currentWallpaper" ] &&
    wal -c &&
    wal -q --backend="$(cat ~/.lastColorBackend.txt)" -i "$currentWallpaper"
}

randomColorSchemeColorThief() {
  wal -i "${HOME}/Pictures/Backgrounds/" --backend colorthief
  echo "colorthief" >"${HOME}/.lastColorBackend.txt"
}

randomColorSchemeColorz() {
  wal -i "${HOME}/Pictures/Backgrounds/" --backend colorz
  echo "colorz" >"${HOME}/.lastColorBackend.txt"
}
randomColorSchemeWal() {
  wal -i "${HOME}/Pictures/Backgrounds/" --backend wal
  echo "wal" >"${HOME}/.lastColorBackend.txt"
}

randomColorSchemeHaishoku() {
  wal -i "${HOME}/Pictures/Backgrounds/" --backend haishoku
  echo "haishoku" >"${HOME}/.lastColorBackend.txt"
}

# --- Thelio bare git command shortcut functions --- #
thelio_diff_origin() {
  if [ -d "${HOME}/.Thelio" ]; then
    if "${HOME}/.darling/bin/thelio" rev-parse --git-dir >/dev/null 2>&1; then
      BRANCH="$("${HOME}/.darling/bin/thelio" branch | grep '\*' | sed 's/\*//g' | sed 's/ //g')"
      "${HOME}/.darling/bin/thelio" diff origin/"${BRANCH}"
    fi
  else
    echo -e "\033[1;37m (${HOME}/.Thelio) not found \033[0m"
  fi
}

thelio_branch() {
  if [ -d "${HOME}/.Thelio" ]; then
    if "${HOME}/.darling/bin/thelio" rev-parse --git-dir >/dev/null 2>&1; then
      branch=$("${HOME}/.darling/bin/thelio" rev-parse --abbrev-ref HEAD 2>/dev/null)
      if [ $? -eq 0 ]; then
        echo -e "\033[1;34m (${branch}) \033[0m"
      fi
    fi
  else
    echo -e "\033[1;37m (${HOME}/.Thelio) not found \033[0m"
  fi
}

thelio_status() {
  if [ -d "${HOME}/.Thelio" ]; then
    if "${HOME}/.darling/bin/thelio" rev-parse --git-dir >/dev/null 2>&1; then
      if [ -n "$("${HOME}/.darling/bin/thelio" status --porcelain)" ]; then
        # Get status
        status=$("${HOME}/.darling/bin/thelio" status --porcelain 2>/dev/null)
        # Replace newlines with a space. The following stackoverflow helped
        # with this solution:
        # https://stackoverflow.com/questions/1251999/how-can-i-replace-each-newline-n-with-a-space-using-sed
        status=$(echo "${status}" | sed -e ':a' -e 'N' -e '$!ba' -e 's/\n/ /g')
        echo -e "\033[1;35m (${status}) \033[0m"
      else
        echo -e "\033[1;32m (😎) \033[0m"
      fi
    fi
  else
    echo -e "\033[1;37m (${HOME}/.Thelio) not found \033[0m"
  fi
}

# --- git command shortcut functions --- #

git_diff_origin() {
  if [ -d .git ]; then
    if /usr/bin/git rev-parse --git-dir >/dev/null 2>&1; then
      BRANCH="$(/usr/bin/git branch | grep '\*' | sed 's/\*//g' | sed 's/ //g')"
      /usr/bin/git diff origin/"${BRANCH}"
    fi
  else
    echo -e "\033[1;37m (not a git repo) \033[0m"
  fi
}

git_branch() {
  if [ -d .git ]; then
    if /usr/bin/git rev-parse --git-dir >/dev/null 2>&1; then
      branch=$(/usr/bin/git rev-parse --abbrev-ref HEAD 2>/dev/null)
      if [ $? -eq 0 ]; then
        echo -e "\033[1;34m (${branch}) \033[0m"
      fi
    fi
  else
    echo -e "\033[1;37m (not a git repo) \033[0m"
  fi
}

git_status() {
  if [ -d .git ]; then
    if /usr/bin/git rev-parse --git-dir >/dev/null 2>&1; then
      if [ -n "$(/usr/bin/git status --porcelain)" ]; then
        # Get status
        status=$(/usr/bin/git status --porcelain 2>/dev/null)
        # Replace newlines with a space. The following stackoverflow helped
        # with this solution:
        # https://stackoverflow.com/questions/1251999/how-can-i-replace-each-newline-n-with-a-space-using-sed
        status=$(echo "${status}" | sed -e ':a' -e 'N' -e '$!ba' -e 's/\n/ /g')
        echo -e "\033[1;35m (${status}) \033[0m"
      else
        echo -e "\033[1;32m (😎) \033[0m"
      fi
    fi
  else
    echo -e "\033[1;37m (not a git repo) \033[0m"
  fi
}

git_signed_commit() {
  git commit -S && echo -e "\033[1;32m (signed commit 😎) \033[0m"
}
