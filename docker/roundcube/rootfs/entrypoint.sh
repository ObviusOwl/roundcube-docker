#!/bin/bash
set -e

RC_DIR="/var/www/html"
UPST_CNF_DIR="/usr/local/lib/roundcube/config"
UPST_LOG_DIR="/usr/local/lib/roundcube/logs"
UPST_TMP_DIR="/usr/local/lib/roundcube/temp"
INSTALLER_DIR="$RC_DIR/installer"

DATA_DIR="/data"
CNF_DIR="$DATA_DIR/config"
LOG_DIR="$DATA_DIR/logs"
TMP_DIR="$DATA_DIR/temp"
DB_VER_FLE="$DATA_DIR/database_version"
CNF_FLE="$CNF_DIR/config.inc.php"

# parse_rc_version iniset.php
function parse_rc_version {
    # see also https://github.com/roundcube/roundcubemail/blob/master/bin/installto.sh
    perl -ne 'print $1 if /define\(.RCMAIL_VERSION.,\s*.([0-9.]+[a-z-]*).\)/' < "$1"
}

# copy_conf_file source dest
function copy_conf_file {
    if [ ! -f "$2" ]; then
        echo "* Creating file '$2'"
        cp "$1" "$2"
    fi
}
# copy_conf_file2 source dest
function copy_conf_file2 {
    echo "* Creating file '$2'"
    cp "$1" "$2"
}


# Create data dirs on the volume
mkdir -p "$CNF_DIR" "$LOG_DIR" "$TMP_DIR"

# Copy bundled config dir file by file
# see also https://github.com/roundcube/roundcubemail/blob/master/bin/installto.sh
copy_conf_file "$UPST_CNF_DIR/.htaccess" "$CNF_DIR/.htaccess"
copy_conf_file "$UPST_CNF_DIR/mimetypes.php" "$CNF_DIR/mimetypes.php"
copy_conf_file "$UPST_CNF_DIR/config.inc.php.sample" "$CNF_DIR/config.inc.php.sample"

# always copy latest defaults settings and shipped htaccess files
copy_conf_file2 "$UPST_CNF_DIR/defaults.inc.php" "$CNF_DIR/defaults.inc.php" 
copy_conf_file2 "$UPST_LOG_DIR/.htaccess" "$LOG_DIR/.htaccess"
copy_conf_file2 "$UPST_TMP_DIR/.htaccess" "$TMP_DIR/.htaccess"

# remove the installer dir, if we have a config file
# see also https://github.com/roundcube/roundcubemail/wiki/Installation
if [ -f "$CNF_FLE" ]; then
    echo "* Removing installer files."
    find "$INSTALLER_DIR" -mindepth 1 -delete
    if [ -n "$(ls -A $INSTALLER_DIR)" ]; then
        echo "* Fatal error: Installer directory is still not empty."
        exit 1
    fi
fi

# fetch previous version, if possible, else use current version
# see also https://github.com/roundcube/roundcubemail/blob/master/bin/update.sh
# where the user is asked for the version and if he does not know the current version is used
if [ -f "$DB_VER_FLE" ]; then
    OLD_VER=$( cat "$DB_VER_FLE" )
    if echo "$OLD_VER" | grep -q -v -E "[0-9.]+[a-z-]*" ; then 
        echo "* File '$DB_VER_FLE' does not contain a valid version number!";
        exit 1;
    fi
else
    OLD_VER=$( parse_rc_version "$RC_DIR/program/include/iniset.php" )
fi

# do database updates and config check
echo "* Updating from version $OLD_VER"
/usr/bin/php "$RC_DIR/bin/update.sh" --version="$OLD_VER" --accept=true

# save current version for later updates
parse_rc_version "$RC_DIR/program/include/iniset.php" > "$DB_VER_FLE"

# run apache httpd
echo "* Starting web server"
exec /usr/sbin/apache2ctl -DFOREGROUND
