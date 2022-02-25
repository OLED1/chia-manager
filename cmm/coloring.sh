#!/bin/bash
#Text coloring
NOCOLOR=$(tput sgr0)
BLUE=$(tput setaf 4)
CYAN=$(tput setaf 6)
GREEN=$(tput setaf 2)
RED=$(tput setaf 1)
YELLOW=$(tput setaf 3)
MAGENTA=$( tput setaf 5 )

#Text formatting
BOLD=$(tput bold)

#Standard Outputs
INFTXT=$BLUE[INF]$NOCOLOR
SUCTXT=$GREEN[SUC]$NOCOLOR
ERRTXT=$RED[ERR]$NOCOLOR
WARTXT=$YELLOW[WAR]$NOCOLOR
QUESTION=$CYAN[QES]$NOCOLOR
OPTION=$CYAN[OPT]$NOCOLOR
TASK=$MAGENTA[TSK]$NOCOLOR