#!/bin/bash

if [ -n "${BASH_VERSION}" ]; then
  # include .bashrc if it exists
  if [ -f "${HOME}/.bashrc" ]; then
    . "${HOME}/.bashrc"
    status="(Loaded .bashrc successfully via .bash_profile)"
    echo -e "\033[1;92m ${status} \033[0m"
  fi
fi
