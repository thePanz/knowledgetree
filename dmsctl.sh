#!/bin/sh

# Boot KnowledgeTree services
# chkconfig: 2345 55 25
# description: KnowledgeTree Services
#
# processname: ktdms 

HOSTNAME=`hostname`
RETVAL=0
PID=""
ERROR=0
SERVER=all
VDISPLAY="99"
INSTALL_PATH=`pwd`
JAVABIN=/usr/bin/java
ZEND_DIR=/usr/local/zend

# OpenOffice
SOFFICEFILE=soffice
SOFFICE_PIDFILE=$INSTALL_PATH/var/log/soffice.bin.pid
SOFFICE_PID=""
SOFFICE_PORT="8100"
SOFFICEBIN=/usr/bin/soffice
SOFFICE="$SOFFICEBIN -nofirststartwizard -nologo -headless -accept=socket,host=127.0.0.1,port=$SOFFICE_PORT;urp;StarOffice.ServiceManager"
SOFFICE_STATUS=""

# Lucene
LUCENE_PIDFILE=$INSTALL_PATH/var/log/lucene.pid
LUCENE_PID=""
LUCENE="$JAVABIN -Xms512M -Xmx512M -jar ktlucene.jar"
LUCENE_STATUS=""

# Scheduler
SCHEDULER_PATH="$INSTALL_PATH/bin/"
SCHEDULER_PIDFILE=$INSTALL_PATH/var/log/scheduler.pid
SCHEDULER_PID=""
SCHEDULERBIN="$INSTALL_PATH/var/bin/schedulerTask.sh"
SCHEDULER="$SCHEDULERBIN"
SCHEDULER_STATUS=""

get_pid() {
    PID=""
    PIDFILE=$1
    # check for pidfile
    if [ -f $PIDFILE ] ; then
        exec 6<&0
        exec < $PIDFILE
        read pid
        PID=$pid
        exec 0<&6 6<&-
    fi
}

get_soffice_pid() {
    get_pid $SOFFICE_PIDFILE
    if [ ! $PID ]; then
        return 
    fi
    if [ $PID -gt 0 ]; then
        SOFFICE_PID=$PID
    fi
}

get_lucene_pid() {
    get_pid $LUCENE_PIDFILE
    if [ ! $PID ]; then
        return 
    fi
    if [ $PID -gt 0 ]; then
        LUCENE_PID=$PID
    fi
}

get_scheduler_pid() {
    get_pid $SCHEDULER_PIDFILE
    if [ ! $PID ]; then
        return 
    fi
    if [ $PID -gt 0 ]; then
        SCHEDULER_PID=$PID
    fi
}

is_service_running() {
    PID=$1
    if [ "x$PID" != "x" ] && kill -0 $PID 2>/dev/null ; then
        RUNNING=1
    else
        RUNNING=0
    fi
    return $RUNNING
}

is_soffice_running() {
    get_soffice_pid
    is_service_running $SOFFICE_PID
    RUNNING=$?
    if [ $RUNNING -eq 0 ]; then
        SOFFICE_STATUS="openoffice not running"
    else
        SOFFICE_STATUS="openoffice already running"
    fi
    return $RUNNING
}

is_lucene_running() {
    get_lucene_pid
    is_service_running $LUCENE_PID
    RUNNING=$?
    if [ $RUNNING -eq 0 ]; then
        LUCENE_STATUS="lucene not running"
    else
        LUCENE_STATUS="lucene already running"
    fi
    return $RUNNING
}

is_scheduler_running() {
    get_scheduler_pid
    is_service_running $SCHEDULER_PID
    RUNNING=$?
    if [ $RUNNING -eq 0 ]; then
        SCHEDULER_STATUS="scheduler not running"
    else
        SCHEDULER_STATUS="scheduler already running"
    fi
    return $RUNNING
}

start_soffice() {
    is_soffice_running
    RUNNING=$?

    if [ $RUNNING -eq 1 ]; then
        echo "$0 $ARG: openoffice (pid $SOFFICE_PID) already running"
    else
        nohup $SOFFICE &> $INSTALL_PATH/var/log/dmsctl.log &
        if [ $? -eq 0 ]; then
            echo "$0 $ARG: openoffice started at port $SOFFICE_PORT"
            ps ax | grep $SOFFICEBIN | awk {'print $1'} > $SOFFICE_PIDFILE
            sleep 2
        else
            echo "$0 $ARG: openoffice could not be started"
            ERROR=3
        fi
fi
}

stop_soffice() {
    NO_EXIT_ON_ERROR=$1
    is_soffice_running
    RUNNING=$?

    if [ $RUNNING -eq 0 ]; then
        echo "$0 $ARG: $SOFFICE_STATUS"
        if [ "x$NO_EXIT_ON_ERROR" != "xno_exit" ]; then
            exit
        else
            return
        fi
    fi
    get_soffice_pid
	if killall $SOFFICEFILE; then
	    echo "$0 $ARG: openoffice stopped"
	else
	    echo "$0 $ARG: openoffice could not be stopped"
	    ERROR=4
	fi
}

start_lucene() {
    is_lucene_running
    RUNNING=$?

    if [ $RUNNING -eq 1 ]; then
        echo "$0 $ARG: lucene (pid $LUCENE_PID) already running"
    else
        cd $INSTALL_PATH/bin/luceneserver
        nohup $LUCENE  &> $INSTALL_PATH/var/log/dmsctl.log &
        if [ $? -eq 0 ]; then
            echo "$0 $ARG: lucene started"
            ps ax | grep ktlucene.jar | awk {'print $1'} > $LUCENE_PIDFILE
            sleep 2
        else
            echo "$0 $ARG: lucene could not be started"
            ERROR=3
        fi
        cd $INSTALL_PATH
fi
}

stop_lucene() {
    NO_EXIT_ON_ERROR=$1
    is_lucene_running
    RUNNING=$?

    if [ $RUNNING -eq 0 ]; then
        echo "$0 $ARG: $LUCENE_STATUS"
        if [ "x$NO_EXIT_ON_ERROR" != "xno_exit" ]; then
            exit
        else
            return
        fi
	fi
    get_lucene_pid
    cd $INSTALL_PATH/search2/indexing/bin
    $ZEND_DIR/bin/php shutdown.php positive &> $INSTALL_PATH/var/log/dmsctl.log
    sleep 5
    if [ $? -eq 0 ]; then
	    echo "$0 $ARG: lucene stopped"
	else
	    echo "$0 $ARG: lucene could not be stopped"
	    ERROR=4
	fi
}

start_scheduler() {
    is_scheduler_running
    RUNNING=$?

    if [ $RUNNING -eq 1 ]; then
        echo "$0 $ARG: scheduler (pid $SCHEDULER_PID) already running"
    else
        cd $SCHEDULER_PATH
        nohup $SCHEDULER  &> $INSTALL_PATH/var/log/dmsctl.log &
        if [ $? -eq 0 ]; then
            echo "$0 $ARG: scheduler started"
            ps ax | grep $SCHEDULERBIN | awk {'print $1'} > $SCHEDULER_PIDFILE
            sleep 2
        else
            echo "$0 $ARG: scheduler could not be started"
            ERROR=3
        fi
    fi
}

stop_scheduler() {
    NO_EXIT_ON_ERROR=$1
    is_scheduler_running
    RUNNING=$?

    if [ $RUNNING -eq 0 ]; then
        echo "$0 $ARG: $SCHEDULER_STATUS"
        if [ "x$NO_EXIT_ON_ERROR" != "xno_exit" ]; then
            exit
        else
            return
        fi
	fi
    get_scheduler_pid
	if kill $SCHEDULER_PID ; then
	    echo "$0 $ARG: scheduler stopped"
	else
	    echo "$0 $ARG: scheduler could not be stopped"
	    ERROR=4
	fi
}

help() {
	echo "usage: $0 help"
	echo "       $0 (start|stop|restart)"
	echo "       $0 (start|stop|restart) scheduler"
	echo "       $0 (start|stop|restart) soffice"
	echo "       $0 (start|stop|restart) lucene"
	cat <<EOF

help       - this screen
start      - start the service(s)
stop       - stop  the service(s)
restart    - restart or start the service(s)

EOF
exit 0
}

noserver() {
       echo -e "ERROR: $1 is not a valid server. Please, select 'scheduler', 'soffice' or 'lucene'\n"
       help
}

[ $# -lt 1 ] && help

if [ ! -z ${2} ]; then
       [ "${2}" != "mysql" ] && [ "${2}" != "apache" ] && [ "${2}" != "agent" ] && [ "${2}" != "scheduler" ] && [ "${2}" != "soffice" ] && [ "${2}" != "lucene" ] && noserver $2
       SERVER=$2
fi
       

if [ "x$3" != "x" ]; then
    MYSQL_PASSWORD=$3
fi


case $1 in
       help)   help
               ;;
       start)
               if [ "${SERVER}" != "all" ]; then
                       start_${2}
               else
                       start_soffice
                       start_lucene
                       start_scheduler
               fi
               ;;
       stop)   if [ "${SERVER}" != "all" ]; then
                       stop_${2}
               else
                       stop_scheduler "no_exit"
                       stop_lucene "no_exit"
                       stop_soffice "no_exit"
               fi
               ;;
       restart)        if [ "${SERVER}" != "all" ]; then
                               stop_${2} "no_exit"
                               sleep 2
                               start_${2}
                       else
                               stop_scheduler "no_exit"
                               stop_lucene "no_exit"
                               stop_soffice "no_exit"
                               start_soffice
                               start_lucene
                               start_scheduler
                       fi
               ;;
esac

exit $ERROR