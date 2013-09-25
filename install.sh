#!/bin/sh
# install base packages needed for building Asterisk
[ `whoami` != 'root' ] && sudo $0 && exit

# assuming CentOS for now
yum -y install php make wget openssl-devel ncurses-devel newt-devel libxml2-devel kernel-devel gcc gcc-c++ sqlite-devel libuuid-devel gtk2-devel
