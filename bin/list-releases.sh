#!/bin/bash
URL="https://api.github.com/repos/roundcube/roundcubemail/releases"
curl -s -H "Accept: application/vnd.github.v3+json" "$URL" | jq -r '.[].tag_name'
