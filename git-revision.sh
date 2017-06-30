#!/bin/bash

# vorher muss ausgeführt werden:
# git tag Prod-1

revisioncount=`git log --oneline | wc -l`
projectversion=`git describe --tags --long`
cleanversion=${projectversion%%-*}

echo "$projectversion-$revisioncount"
# echo "$cleanversion.$revisioncount"
