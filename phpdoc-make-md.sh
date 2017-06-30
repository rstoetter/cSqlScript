#!/bin/sh

DATUMSBLOCK=$(date "+%Y-%m-%d-%H-%M")
STARTTIME=$(date +%s)

mkdir -p phpDocumentor.xref/md-files.dev
mkdir -p phpDocumentor.xref/md-files.usr

rm phpDocumentor.xref/md-files.usr/*
rm phpDocumentor.xref/md-files.dev/*

export NO_BROWSER=1
time nice ./phpdoc-cSqlScript-php.sh --template="xml"

time nice ./phpdocmd.sh phpDocumentor.xref/structure.xml phpDocumentor.xref/md-files.usr/ --sort-index --sort-see --level component --private-off --protected-off $*
time nice ./phpdocmd.sh phpDocumentor.xref/structure.xml phpDocumentor.xref/md-files.dev/ --sort-index --sort-see --level component --public-off --index DevApiIndex.md  $*

# export include_path=./misc/phpdoc-md/src

ENDTIME=$(date +%s)
secs=$(($ENDTIME - $STARTTIME))
printf 'Elapsed Time %dh:%dm:%ds\n' $(($secs/3600)) $(($secs%3600/60)) $(($secs%60))

# chromium phpDocumentor.xref/index.html &

sleep 5



