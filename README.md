# Roundcube docker deployment

Docker container deployment project for [roundcube](https://roundcube.net/) web mail.

Do not use this project blindfold, but make sure you understand docker, the traditional 
roundcube deployment and the content of this project.

The docker image requires a volume mounted at `/data`. The following directories will 
be created at startup:

- `/data/config`
- `/data/logs`
- `/data/temp`

Set the environment variable `ENABLE_INSTALLER` to "yes" to enable the roundcube 
installer. Complete the installer and save the configuration file. Do not forget 
to initialize the database.

**Disable the installer** once done, the installer will leak all sensitive 
informations if it is not disabled in the config file. As a precaution unset 
the environement variable too so that the installer will be deleted on container 
start up.

The configuration file is saved in `/data/config/config.inc.php` and can be 
edited from outside the container.

The start up script runs automatically the roundcube update script.

The container crashes on start up if the database is not available. If started 
at the same time as the database container, the roundcube container is expected to 
restart until the database is ready.

Only the MySQL database driver is installed, other drivers can be installed by 
extending the Dockerfile. Similarly, only English and German spellchecking is installed. 


# Technical Details

Roundcube is installed into `/roundcube`. Only the subdirectories `installer` and
`config` are writable. The directories `/data/logs` and `/data/temp` are symlinked
to the correspondent directories in the roundcube installation. `temp` is used 
to store uploads.

The start up script removes the content of `/roundcube/installer` if the environment 
variable `ENABLE_INSTALLER` is not set to "yes".

If the config file `/data/config/config.inc.php` exists it is symlinked into 
`/roundcube/config` so that roundcube can use it.

The installer writes the config file to `/roundcube/config/config.inc.php`, 
roundcube is patched to move the file into `/data/config` and create the symlink 
right after writing the file.

If the file `/data/config/mimetypes.php` exists it is symlinked to 
`/roundcube/config/mimetypes.php` replacing the default one.

Roundcubes update script relies on input about the previous version number. 
If no input is given, roundcube checks the installation directory 
( `program/include/iniset.php` ), which however is part of the container's 
root image and thus is always the latest. The version is kept in the database 
and updated by the start up script. Oddly enough roundcube itself keeps already 
the database schema version number (different from the program version number) 
in the database. 

The database has a table named `system`. The program version is saved with the 
key `docker_app_version`, roundcube uses `roundcube-version` for the database schema.

The default log driver is patched to be "stdout" instead of "file". If a existing
config file is used, check the setting, as docker containers should not log to files.

The URL path `/.health` points to a html file containing only the word "OK". This
bypasses roundcube's session management and does not produce apache httpd log 
entries. Use this URL for health/readiness probes in kubernetes. 
It is still recommended to check the roundcube login page with a slower paced 
monitoring system (e.g. icinga) to also catch database problems.

Be careful with scaling the container to multiple instances, the start up script
is not tested with concurrency and probably will create race conditions.


# Building the image

Use the script `./bin/docker-build.sh` to build the image. 
Adapt the version variable and commit. 

The following build arguments are available:

- `RC_VERSION`: roundcube version to download from the [github releases page](https://github.com/roundcube/roundcubemail/releases)
- `MAIL_CA_URL`: URL to a certificate authority file to install into the image.

`RC_VERSION` is mandatory. `MAIL_CA_URL` defaults to my own CA used for the mail servers. 

# Reference links

- https://roundcube.net/
- https://github.com/roundcube/roundcubemail/releases
- https://github.com/roundcube/roundcubemail/wiki/Installation
- https://github.com/roundcube/roundcubemail/blob/master/INSTALL
- https://github.com/roundcube/roundcubemail/blob/master/bin/installto.sh
- https://github.com/roundcube/roundcubemail/blob/master/bin/update.sh
- https://github.com/roundcube/roundcubemail/wiki/Configuration%3A-Load-balanced-Setup
- https://github.com/roundcube/roundcubemail/blob/master/config/defaults.inc.php
