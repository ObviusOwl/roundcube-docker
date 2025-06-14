#!/bin/bash
set -e

# roundcube version 
# use the git tag from
# https://github.com/roundcube/roundcubemail/releases
RC_VERSION="1.6.11"

# image revision for multiple builds per day
REV="1"

IMG_TAG="$RC_VERSION-`date +%Y%m%d`-$REV"
RC_IMG="reg.lan.terhaak.de/jojo/roundcube:$IMG_TAG"
DH_IMG="obviusowl/roundcube:$IMG_TAG"

sudo docker build --pull -t "$RC_IMG" -t "$DH_IMG" \
  --build-arg RC_VERSION="$RC_VERSION" \
  ./docker/roundcube

