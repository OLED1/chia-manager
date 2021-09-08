# chia-web-gui
## Branch: Dev, Introduction
This is the dev branch of the Chia Mgmt Gui. It is primary made for developers.
So if you want to decide to install this branch be always afraid of untested content and bugs.

This project is made for Chia Farmer.
The main goal is to make monitoring and managing the chia infrastructure and the needed nodes more efficient and easy.

### Why should i use it?
If you have a mid sized or large chia infrastructure which is heavy to monitor and you want to be always sure everythings is working fine and every node makes his work you are good to go.

If you have a small sized chia infrastructure you can use it too.
Some future features like autoplotting might be interesting for you.

Furthermore this project (will) offer(s) nearly everthing like the chia electron gui.
Read more in the section "features" and "upcomming features".

### What can i do with it?
###Monitoring
You are able to monitor the upstatus and service of every node.
Every node will be checked if it is up running and if the (farmer/harvester/wallet/etc.) service is working correctly.

###Managing
At the moment you are able to restart a certain service if it is not running correctly right from the webgui.

###Overview
Currently you are able to view the following read-only information partially reclined on the chia electron gui:
- Node system information (Filesystems, RAM and SWAP information and System Load)
- Wallet Information (Syncstatus, Owning XCH, etc.)
- Farm Information (Farminstatus and every corresponding information)
- Harvester Information (Configures Plot directories and used space, Plots you are currently owning)
- Many more features will be offered in future realeses.

##How does this project work? - From where does the webgui get the data?
This project consists of two mainparts. The so-called node-client and the web-gui.
To be able to query current data from your infrastructure you need to install the python based node-client on every node in your infrastructure. This node-client will connect to the api via a bidirectional websocket connection.

Currently all node data will be queried from the websocket server, but should be changed in one of the upcoming versions.
In future the node client will automatically detect changes and informs the web-gui.
So every data you will see are real live data.

The web-gui should always show up-to-date and live queried data, if available.
We are working hard to make a real live web based application.

##WTF?! Why only linux?
We developers have only linux desctops and we decided to make the really first version of the node-client only for linux.
Otherwise we were not able to release the first version of this project just in time.

But it is planning, promised!

The webgui should run on a linux server anyway.

##Current features
- Almost read only management via webgui
  - Only a certain service restart is possible at the moment
  - Dashboard
    - System and Security
    - Current Chia Netspace and XCH Price (queried from an up-to-date external api)
    - Wallet(s) overall information like Node/Service stats, Sync status, etc.
    - Farm overview like Node/Service stats, Farming Status, Plot count, and total size
    - Harvester overview like Node/service stats and a list of not mounted directories
  - Nodes Page
    - Manage your Chia Nodes. Accept and deny node requests. Update the node client script fully automatically.
  - Infra Sysinfo
    - Get the latest available system information from you host systems. Filesystem spaces, RAM and SWAP, Load information


##Upcoming features
- Full management via webgui
  - Update and restart the host system
  - Update your chia version fully automatically
  - Send and receive money via second factor
  - Detect and check your plots periodically
  - Manage windows systems too
  - ...

- Complete monitoring
 - Get an email if a service or server is not running or recently died
 - be able to detect immediately if you might lose money
 - ...

- Full autoplotting with madmax plotter integration
  - Select an empty or not fully plotted directory and let chiamgmt autoplot all your directories
  - ...

- Security at its best
 - Second factor via ubikey, two factor app, etc.
 - ...

- And further more!
- ...

Do you have any further ideas of features you want to see? Just make a feature request or mail us!

###Roadmap


##We need you!
Currently we are two contributors. Lucaaust, an occupational python programmer and me, a hobby php programmer.

You can help us out with testing and if you want with code contribution.
We want to offer a free, stable and overall chia management software.

##Installation and Usage
###Installation
At first you need a self hosted system where you can run the php based webgui and the needed mysql database.

####Install PHP
At least PHP 7.4 is needed to run the webuig. PHP 8 is currently not supported/tested.

#####Debian based systems (Ubuntu 20.04)
This application is currently tested on Ubuntu 20.04 LTS.
```
apt-get install apt install php php-cli php-fpm php-json php-common php-mysql php-zip php-gd php-mbstring php-curl php-xml php-pear php-bcmath
```

####Setup a Mysql database
```
mysql -u root -p
CREATE DATABASE chiamgmt_db;
CREATE USER 'chiamgmt_user'@'localhost' IDENTIFIED WITH mysql_native_password BY 'password';
GRANT ALL PRIVILEGES ON chiamgmt_db TO 'chiamgmt_user'@'localhost';
FLUSH PRIVILEGES;
```

####Setup a vhost
You can download an example vhost config for apache from here:
https://files.chiamgmt.edtmair.at/server/Example_Configs/

####Download the installer
```
cd /path/to/your/desired/directory

Download you desired package install package from
https://files.chiamgmt.edtmair.at/server/install-packages/

unzip chia-web-gui-dev
mv chia-web-gui-dev/installer.php /path/to/your/desired/directory
rm -rf chia-web-gui-dev*
```

####Execute the installer
Go to https://your.chiamgmtwebgui.com/installer.php and follow the steps.
After successfull instalation do not forget to remove the installer.php - file.

###Disclaimer
CHIA NETWORK INC, CHIA™, the CHIA BLOCKCHAIN™, the CHIA PROTOCOL™, CHIALISP™ and the “leaf Logo” (including the leaf logo alone when it refers to or indicates Chia), are trademarks or registered trademarks of Chia Network, Inc., a Delaware corporation. There is no affliation between this Chia Mgmt project and the main Chia Network project.