#!/bin/bash
# crontab:
# 0 0 10 * * /home/lincon/src/lincon_crawler/cron.sh

echo "Please wait. This may take hours..."
BASEDIR=$(dirname $0)
echo "Started "
date
# Reset: clear all existing records
php init.php

# Crawl all prefectures
for i in {1..47}; do
    if [ $i -lt 10 ]
    then
        php $BASEDIR/exec.php "0$i"
    else
        php $BASEDIR/exec.php "$i"
    fi
    echo "Crawler: Done $i"
done
echo "Ended "
date