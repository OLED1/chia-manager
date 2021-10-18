# chia-web-gui
## Branch: Dev, Introduction
This is the dev branch of the **Chia Mgmt Web-GUI**. This branch is primary made for **developers**.
So if you want to decide to install this branch be always afraid of **untested content** and **bugs**.

This project is made for **Chia Farmer**.
The main goal is to make monitoring and managing the Chia Infrastructure and the needed nodes more **efficient and easy**.

### Why should i use it?
This project should make it more easy to use, manage and monitor your Chia Nodes and to be sure every node makes his work and you are good to go.

Some of the upcoming feature like autoplotting via Madmax plotter will excite you.

Furthermore this project (will) offer(s) nearly everything like the chia electron gui.
Read more in the following sections.

### What can i do with it?
### Monitoring
You are able to monitor the **up-status** and **service(s)** of every node.
Every node will be checked if it is up running and if the (farmer/harvester/wallet/etc.) service is working correctly.

### Managing
At the moment you are able to restart a certain service if it is not running correctly right from the webgui.

### Overview
Currently you are able to view the following read-only information partially reclined on the chia electron gui:
- Node system information (Filesystems, RAM and SWAP information and System Load)
- Wallet Information (Syncstatus, Owning XCH, etc.)
- Farm Information (Farminstatus and every corresponding information)
- Harvester Information (Configures Plot directories and used space, Plots you are currently owning)
- Many more features will be offered in future releases.

## How does this project work? - From where does the webgui get the data?
This project consists of two main parts. The so-called **node-client** and the **Web-GUI**.
The **node-client** gets his data with the help of **Chia Blockchain CLI** and formats them for the Web-GUI.
**No electron GUI needed!**
To be able to query current data from your infrastructure you need to install the python based node-client on every node in your infrastructure. This node-client will connect to the API which is offered through a **bidirectional and encrypted web-socket** connection.

Currently all node data will be queried from the web-socket server, but should be changed in one of the upcoming versions.
In future the node client will **automatically** detect changes and informs the web-gui with new data.
So every data you will see are **real live** data.

The Web-GUI should always show up-to-date and live queried data, if available.
We are working hard to make a real live web based application.

## WTF?! Why only Linux?
We developers have only Linux desktops and we decided to make the really first version of the node-client only for Linux.
Otherwise we were not able to release the first version of this project just in time.

But it is planning, promised!

The web-gui should run on a Linux server anyway.

## Current (key)features
- Almost read only management via web-gui
  - Only a certain service restart is possible at the moment
  - Dashboard
    - System and Security
    - Current Chia Netspace and XCH Price (queried from an up-to-date external api)
    - Wallet(s) overall information like Node/Service stats, Sync status, etc.
    - Farm overview like Node/Service stats, Farming Status, Plot count, and total size
    - Harvester overview like Node/service stats and a list of not mounted directories
    - Your assets are converted into your preferred currency
  - Nodes Page
    - Manage your Chia Nodes. Accept and deny node requests. Update the node client script fully automatically.
  - Infra Sysinfo
    - Get the latest available system information from you host systems. **Filesystem spaces**, **RAM and SWAP** and **Load information**.
  - Wallet
    - All available wallets and information in one view inclusive all past transactions
  - Farmer
    - Get all available information about your farm like **Farmingstatus**, **XCH Block Rewards** and so on.
  - Harvester
    - A brief overview of your configured plotting directories and used capacity. A list of all found plots.


## Upcoming features
- Full management via webgui
  - Update and restart the host system
  - Update your chia version fully automatically
  - Send and receive money via the Web-GUI secured through a second factor
  - Detect and check your plots periodically
  - Manage Windows systems too
- Complete monitoring
  - Get an email if a service or server is not running or recently died
  - Be able to detect immediately if you might lose money
  - Setup and manage different alerting levels to be fully informed just in moment something unexpected is happening
- Full autoplotting with madmax plotter integration
  - Select an empty or not fully plotted directory and let Chia Mgmt **autoplot** all your directories
- Security
  - Second factor via ubikey, two factor app, etc.
- And much more!

Do you have any further ideas of features you want to see? Just make a feature request or mail us!

### Roadmap
- Version 0.2
  - Planned Date: 10/21
  - Planned Features:
    - Pool Data via "plotnft" command
    - Pool API Integration from pools which offers an open API
    - Second factor via ubikey, second factor app, etc.
    - Smart Node Client implementation
- Version 0.3
  - Planned Date: 11/21
  - Planned Features:
    - Extended Monitoring and messages via email
    - More charts which offers more past information
- Version 0.4
  - Planned Date: 12/21
  - Planned Features:
- Version 0.5
  - Planned Date: 01/22
  - Planned Features:
- Version 0.6
  - Planned Date: 02/22
  - Planned Features:
- Version 0.7
  - Planned Date: 03/22
  - Planned Features:
- Version 0.8
  - Planned Date: 04/22
  - Planned Features:
- Version 0.9
  - Planned Date: 05/22
  - Planned Features:
- Version 1.0
  - Planned Date: 06/22
  - Planned Features:

If the feature list stays as long as currently, version 1.0 might be reached much faster.
Version 1.0 will be released if every planned main features are implemented.

## We need your help!
Currently we are two contributors. **Lucaaust**, an occupational python programmer and **OLED1**, an occupational Linux Sysadmin and hobby programmer.

You can help us out with testing and if you want with code contribution.
We want to offer a free, open source and stable Chia Farm Management Software.

## Installation and Usage
### Installation
At first you need a self hosted system where you can run the PHP based Web-GUI and the needed Mysql database.

#### Install PHP
At least PHP 7.4 is needed to run the Web-GUI. PHP 8 is currently not supported/tested.

##### Debian based systems (Ubuntu 20.04)
This application is currently tested on Ubuntu 20.04 LTS.
```
apt install php php-common php-json php-mbstring php-igbinary php-tokenizer php-apcu php-readline php-sockets php-intl php-posix php-sysvmsg php-cli php-fpm
```

#### Setup a Mysql database
```
mysql -u root -p
CREATE DATABASE chiamgmt_db;
CREATE USER 'chiamgmt_user'@'localhost' IDENTIFIED WITH mysql_native_password BY 'password';
GRANT ALL PRIVILEGES ON chiamgmt_db TO 'chiamgmt_user'@'localhost';
FLUSH PRIVILEGES;
```

#### Setup a vhost
You can download an example Vhost config for apache webservers from here:
https://files.chiamgmt.edtmair.at/server/Example_Configs/

#### Create your folder structure
```
mkdir /path/to/your/desired/directory
cd /path/to/your/desired/directory

E.g.
mkdir /var/www/chiamgmt
cd /var/www/chiamgmt
```

#### Download the project files
```
wget https://github.com/OLED1/chia-web-gui/archive/refs/heads/dev.zip
unzip chia-web-gui-dev
```

#### Execute the installer
Go to https://your.chiamgmtwebgui.com/frontend/sites/installer_updater and follow the steps.
After successful installation do not forget to update the javascript nonce stated in the config file into your htaccess file. Otherwise no javascript can be loaded.

#### Node client Installation
Just follow the instructions in the Web-GUI under the point "Nodes".
Select your desired system OS. (Only Linux is currently supported)

### Disclaimer
CHIA NETWORK INC, CHIA™, the CHIA BLOCKCHAIN™, the CHIA PROTOCOL™, CHIALISP™ and the “leaf Logo” (including the leaf logo alone when it refers to or indicates Chia), are trademarks or registered trademarks of Chia Network, Inc., a Delaware corporation. There is no affiliation between this Chia Mgmt project and the main Chia Network project.
