#!/bin/bash
set -e

# medawiki version 
RC_VERSION="1.3.6"
# image revision for multiple builds per day
REV="1"

IMG_TAG="$RC_VERSION-`date +%Y%m%d`-$REV"
RC_IMG="reg.lan.terhaak.de/jojo/roundcube:$IMG_TAG"

sudo docker build -t "$RC_IMG" \
  --build-arg RC_VERSION="$RC_VERSION" \
  ./docker/roundcube
