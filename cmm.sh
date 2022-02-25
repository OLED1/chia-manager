#!/bin/bash
#############################################################################
#                                                                           #
#                   Chia Manager instance management script                 #
#                   Current Version: 0.1.3 alpha                            #
#                   Author: Oliver Edtmair (OLED1)                          #                  
#                                                                           #
#                   Changes:                                                #
#                   - Implemented installer script                          #
#                                                                           #
#############################################################################

##### Global variables #####
source ./cmm/coloring.sh

declare -A COMMANDS
declare -A HELP

COMMANDS=(
    [install]=0 
    #[reinstall]=1 
    #[check_install]=2
    #[uninstall]=3 
    [help]=4
)
HELP=(
    [install]="Install a new Chia(R)-Manager instance"
    [reinstall]="Reinstall an existing Chia(R)-Manager instance"
    [check_install]="Check an existing Chia(R)-Manager instance"
    [uninstall]="Completely remove Chia(R)-Manager from your system"
    [help]="Shows this dialog"
)

##### Code section #####
echo -e "$GREEN################################################################################
#           Chia-Manager instance manage script for Chia Manager WebGUI        #
#                             BY lucaaust and OLED1                            #
#                                                                              #                            
#           Project Sources:                                                   #
#           Server: https://github.com/OLED1/chia-manager                      #
#           Client: https://github.com/OLED1/chia-manager-client               #
#                                                                              #
#                                                                              #
#           Please submit feature requests and issues there if you have some.  #
#           Thank you for using our project \xf0\x9f\x98\x80                                 #
################################################################################$NOCOLOR"

show_help() {
    echo "${INFTXT}The following commands are valid:"
    for cmd in ${!COMMANDS[@]}; do
        echo "${OPTION}${BLUE}${cmd}${NOCOLOR}: ${HELP[$cmd]}"
    done
    exit 0
}

install(){
    source ./cmm/install.sh
}

if [ -z "$1" ];then
    show_help
fi

if [[ ${COMMANDS[$1]} =~ ^-?[0-9]+$ ]] && [ "${COMMANDS[$1]}" -ge 0 ];then
    if [ "${COMMANDS[$1]}" -eq 0 ];then
        install
    elif [ "${COMMANDS[$1]}" -eq 3 ];then
        show_help 
    fi
else
    show_help
fi

exit 0