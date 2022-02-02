# chia-web-gui
## Branch: Dev, Introduction
This is the dev branch of the **Chia® Manager Web-GUI**. This branch is primary made for **developers**.
So if you want to decide to install this branch be always afraid of **untested content** and **bugs**.

This project is made for **Chia® Farmer** . Please visit https://www.chia.net/ get more information about Chia®.
The main goal is to make monitoring and managing the Chia® Infrastructure and the needed nodes more **efficient and easy**.

### Why should i use it?
This project should make it more easy to use, manage and monitor your Chia® Nodes and to be sure every node makes his work and you are good to go.

Some of the upcoming feature like autoplotting via Madmax plotter will excite you.

Furthermore this project (will) offer(s) nearly everything like the Chia® electron gui.
Read more in the following sections.

### What can i do with it?
### Monitoring
You are able to monitor the **up-status** and **service(s)** of every node.
Every node will be checked if it is up running and if the (farmer/harvester/wallet/etc.) service is working correctly.

### Managing
At the moment you are able to restart a certain service if it is not running correctly right from the webgui.

### Overview
Currently you are able to view the following read-only information partially reclined on the Chia® electron gui:
- Node system information (Filesystems, RAM and SWAP information and System Load)
- Wallet Information (Syncstatus, Owning XCH, etc.)
- Farm Information (Farminstatus and every corresponding information)
- Harvester Information (Configured Plot directories and used space, Plots you are currently owning)
- Many more features will be offered in future releases.

## How does this project work? - From where does the webgui get the data?
This project consists of two main parts. The so-called **node-client** and the **Web-GUI**.
The **node-client** gets his data by using the locally running socalled RPC Api, which requires the services locally available.
**No electron GUI needed!**
To be able to query current data from your infrastructure you need to install the python based node-client on every node in your infrastructure. This node-client will connect to the API which is offered through a **bidirectional and encrypted web-socket** connection.

Currently all node data will be queried from the web-socket server, but should be changed in one of the upcoming versions.
In future the node client will **automatically** detect changes and informs the web-gui with new data.
So every data you will see are **real live** data.

The Web-GUI should always show up-to-date and live queried data, if available.
We are working hard to make a real live web based application.

## WTF?! Why only Linux?
We developers are having only Linux desktops currently running and we decided to make the really first version of the node-client only for Linux.
Otherwise we were not able to release the first version of this project just in time.

But it is in planning, promised!

The web-gui should run on a Linux server anyway as any other web based application.

## Current (key)features
- Almost read only management via web-gui
  - Only a certain service restart is possible at the moment
  - Dashboard
    - System and Security
    - Current Chia® Netspace and XCH Price (queried from an up-to-date external api)
    - Wallet(s) overall information like Node/Service stats, Sync status, etc.
    - Farm overview like Node/Service stats, Farming Status, Plot count, and total size
    - Harvester overview like Node/service stats and a list of not mounted directories
    - Your assets are converted into your preferred currency
  - Nodes Page
    - Manage your Chia® Nodes. Accept and deny node requests.
  - Infra Sysinfo
    - Get the latest available system information from you host systems. **Filesystem spaces**, **RAM and SWAP** and **Load information**.
  - Wallet
    - All available wallets and information in one view inclusive all past transactions
  - Farmer
    - Get all available information about your farm like **Farmingstatus**, **XCH Block Rewards** and so on.
  - Harvester
    - A brief overview of your configured plotting directories and used capacity. A list of all found plots.

  See full feature list here: [Chia® Manager Documentation](https://docs.chia-manager.com/features/features).


## Upcoming features
- Full management via webgui
  - Update and restart the host system
  - Update your Chia® version fully automatically
  - Detect and check your plots periodically
  - Manage MS Windows systems too
- Complete monitoring
  - Get an email if a service or server is not running or recently died
  - Be able to detect immediately if you might lose money
  - Setup and manage different alerting levels to be fully informed just in moment something unexpected is happening
- Full autoplotting with madmax plotter integration
  - Select an empty or not fully plotted directory and let Chia® Manager **autoplot** all your directories
- And much more!

Do you have any further ideas of features you want to see? Just make a feature request or mail us!

### Roadmap
Please see our documentation at [Chia® Manager Documentation](https://docs.chia-manager.com/features/roadmap)

If the feature list stays as long as currently, version 1.0 might be reached much faster.
Version 1.0 will be released if every planned main features are implemented.

## We need your help!
Currently we are two contributors. **Lucaaust**, an occupational python programmer and **OLED1**, an occupational Linux Sysadmin and hobby programmer.

You can help us out with testing and if you want with code contribution.
We want to offer a free, open source and stable Chia® Farm Management Software.

## Installation and Usage
### Installation
Please see our documentation at [Chia® Manager Documentation](https://docs.chia-manager.com/administrator-documentation)

#### Node client Installation
Just follow the instructions in the Web-GUI under the point "Nodes".
Select your desired system OS. (Only Linux is currently supported)

### Disclaimer
CHIA NETWORK INC, CHIA™, the CHIA BLOCKCHAIN™, the CHIA PROTOCOL™, CHIALISP™ and the “leaf Logo” (including the leaf logo alone when it refers to or indicates Chia), are trademarks or registered trademarks of Chia Network, Inc., a Delaware corporation. There is no affiliation between this project and the main Chia Network project.
