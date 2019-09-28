#!/bin/bash
cd htdocs/avp/
php updateServerInfo.php >> /home/ubuntu/tmp/updateServer.log
php cleanupOperation.php >> /home/ubuntu/tmp/errorCleanup.log
