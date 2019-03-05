# Roundcube docker deployment

Docker container deployment project for [roundcube](https://roundcube.net/) web mail.

Do not use this project blindfold, but make sure you understand docker, the traditional 
roundcube deployment and the content of this project.

The docker image requires a volume mounted at `/data`. The following directories will 
be created at startup. The corresponding directory in the roundcube deployment directory 
are liked to those. 

- `/data/config`
- `/data/logs`
- `/data/temp`

The directories are populated with the files from the roundcube distribution. 

If there is no config file (`/data/config/config.inc.php`), roundcube will redirect 
to the installer. Run the installer to the end to get the content for a config file.
Create this file in the data directory. Refer to your kubernetes vendor documentation 
for access to the files from outside the container.

On container start up, the installer directory will be removed automatically, if
the config file exists. The installer directory is part of the image, but will not
be visible anymore. The official install guide suggests to delete the installer, 
when not needed to increase security. 

The logs directory should not be used, if the docker best practices to log to stdout 
are followed. 

This can be configured in the config file:

```php
$config['log_driver'] = 'stdout';
```

The default config uses `install_dir/temp` to store uploaded attachments. In the 
container image this is symlinked to the temp folder on the data volume, which
means there is no need to change the config. 

However to scale roundcube to multiple replicas, make sure to use a volume with 
`ReadWriteMany` access capability. This is required anyway, for the database cleaning 
cron job.

The file `/data/database_version` will be created at start up to store the current
roundcube version. This is required for the update script, which is called on each start 
up. Do not modify this file by hand, unless you know for sure your database schema 
is actually at his specific version. This also means **updating the database** is 
handled automatically on each container start up.

# Building the image

Use the script `./bin/docker-build.sh` to build the image. 
Adapt the version variable and commit. The Dockerfile uses build arguments
to download the wanted version of roundcube and the certificate of the CA issuing 
the certificates for the SMTP and IMAP servers. By default the certificate is 
downloaded from my site.

# Reference links

- https://roundcube.net/
- https://github.com/roundcube/roundcubemail/releases
- https://github.com/roundcube/roundcubemail/wiki/Installation
- https://github.com/roundcube/roundcubemail/blob/master/INSTALL
- https://github.com/roundcube/roundcubemail/blob/master/bin/installto.sh
- https://github.com/roundcube/roundcubemail/blob/master/bin/update.sh
- https://github.com/roundcube/roundcubemail/wiki/Configuration%3A-Load-balanced-Setup
- https://github.com/roundcube/roundcubemail/blob/master/config/defaults.inc.php
