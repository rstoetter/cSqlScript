#!/bin/sh

DATUMSBLOCK=$(date "+%Y-%m-%d-%H-%M")
STARTTIME=$(date +%s)

time nice ./phpdoc.sh --config phpdoc-cSqlScript-php.cfg -p --quiet $*

ENDTIME=$(date +%s)
secs=$(($ENDTIME - $STARTTIME))
printf 'Elapsed Time %dh:%dm:%ds\n' $(($secs/3600)) $(($secs%3600/60)) $(($secs%60))

for i in "$@" ; do
    if [[ $i == "no-browser" ]] ; then
        NO_BROWSER=1
        break
    fi
done

if [ -z "$NO_BROWSER" ]; then
    chromium phpDocumentor.xref/index.html &
fi

sleep 5



