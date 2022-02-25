#!/bin/bash
declare -A OPTIONS_ANS

check_system(){
    echo "============================================================================================"
    echo "${TASK}Checking operating system"
    echo "============================================================================================"
    echo "${INFTXT}Currently supported are ${BOLD}Ubuntu 20.04${NOCOLOR} and ${BOLD}Debian 10${NOCOLOR}."
    echo "${INFTXT}You can use any Ubuntu or Debian version you like. We only check if a specific file is existing."
    echo "${INFTXT}${RED}We do not guarantee${NOCOLOR} that it will work if you are using another versions."
    which lsb_release > /dev/null 2>&1
    lsb_release_found=$?
    if [ $lsb_release_found == 0 ];then
        echo "${SUCTXT}This system seems to be supported."
    else
        echo "${ERRTXT}This system seems not to be supported currently. We are sorry."
    fi

    return $lsb_release_found
}

configure_new_install(){
    echo "============================================================================================"
    echo "${TASK}Configuration determinations"
    echo "============================================================================================"
    echo "${TASK}Please answer the following:"
    echo "--------------------------------------------------------------------------------------------"
    read_answer "y" "Will this instance be externally available?"
    OPTIONS_ANS["ext_available"]=$?

    if [ ${OPTIONS_ANS["ext_available"]} == 0 ];then
        OPTIONS_ANS["get_dns_name"]=0
    else
        echo "--------------------------------------------------------------------------------------------"
        read_answer "y" "Are you running a local DNS server where you will configure a DNS Name?"
        OPTIONS_ANS["get_dns_name"]=$?
    fi

    echo "--------------------------------------------------------------------------------------------"
    if [ ${OPTIONS_ANS["get_dns_name"]} == 0 ];then
        OPTIONS_ANS["dns_name"]="$( read_text "" "Which DNS name will this instance get (E.g. chia-manager.example.com)?" )"
    else
        echo "${INFTXT}${YELLOW}Please note: WE CURRENTLY DON'T KNOW IF THE APPLICATION WILL WORK BY STATING ONLY AN IP ADDRESS. YOU MAY NEED TO DO SOME MANUAL DOINGS AFTER INSTALLATION.${NOCOLOR}"
        OPTIONS_ANS["dns_name"]="$( read_text "" "Which IP address will this instance get (E.g. 172.31.0.1)?" )"
    fi

    echo "--------------------------------------------------------------------------------------------"
    host ${OPTIONS_ANS["dns_name"]} > /dev/null 2>&1
    host_resolvable=$?
    if [ $host_resolvable == 0 ];then
        echo "${INFTXT}Your stated DNS Name ${OPTIONS_ANS["dns_name"]} seems to be ${BOLD}resolvable${NOCOLOR}."
    else
        echo "${INFTXT}Your stated DNS Name ${OPTIONS_ANS["dns_name"]} seems ${BOLD}NOT to be resolvable${NOCOLOR}. You may not be able to open installer at the end."
        echo "${INFTXT}Please do not forget to add the domain ${BOLD}${OPTIONS_ANS["dns_name"]}${NOCOLOR} to your DNS server."
    fi

    echo "--------------------------------------------------------------------------------------------"
    OPTIONS_ANS["mail_address"]="$( read_text "" "Please state your current e-mail address?" )"

    echo "--------------------------------------------------------------------------------------------"
    #0 = Use self signed certificate, 1 = State own, 2 = Use certbot
    if [ ${OPTIONS_ANS["get_dns_name"]} == 0 ];then
        echo "${INFTXT}${BOLD}We need to generate a certificate. Please choose:${NOCOLOR}"
        show_cert_help

        if [ ${OPTIONS_ANS["ext_available"]} == 0 ];then
            options_text="Self signed = 0, State own = 1, Use certbot = 2"
            options_num=( 0 1 2 )
            option_default=2
        else
            options_text="Self signed = 0, State own = 1"
            options_num=( 0 1 )
            option_default=1
        fi

        OPTIONS_ANS["cert_generate"]=$( choose_option "$option_default" "$options_text" "${options_num[@]}" )
    else
        echo "${INFTXT}Because we aren't using a DNS Name we need to generate a self signed certificate"
        OPTIONS_ANS["cert_generate"]=0
    fi

    echo "--------------------------------------------------------------------------------------------"
    echo "${TASK}We need to setup a websocket server. Please just enter or type in:"
    echo "--------------------------------------------------------------------------------------------"
    OPTIONS_ANS["local_wss_port"]="$( read_text "8443" "Which websocket port should be used? You can leave it default if this is your first installation." )"
    OPTIONS_ANS["local_wss_listening_dir"]="$( read_text "/chiamgmt" "Which listener directory should be used? You can leave it default." )"

    echo "--------------------------------------------------------------------------------------------"
    echo "${TASK}Database setup"
    echo "--------------------------------------------------------------------------------------------"
    read_answer "" "Do you already have an existing mysql database installation?"
    OPTIONS_ANS["db_exists"]=$?

    while true;do
        db_name="$( read_text "chia_manager_db" "Which name should the database get?" )"
        if [ ${OPTIONS_ANS["db_exists"]} == 1 ];then
            OPTIONS_ANS["db_name"]=$db_name
            break
        fi

        check_db_exists $db_name "" "" "" 1
        db_exists=$?
        if [ ${OPTIONS_ANS["db_exists"]} == 0 ] && [ $db_exists == 0 ];then
            OPTIONS_ANS["db_name"]=$db_name
            break
        fi
    done

    OPTIONS_ANS["db_host"]="$( read_text "localhost" "Please select the host name or ip address of your mysql installation." )"
    if [ "${OPTIONS_ANS["db_host"]}" != "localhost" ] || [ "${OPTIONS_ANS["db_host"]}" != "127.0.0.1" ];then
        echo "${INFTXT}It seems you are using a remote database. So creating a user which is allowed to connect from ${OPTIONS_ANS["db_host"]} makes no sense."
        OPTIONS_ANS["db_user_remote_permission"]="$( read_text "" "Please state the IP address of the host you will run Chia(R)-Manager at." )"  
    else
        OPTIONS_ANS["db_user_remote_permission"]=${OPTIONS_ANS["db_host"]}
    fi

    OPTIONS_ANS["db_username"]="$( read_text "chia_manager_user" "What username do you like to use for the database." )"
    OPTIONS_ANS["db_password"]="$( read_text "" "Which password should be set for the user ${OPTIONS_ANS["db_username"]}. Prerequisite: [a-z,A-Z,1-9,Special characters,At least 8 characters]" )"

    review_settings
    return $?
}

#$1 = dbname, $2 = user, $3 = password, $4 = host, $5 = (DB should exist: 0, DB should not exist: 1)
check_db_exists(){
    echo "--------------------------------------------------------------------------------------------"
    echo "${TASK}Database checks."
    echo "--------------------------------------------------------------------------------------------"

    while true;do
        if [ -z $1 ];then
            database="$( read_text "chia_manager_db" "Please state the mysql database name." )"
        else
            database=$1
        fi
        if [ -z $2 ];then
            user="$( read_text "root" "Please state the mysql super user name." )"
        else
            user=$2
        fi
        if [ -z $3 ];then
            password="$( read_text "" "Please state the password for database ${BOLD}admin 'root'${NOCOLOR}." )"
        else
            password=$3
        fi
        if [ -z $4 ];then
            host="$( read_text "localhost" "Please state the mysql host where it is listening." )"
        else
            host=$4
        fi
        
        test_mysql_connection $user $password $host
        connect_success=$?

        if [ $connect_success == 0 ];then
            echo "${SUCTXT}Successfully tested database connection."
        else
            echo "${ERRTXT}Could not connect to database."
            read_answer "y" "Try again?"
            try_again=$?
            if [ $try_again == 0 ];then
                continue
            else
                break
            fi
        fi

        echo "--------------------------------------------------------------------------------------------"
        echo "${TASK}Checking database $1 exists."
        echo "--------------------------------------------------------------------------------------------"
        db_count="$( mysql --user=$user --password=$password --host=$host --execute="SHOW DATABASES LIKE '$database'" | wc -l )";

        if [ $db_count -gt 0 ] && [ $5 == 0 ];then
            echo "${SUCTXT}Database $1 existing."
        elif [ $db_count -eq 0 ] && [ $5 == 1 ];then
            echo "${SUCTXT}Database $1 not existing."
        else
            echo "${ERRTXT}mysql did not return whats expecting."
            return 1
        fi
        return 0
    done
}

#$1 = user, $2 = password, $3 = host
test_mysql_connection(){
    echo "--------------------------------------------------------------------------------------------"
    echo "${TASK}Checking connection. Please state the mysql password for user $user."
    echo "--------------------------------------------------------------------------------------------"
    mysql --user=$1 --password=$2 --host=$3 --execute=exit > /dev/null 2>&1
    connect_success=$? 

    if [ $connect_success == 0 ];then
        echo "${SUCTXT}Connected to database."
    else
        echo "${ERRTXT}Could not connect to database."
    fi

    return $connect_success   
}

read_answer(){
    while true; do
        read -p "${QUESTION}${2}[y|Y = Yes,n|N = No] [Default: ${1}] " yn

        if [ -z $yn ] && [ ! -z $1 ];then
            yn=$1
        fi

        if [ "$yn" == "" ];then
            continue
        fi

        case $yn in
            [Yy]* ) answer=0; break;;
            [Nn]* ) answer=1; break;;
            * ) echo "Please answer yes or no.";;
        esac
    done

    return $answer
}

read_text(){
    local func_result=""
    while true; do
        read -p "${QUESTION}${2} [Default: ${1}] " func_result

        if [ -z $func_result ] && [ ! -z $1 ];then
            func_result=$1
        fi

        if [ "$func_result" == "" ];then
            continue
        fi

        read_answer "" "You have chosen ${BLUE}$func_result${NOCOLOR}. Is that correct?"
        if [ $? == 0 ];then
            break
        fi
    done
    
    echo "$func_result"
}

choose_option(){
    local valid_options=("$@")
    local func_result=""
    while true; do
        read -p "${QUESTION}${2} [Default: ${1}] " func_result

        if [ -z $func_result ] && [ ! -z $1 ];then
            func_result=$1
        fi

        if [ "$func_result" == "" ];then
            continue
        fi

        if [[ " ${valid_options[*]} " =~ " $func_result " ]]; then
            read_answer "" "You have chosen ${BLUE}$func_result${NOCOLOR}. Is that correct?"
            if [ $? == 0 ];then
                break
            fi
        fi
    done
    
    echo "$func_result"
}

review_settings(){
    echo "============================================================================================"
    echo "${TASK}Please review your settings now"
    echo "============================================================================================"
    echo "${INFTXT}${BOLD}RAW Output${NOCOLOR}"
    echo "--------------------------------------------------------------------------------------------"
    for option_ans in ${!OPTIONS_ANS[@]}; do
        echo "${INFTXT}$option_ans: ${OPTIONS_ANS[$option_ans]}"
    done
    echo "--------------------------------------------------------------------------------------------"
    echo "${INFTXT}${BOLD}Explaination${NOCOLOR}"
    echo "--------------------------------------------------------------------------------------------"
    if [ ${OPTIONS_ANS["ext_available"]} == 0 ];then
        echo "${INFTXT}Your installation will ${BLUE}be available through the INTERNET.${NOCOLOR}"
    else
        echo "${INFTXT}Your installation will ${BLUE}NOT be available through the INTERNET.${NOCOLOR}"
    fi

    if [ ${OPTIONS_ANS["get_dns_name"]} == 0 ];then
        echo "${INFTXT}Your installation will get a DNS Name which will be ${BLUE}${OPTIONS_ANS["dns_name"]}${NOCOLOR}."
    else
        echo "${INFTXT}Your installation will NOT get a DNS Name. It will be configured using the IP ${BLUE}${OPTIONS_ANS["dns_name"]}${NOCOLOR}."
    fi

    echo "${INFTXT}The e-mail which will be stated in the config file will be ${BLUE}${OPTIONS_ANS["mail_address"]}${NOCOLOR}."

    if [ ${OPTIONS_ANS["cert_generate"]} == 0 ];then
        echo "${INFTXT}The connection will be secured using a ${YELLOW}self signed certificate.${NOCOLOR}"
    elif [ ${OPTIONS_ANS["cert_generate"]} == 1 ];then
        echo "${INFTXT}The connection will be secured using an ${GREEN}already existing certfificate.${NOCOLOR}"
    else
        echo "${INFTXT}The connection will be secured using ${GREEN}freely available certificates generated by certbot and validated by letsencrypt.${NOCOLOR}"
    fi

    echo "${INFTXT}Your websocket server will be listening on your local server using port ${GREEN}${OPTIONS_ANS["local_wss_port"]}${NOCOLOR} and listening directory ${GREEN}${OPTIONS_ANS["local_wss_listening_dir"]}${NOCOLOR}."
    if [ ${OPTIONS_ANS["db_exists"]} == 0 ];then
        echo "${INFTXT}We will use an ${BLUE}existing database${NOCOLOR}. The database for Chia(R)-Manager will be ${BLUE}${OPTIONS_ANS["db_name"]}${NOCOLOR}."
    else
        echo "${INFTXT}We will setup a ${BLUE}plain database${NOCOLOR}. The database for Chia(R)-Manager will be ${BLUE}${OPTIONS_ANS["db_name"]}${NOCOLOR}."
    fi
    echo "${INFTXT}The database ${BLUE}${OPTIONS_ANS["db_name"]}${NOCOLOR} will be setup using host ${BLUE}${OPTIONS_ANS["host"]}${NOCOLOR}, user ${BLUE}${OPTIONS_ANS["db_username"]}@${OPTIONS_ANS["db_user_remote_permission"]}${NOCOLOR} and password ${BLUE}${OPTIONS_ANS["db_password"]}${NOCOLOR}."

    echo "--------------------------------------------------------------------------------------------"

    read_answer "" "Are these settings correct? If not, this script will abort and you need to reopen the installer again."
    return $?
}

show_cert_help(){
    echo "--------------------------------------------------------------------------------------------"
    echo "${INFTXT}[Self signed](${YELLOW}Not recommended${NOCOLOR})A self signed certificate will be generated on the system itself and will throw a connection warning when opening the web gui."
    echo "${INFTXT}[State own]If you already have a certificate for the domain you want to use, choose this option."
    echo "${INFTXT}[${GREEN}Certbot${NOCOLOR}](${GREEN}Recommended${NOCOLOR}) Certbot offers free and valid certificates which are installed and renewed automatically. If you select this option, this installer will setup certbot for you."
    echo "--------------------------------------------------------------------------------------------"
}

install_system_modules(){
    echo "============================================================================================"
    echo "${TASK}Installing apache2, mysql-server and PHP 7.4."
    echo "============================================================================================"
    echo "${YELLOW}Please state 'root' passwords when asked.$NOCOLOR"
    echo "${INFTXT}The following command will be executed:"
    echo "${INFTXT}sudo apt-get install apache2 mysql-server libapache2-mod-fcgid php7.4 php7.4-common php7.4-json php7.4-mbstring php7.4-igbinary php7.4-tokenizer php7.4-apcu php7.4-readline php7.4-sockets php7.4-intl php7.4-posix php7.4-sysvmsg php7.4-cli php7.4-fpm php7.4-mysql php7.4-zip -y"
    read_answer "y" "Proceed?"
    proceed=$?
    if [ $proceed == 1 ];then
        exit
    fi
    echo "${INFTXT}${YELLOW}Please state 'root' passwords when asked.$NOCOLOR"

    sudo apt-get install apache2 mysql-server libapache2-mod-fcgid php7.4 php7.4-common php7.4-json php7.4-mbstring php7.4-igbinary php7.4-tokenizer php7.4-apcu php7.4-readline php7.4-sockets php7.4-intl php7.4-posix php7.4-sysvmsg php7.4-cli php7.4-fpm php7.4-mysql php7.4-zip -y  > /dev/null 2>&1
    service_installation=$?
    
    if [ $service_installation == 0 ];then
        echo "${SUCTXT}Installation ended successfully." 
    else
        echo "${ERRTXT}An error occured during the installation procedure."
    fi
    return $service_installation
}

enable_services_and_apache_modules(){
    echo "============================================================================================"
    echo "${TASK}Enabling system services and apache2 modules."
    echo "============================================================================================"
    echo "${INFTXT}The following command will be executed:"
    echo "${INFTXT}sudo a2enmod headers proxy proxy_http proxy_fcgi ssl rewrite actions fcgid alias proxy_wstunnel"
    echo "${INFTXT}sudo a2enconf php7.4-fpm"
    echo "${INFTXT}sudo systemctl enable --now apache2"
    echo "${INFTXT}sudo systemctl enable --now mysql"
    echo "${INFTXT}sudo systemctl enable --now php7.4-fpm"

    read_answer "y" "Proceed?"
    proceed=$?
    if [ $proceed == 1 ];then
        exit
    fi
    echo "${INFTXT}${YELLOW}Please state 'root' passwords when asked.$NOCOLOR"
    
    sudo a2enmod headers proxy proxy_http proxy_fcgi ssl rewrite actions fcgid alias proxy_wstunnel  > /dev/null 2>&1
    a2enmod_success=$?
    if [ $a2enmod_success -gt 0 ];then
        echo "${ERRTXT}An error occured during the a2enmod command processing (Exit Code $a2enmod_success). Cancelling..."
        return $a2enmod_success   
    fi
    sudo a2enconf php7.4-fpm  > /dev/null 2>&1
    a2enconf_success=$?
    if [ $a2enconf_success -gt 0 ];then
        echo "${ERRTXT}An error occured during the apache starting command processing (Exit Code $a2enconf_success). Cancelling..."
        return $a2enconf_success    
    fi
    sudo systemctl enable --now apache2  > /dev/null 2>&1
    apache_enabling=$?
    if [ $apache_enabling -gt 0 ];then
        echo "${ERRTXT}An error occured during the apache enableing and starting command processing (Exit Code $apache_enabling). Cancelling..."
        return $apache_enabling   
    fi
    sudo systemctl enable --now mysql  > /dev/null 2>&1
    mysql_enabling=$?
    if [ $mysql_enabling -gt 0 ];then
        echo "${ERRTXT}An error occured during the mysql enableing and starting command processing (Exit Code $mysql_enabling). Cancelling..."
        return $mysql_enabling   
    fi
    sudo systemctl enable --now php7.4-fpm  > /dev/null 2>&1
    php_enabling=$?
    if [ $php_enabling -gt 0 ];then
        echo "${ERRTXT}An error occured during the php enableing and starting command processing (Exit Code $php_enabling). Cancelling..."
        return $php_enabling   
    fi

    echo "${SUCTXT}Commands successfully processed."
    return 0
}

create_directories(){
    local logdir="/var/log/apache2/${OPTIONS_ANS["dns_name"]}"
    local certdir="/etc/apache2/ssls/chia-manager"
    echo "============================================================================================"
    echo "${TASK}Creating directories"
    echo "============================================================================================"
    echo "${TASK}Creating logging directory."
    echo "--------------------------------------------------------------------------------------------"
    sudo mkdir -p $logdir > /dev/null 2>&1
    log_dir_created=$?
    if [ $log_dir_created == 0 ];then
        echo "${SUCTXT}Successfully created directory $logdir."
    else
       echo "${SUCTXT}Logdir $logdir could not be created. Cancelling..."
       return $log_dir_created
    fi

    if [ ${OPTIONS_ANS["cert_generate"]} == 1 ];then
        echo "--------------------------------------------------------------------------------------------"
        echo "${TASK}Creating ssl directory $certdir."
        echo "--------------------------------------------------------------------------------------------"
        sudo mkdir -p $certdir > /dev/null 2>&1
        cert_dir_created=$?
        if [ $cert_dir_created == 0 ];then
            echo "${SUCTXT}Successfully created directory $certdir."
        else
            echo "${SUCTXT}Directory $certdir could not be created. Cancelling..."
            return $cert_dir_created
        fi
    fi

    return 0
}

setup_vhost(){
    local vhost_folder="/etc/apache2/sites-available/"
    local vhost_file="chia-manager.conf"
    echo "============================================================================================"
    echo "${TASK}Setting up apache2 vhost."
    echo "============================================================================================"
    vhost_template="$( cat cmm/vhost_template )"
    vhost_template="${vhost_template//"[vhost_mail]"/${OPTIONS_ANS["mail_address"]}}"
    vhost_template="${vhost_template//"[vhost_domain]"/${OPTIONS_ANS["dns_name"]}}"
    vhost_template="${vhost_template//"[project_root]"/$( pwd )}"
    vhost_template="${vhost_template//"[vhost_log_root]"/"/var/log/apache2"}"
    vhost_template="${vhost_template//"[vhost_wss_dir]"/${OPTIONS_ANS["local_wss_listening_dir"]}}"
    vhost_template="${vhost_template//"[vhost_wss_port]"/${OPTIONS_ANS["local_wss_port"]}}"

    while true; do
        if [ ! -f "$vhost_folder$vhost_file" ];then
            break
        else
            echo "${INFTXT}Config $vhost_file already existing, please state a new name which will not be $vhost_file."
            vhost_file="$( read_text ${OPTIONS_ANS["dns_name"]} "Which name should the config get? (E.g. chia-manager-1)?" ).conf"
        fi
    done

    if [ ! -f "$vhost_folder$vhost_file" ];then
        echo "${INFTXT}Writing new config to $vhost_folder$vhost_file."
        sudo touch /tmp/${vhost_file}
        sudo echo "$vhost_template" > "/tmp/${vhost_file}"
        sudo chown root. /tmp/${vhost_file}
        sudo cp /tmp/${vhost_file} ${vhost_folder}${vhost_file}
        sudo rm -rf /tmp/${vhost_file}
    fi

    if [ -f "$vhost_folder$vhost_file" ];then
       echo "${SUCTXT}Successfully wrote new vhost file $vhost_folder$vhost_file."
    else
        echo "${ERRTXT}An error occured writing new vhost file $vhost_folder$vhost_file. Aborting... Please try again or report us the error if this problem still exists."
        sudo rm -rf $vhost_folder$vhost_file
        return 1
    fi

    echo "--------------------------------------------------------------------------------------------"
    echo "${TASK}Enabling new vhost file"
    echo "--------------------------------------------------------------------------------------------"
    sudo a2ensite $vhost_file > /dev/null 2>&1
    vhost_enabled=$?
    if [ $vhost_enabled == 0 ];then
        echo "${SUCTXT}Successfully enabled new vhost $vhost_file."
    else
        echo "${ERRTXT}An error occured enabling new vhost file. Aborting. Pelase try again."
        return 1
    fi

    echo "--------------------------------------------------------------------------------------------"
    echo "${TASK}Testing apache2 config"
    echo "--------------------------------------------------------------------------------------------"
    apache_test="$( sudo apache2ctl -t )"
    sudo apache2ctl -t > /dev/null 2>&1 
    apache_test_success=$?
    if [ $apache_test_success == 0 ];then
        echo "${SUCTXT}Successfully tested apache configs."
    else
        echo "${ERRTXT}The apache2 config was not tested successfully. Message: "
        echo $apache_test
        echo "${ERRTXT}Please check that."
        return 1
    fi

    echo "--------------------------------------------------------------------------------------------"
    echo "${TASK}Restarting apache2 service"
    echo "--------------------------------------------------------------------------------------------"
    sudo systemctl restart apache2 > /dev/null 2>&1
    apache2_restarted=$?
    if [ $apache2_restarted == 0 ];then
        echo "${SUCTXT}Successfully restarted apache2 service."
    else
        echo "${ERRTXT}The apache2 service could not be restarted."
        return 1
    fi

    return 0
}

generate_certs(){
    local certdir="/etc/apache2/ssls/chia-manager"
    echo "============================================================================================"
    echo "${TASK}SSL certificate encryption set-up."
    echo "============================================================================================"
    echo "${INFTXT}${YELLOW}Please state 'root' passwords when asked.$NOCOLOR"
    if [ ${OPTIONS_ANS["cert_generate"]} == 0 ];then
        echo "${INFTXT}We will use the default systems snakeoil certficates for connection encryption. We recommend to replace them with valid certificates."
    elif [ ${OPTIONS_ANS["cert_generate"]} == 1 ];then
        echo "${INFTXT}You have chosen to install your own certificates."
        echo "${INFTXT}Please put them to ${BOLD}$certdir${NOCOLOR} using the sftp server via port 22."
        echo "${INFTXT}After that open the vhost file."
        echo "${INFTXT}nano /etc/apache2/sites-available/${OPTIONS_ANS["dns_name"]}.conf"
        echo "${INFTXT}Replace SSLCertificateFile /etc/ssl/certs/ssl-cert-snakeoil.pem with SSLCertificateFile $certdir/<your-cert>.crt"
        echo "${INFTXT}Replace SSLCertificateKeyFile /etc/ssl/private/ssl-cert-snakeoil.key with SSLCertificateFile $certdir/<your-cert>.key"
        echo "${INFTXT}No press Ctrl+O, Y, Ctrl+X to save and close the file."
        read_answer "" "We will now wait until this steps are done if you want to do this now. If you want to do it later, press y or Y now."
    else
        echo "${INFTXT}Generating certfificates using certbot."
        echo "${INFTXT}Checking if certbot is installed."
        which certbot  > /dev/null 2>&1
        certbot_avail=$?

        if [ $certbot_avail == 1 ];then
            echo "${WARTXT}Certbot not installed."
            echo "${INFTXT}Installing certbot using apt-get install certbot python3-certbot-apache."
            sudo apt-get install certbot python3-certbot-apache > /dev/null 2>&1
        fi

        which certbot > /dev/null 2>&1
        certbot_avail=$?

        if [ $certbot_avail == 0 ];then
            echo "${SUCTXT}${YELLOW}Certbot installed. Executing commands. Please answer all questions certbot will ask.${NOCOLOR}"
            echo "${INFTXT}${BOLD}Choose 'No redirect' at the end${NOCOLOR}."
            sleep 2
            sudo certbot -d ${OPTIONS_ANS["dns_name"]}
            certbot_success=$?
            if [ $certbot_success == 0 ];then
               echo "${WARTXT}Successfully generated new certificate."
            else
                echo "${WARTXT}Certificate generation for ${OPTIONS_ANS["dns_name"]} failed, but no worries you can try again later by using the 'certbot' command." 
            fi
        else
            echo "${WARTXT}The certbot installation failed. No worries you can try to reinstall certbot later."
            echo "${INFTXT}Just type in the following: apt-get install certbot python3-certbot-apache."
        fi
    fi

    local dhdir="/etc/ssl/certs/"
    local dhfile="dhparam.pem"
    if [ ! -f "$dhdir$dhfile" ];then
        echo "--------------------------------------------------------------------------------------------"
        echo "${TASK}Generating dh-parameter for better connection encryption."
        echo "--------------------------------------------------------------------------------------------"
        sudo mkdir -p $dhdir
        sudo openssl dhparam -out "$dhdir$dhfile" 2048
    fi

    return 0
}

setup_mysql_database(){
    echo "============================================================================================"
    echo "${TASK}Installing and setting up database."
    echo "============================================================================================"
    if [ ${OPTIONS_ANS["db_exists"]} == 1 ];then
        echo "${INFTXT}We need to process the first mysql installer called 'mysql_secure_installation'."
        echo "${INFTXT}The root user does not have a password set currently. Just press enter."
        echo "${INFTXT}Please state a strong password for the user 'root' because it's the databse administrator with all rights."
        echo "${INFTXT}Choose at least 'medium' for password strength."
        echo "${INFTXT}You can choose the default for all other settings. ${BOLD}Anyway, read carefully whats asked!${NOCOLOR}"
        read_answer "y" "Should we hang on?"
        mysql_secure_installation
    fi

    echo "--------------------------------------------------------------------------------------------"
    echo "${TASK}Setting up database ${BOLD}${OPTIONS_ANS["db_name"]}${NOCOLOR} using the user ${BOLD}${OPTIONS_ANS["db_username"]}${NOCOLOR} with password ${BOLD}${OPTIONS_ANS["db_password"]}${NOCOLOR}."
    echo "--------------------------------------------------------------------------------------------"

    while true;do
        user="$( read_text "root" "Please state the mysql super user name." )"
        password="$( read_text "" "Please state the password for database ${BOLD}admin 'root'${NOCOLOR}." )"
        host="$( read_text "localhost" "Please state the mysql host where it is listening." )"
        test_mysql_connection $user $password $host
        connection_success=$?
        if [ $connection_success == 0 ];then
            break;
        else
            read_answer "y" "Try again?"
            try_again=$?
            if [ $try_again == 0 ];then
                continue
            else
                break
            fi
        fi
    done   

    create_db="$( mysql --user=${user} --password=${password} --host=${host} --execute="CREATE DATABASE ${OPTIONS_ANS["db_name"]};" )"
    create_db_success=$?
    if [ $create_db_success == 1 ];then
        echo "${ERRTXT}Error during database creation."
        echo $create_db
        return 1
    fi

    create_user="$( mysql --user=${user} --password=${password} --host=${host} --execute="CREATE USER '${OPTIONS_ANS["db_username"]}'@'${OPTIONS_ANS["db_user_remote_permission"]}' IDENTIFIED WITH mysql_native_password BY '${OPTIONS_ANS["db_password"]}';" )"
    create_user_success=$?
    if [ $create_user_success == 1 ];then
        echo "${ERRTXT}Error during user creation."
        echo $create_user
        return 1
    fi

    grant_privs="$( mysql --user=${user} --password=${password} --host=${host} --execute="GRANT ALL PRIVILEGES ON ${OPTIONS_ANS["db_name"]} . * TO '${OPTIONS_ANS["db_username"]}'@'${OPTIONS_ANS["db_user_remote_permission"]}';" )"
    grant_privs_success=$?
    if [ $grant_privs_success == 1 ];then
        echo "${ERRTXT}Error during user grant setting."
        echo $grant_privs
        return 1
    fi
    
    mysql --user=${user} --password=${password} --host=${host} --execute="FLUSH PRIVILEGES;" > /dev/null 2>&1

    #$1 = dbname, $2 = user, $3 = password, $4 = host, $5 = (DB should exist: 0, DB should not exist: 1)
    check_db_exists ${OPTIONS_ANS["db_name"]} ${OPTIONS_ANS["db_username"]} ${OPTIONS_ANS["db_password"]} ${OPTIONS_ANS["db_host"]} 0  
    db_exists=$?

    if [ $db_exists == 0 ];then
        echo "${SUCTXT}New database ${OPTIONS_ANS["db_name"]} existing."
        echo "--------------------------------------------------------------------------------------------"
        echo "${INFTXT}${BOLD}Database login information${NOCOLOR}"
        echo "--------------------------------------------------------------------------------------------"
        echo "${INFTXT}DB NAME: ${OPTIONS_ANS["db_name"]}."
        echo "${INFTXT}DB USER: ${OPTIONS_ANS["db_username"]}."
        echo "${INFTXT}DB Password: ${OPTIONS_ANS["db_password"]}."
        echo "${INFTXT}DB Host: ${OPTIONS_ANS["db_host"]}."
        read_answer "y" "Please save the above stated information to a safe place. Done?"
    else
        echo "${ERRTXT}The new database is not existing. Please try again. If this error was shown again, please get in touch with us."
    fi

    return $db_exists    
}

set_directory_permissions(){
    echo "============================================================================================"
    echo "${TASK}Setting file permissions."
    echo "============================================================================================"
    working_dir="$( pwd )"
    echo "${INFTXT}Working dir: $working_dir"
    sudo chown www-data. ${working_dir}/ -R > /dev/null 2>&1
    set_user=$?
    sudo chmod 770 ${working_dir}/ -R > /dev/null 2>&1
    set_chmod=$?

    if [ $set_chmod == 0 ] && [ $set_user == 0 ];then
        echo "${SUCTXT}Successfully set new permissions to ${working_dir}."
    else
        echo "${ERRTXT}Could not set new permissions to ${working_dir}."
    fi

    return $set_permissions
}

show_installation_summary(){
    echo "============================================================================================"
    echo "${TASK}Installation summary."
    echo "============================================================================================"
    echo "${SUCTXT}Congratulations! The first steps to use Chia(R)-Manager are now done!"
    echo "${INFTXT}Please open your browser now and type in https://${OPTIONS_ANS["dns_name"]}."
    echo "${INFTXT}This should now open the Chia(R)-Manager installer."
    echo "${INFTXT}The installer should be really straight forward to use. But here are the information you need to setup Chia(R)-Manager again:"
    echo "${INFTXT}DB NAME: ${OPTIONS_ANS["db_name"]}."
    echo "${INFTXT}DB USER: ${OPTIONS_ANS["db_username"]}."
    echo "${INFTXT}DB Password: ${OPTIONS_ANS["db_password"]}."
    echo "${INFTXT}DB Host: ${OPTIONS_ANS["db_host"]}."
    echo "${INFTXT}Websocket listening dir: ${OPTIONS_ANS["local_wss_listening_dir"]}."
    echo "${INFTXT}Websocket listening port: ${OPTIONS_ANS["local_wss_port"]}."
    echo "============================================================================================"
    echo "${INFTXT}${GREEN}Thank you for using Chia(R)-Manager.${NOCOLOR}"
    echo "============================================================================================"
    echo "${INFTXT}Bye."
}

if [ $( whoami ) != "root" ];then
    echo "${INFTXT}We need to be root to be able install all components."
    echo "${INFTXT}Please use 'sudo ./cmm.sh install'."
    exit
fi

echo "${INFTXT}${YELLOW}The install script will make changes to your system.$NOCOLOR"
echo "${INFTXT}The following changes will be applied:"
echo "${INFTXT}Installing system components apache2 (and needed modules), mysql-server, php (and needed modules)"
check_system
system_passed=$?
if [ $system_passed == 1 ];then
    exit 1
fi

read -p "${QUESTION}Do you want to proceed[y|Y = Yes]? "
if [[ $REPLY =~ ^[Yy]$ ]]; then
    configure_new_install
    configured=$?
    proceed_installation=1
    if [ $configured == 0 ];then
        echo "${INFTXT}Ok hanging on."
        echo "${INFTXT}${RED}Please do not quit this script from now on. If you want to cancel anyway, you can do it now.${NOCOLOR}"
        read -p "${QUESTION}Do you really want to proceed[y|Y = Yes]? " yn
        if [[ $yn =~ ^[Yy]$ ]]; then
            proceed_installation=0
        fi
    fi

    if [ $proceed_installation == 1 ];then
        exit 1
    fi

    install_system_modules
    install_status=$?
    if [ $install_status == 1 ];then
        exit 1
    fi
    enable_services_and_apache_modules
    service_enable_status=$?
    if [ $service_enable_status == 1 ];then
        exit 1
    fi

    create_directories
    dirs_created=$?
    if [ $dirs_created == 1 ];then
        exit 1
    fi

    setup_vhost
    vhost_setup_status=$?
    if [ $vhost_setup_status == 1 ];then
        exit 1
    fi

    set_directory_permissions
    permissions_set=$?
    if [ $permissions_set == 1 ];then
        exit 1
    fi

    setup_mysql_database
    mysql_setup_status=$?
    if [ $mysql_setup_status == 1 ];then
        exit 1
    fi

    generate_certs
    certs_generated=$?
    if [ $certs_generated == 1 ];then
        exit 1
    fi

    show_installation_summary
fi
