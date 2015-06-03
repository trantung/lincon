BASEDIR=$(dirname $0)
NOW=$(date +"%Y-%m-%d")
LOGFILE="$BASEDIR/log/${NOW}-stdout.log"
touch $LOGFILE
nohup $BASEDIR/cron.sh >> $LOGFILE &

# PID
echo $!