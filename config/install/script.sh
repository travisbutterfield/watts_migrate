#!/bin/bash

configname=$1
purple=$(tput setb 5)
green=$(tput setb 2 && tput setf 7)
endcolor=$(tput sgr0)

drush config:delete ${configname} 2> /dev/null

if [ $? -eq 0 ]
then
  printf "${green}[success]${endcolor} Deleted ${configname} from active configuration.\n" > /dev/tty
  exit 0
else
  printf "${purple}[error]${endcolor} Attempted to delete ${configname} from active configuration, but it does not exist.\n" >&2
  exit 1
fi