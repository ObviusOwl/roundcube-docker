<?php
/*
    See also:
    - https://github.com/roundcube/roundcubemail/blob/master/bin/installto.sh
    - https://github.com/roundcube/roundcubemail/wiki/Installation
    - https://github.com/roundcube/roundcubemail/blob/master/program/include/iniset.php

    The previous version needs to be stored somewhere (database), as the install
    path always contains the new version and the update script needs the old version.
*/

define( "INSTALL_PATH", "/roundcube/" );
define( "DIST_PATH",    "/roundcube-dist/" );
define( "DATA_PATH",    "/data/" );

/**
    Loading Roundcube libs
*/

$include_path = INSTALL_PATH . 'program/lib' . PATH_SEPARATOR;
$include_path.= ini_get('include_path');
if (set_include_path($include_path) === false) {
    die("Fatal error: ini_set/set_include_path does not work.");
}
// include composer autoloader (if available)
if (@file_exists(INSTALL_PATH . 'vendor/autoload.php')) {
    require INSTALL_PATH . 'vendor/autoload.php';
}
// include Roundcube Framework
require_once 'Roundcube/bootstrap.php';


/**
    Run an external command
*/
function run_cmd( array $cmd, bool $exec=False ){
    if( $exec ){
        $ret = pcntl_exec( $cmd[0], array_slice( $cmd, 1 ) );
        if( $ret === False ){
            throw new Exception( "Failed to exec." );
        }
    }else{
        $cmd_esc = [];
        foreach( $cmd as $c ){
            $cmd_esc[] = escapeshellarg( $c );
        }
        $cmd_str = implode( " ", $cmd_esc );
        $ret = 0;
        system( $cmd_str, $ret );
        if( $ret !== 0 ){
            throw new Exception( "Command returned with non-zero code." );
        }
    }
}

/**
    Parse iniset.php for the Roundcube mail version
*/
function parse_version( string $file ){
    $cont = file_get_contents( $file );
    if( $cont === False ){
        throw new Exception( "Failed to read file: $file" );
    }
    $matches = [];
    $res = preg_match( "/define\s*\(\s*.RCMAIL_VERSION.\s*,\s*.([0-9.]+[a-z-]*).\s*\)/", $cont, $matches );
    if( $res !== 1 ){
        throw new Exception( "Roundcube version not found" );
    }
    return $matches[1];
}


/**
    Docker container start up routine
*/

echo "* Creating runtime files\n";

// create directories in /data
run_cmd( [ "/bin/mkdir", "-p", DATA_PATH."config", DATA_PATH."logs", DATA_PATH."temp" ] );

// copy htacces file from roundcube dist
run_cmd( [ "/bin/cp", DIST_PATH."logs/.htaccess", DATA_PATH."logs/.htaccess" ] );
run_cmd( [ "/bin/cp", DIST_PATH."temp/.htaccess", DATA_PATH."temp/.htaccess" ] );

// copy sample config file
run_cmd( [ "/bin/cp", INSTALL_PATH."config/config.inc.php.sample", DATA_PATH."config/" ] );

// override mimetype mappings
if( file_exists( DATA_PATH."config/mimetypes.php" ) ){
    run_cmd( [ "/bin/ln", "-sf", DATA_PATH."config/mimetypes.php", INSTALL_PATH."config/mimetypes.php" ] );
}

// user has created a config file
if( file_exists( DATA_PATH."config/config.inc.php" ) ){
    echo "* Loading the roundcube config file\n";

    // link the config file into roundcube's install dir
    run_cmd( [ "/bin/ln", "-sf", DATA_PATH."config/config.inc.php", INSTALL_PATH."config/config.inc.php" ] );

    // load the config    
    require_once INSTALL_PATH . "config/config.inc.php";
}

// remove the installer
if( getenv("ENABLE_INSTALLER") !== "yes" ){
    echo "* Removing installer files.\n";
    run_cmd( [ "/usr/bin/find", INSTALL_PATH."installer", "-mindepth", "1", "-delete" ] );    
}

// connect to the database
if( isset($config['db_dsnw']) ){
    echo "* Connecting to the database\n";

    $db = rcube_db::factory( $config['db_dsnw'] );
    $db->db_connect( 'w' );

    register_shutdown_function( function() use ($db){
        $db->closeConnection();
    } );

    if( ! $db->is_connected() ){
        die( "Failed to connect to database\n" );
    }
}

// parse packaged version
$new_rc_version = parse_version( INSTALL_PATH . "program/include/iniset.php" );

// load version from database
$rc_version_fromdb = False;
if( isset($db) ){
    $quer = $db->query( 'SELECT value FROM system WHERE name = "docker_app_version"' );
    $r = $db->fetch_assoc( $quer );
    if( $r !== False ){
        $rc_version = $r[1];
        $rc_version_fromdb = True;
    }
}

// fallback to current version
if( ! isset($rc_version) ){
    echo "Warning: Falling back to current version as previous version.\n";
    $rc_version = $new_rc_version;
}

// run the update script
echo "* Updating from $rc_version to $new_rc_version\n";
run_cmd( [ "/usr/bin/php", INSTALL_PATH . "bin/update.sh", "--version='$rc_version'", "--accept=true" ] );

// save the new version to the database
if( isset($db) ){
    echo "Saving current rcmail version to database\n";

    if( $rc_version_fromdb ){
        $quer = $db->query( 'UPDATE system SET value = ? WHERE name = "docker_app_version"', $new_rc_version );
    }else{
        $quer = $db->query( 'INSERT INTO system (name, value) VALUES ( "docker_app_version", ? )', $new_rc_version );
    }
    if( $db->is_error( $quer ) ){
        echo "Warning: failed to save current rcmail version to the database.\n";
    }    
    $db->closeConnection();
}

// run the web server replacing current process context
echo "* Starting the web server\n";
run_cmd( [ "/usr/sbin/apache2ctl", "-DFOREGROUND" ], True );
