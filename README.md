php-mysql-ssh
=============

PHP class for running SQL Queries on remote MySQL Databases over an SSH tunnel 
@requires: openssl and SSH2 PHP modules to be installed on server

@usage:
$mysql = new SSHMysql(...server creds...);
$result = $mysql->query(...sql string...);

@return stdClass object containing query results