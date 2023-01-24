#!/bin/bash
#React/MySQL simple can't resolve the service name, so we need to add an entry to the hosts file
if [ ! -z "$CM_MYSQL_HOST" ];then ip=$(host $CM_MYSQL_HOST | awk '{ print $4 }'); echo $ip $CM_MYSQL_HOST >> /etc/hosts; fi
if [ ! -z "$CM_WEBSOCKET_DOCKER_HOST" ];then ip=$(host $CM_WEBSOCKET_DOCKER_HOST | awk '{ print $4 }'); echo $ip $CM_WEBSOCKET_DOCKER_HOST >> /etc/hosts; fi