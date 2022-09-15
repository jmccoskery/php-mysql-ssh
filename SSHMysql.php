<?php

        /********************************************************************************
         * @author jmccoskery
         * PHP class for running SQL Queries on remote MySQL Databases over an SSH connection
         * requires openssl and SSH2 PHP modules to be installed on server
         *
         * @usage:
         * $mysql = new SSHMysql(...server creds...);
         * $result = $mysql->query(...sql string...);
         ********************************************************************************/
        class SSHMysql
        {

                // variable definition(s)
                private $_server;


                /********************************************************************************
                 * @method __construct
                 * @param $server_sshipaddress
                 * @param $server_sshport
                 * @param $server_sshusername
                 * @param $server_sshpassword
                 * @param $server_mysqlipaddress
                 * @param $server_mysqlusername
                 * @param $server_mysqlpassword
                 * @param $server_mysqlport
                 *
                 * class construct, takes server credentials as parameters for use in subsequent
                 * queries
                 ********************************************************************************/
                function __construct( $server_sshipaddress, $server_sshport, $server_sshusername, $server_sshpassword, $server_mysqlipaddress, $server_mysqlusername, $server_mysqlpassword, $server_mysqlport )
                {
                        $this->_server = array();
                        $this->_server['sshipaddress'] = $server_sshipaddress;
                        $this->_server['sshport'] = $server_sshport;
                        $this->_server['sshusername'] = $server_sshusername;
                        $this->_server['sshpassword'] = $server_sshpassword;
                        $this->_server['mysqlipaddress'] = $server_mysqlipaddress;
                        $this->_server['mysqlusername'] = $server_mysqlusername;
                        $this->_server['mysqlpassword'] = $server_mysqlpassword;
                        $this->_server['mysqlport'] = $server_mysqlport;
                }

                /********************************************************************************
                 * @method query
                 * @param $sql
                 * @return stdObject
                 *
                 * executes the query on the mysql server, parses it and returns the results or
                 * error details
                 ********************************************************************************/
                public function query($sql)
                {
                        // if !ssh2_connect, exit because the SSH2 module is not installed for PHP //
                        if (function_exists("ssh2_connect")     )
                        {
                                $connection = ssh2_connect($this->_server['sshipaddress'], $this->_server['sshport']);

                                // if the SSH username and password are correct, try to run the query via ssh2_exec; if it's not correct return authentication failure to user //
                                if (ssh2_auth_password($connection, $this->_server['sshusername'], $this->_server['sshpassword'])) {

                                        // set up a shell script.  use port-forwarding over 3307 to tunnel into the remote database via SSH, this COULD CHANGE depending on your server's SSH configuration.  3307 is most common.//
                                        // clean up the SQL query so things like slashes, single quotes and double quotes don't cause errors //
                                        $ssh_query ='ssh -L 3307:'.$this->_server['sshipaddress'].':'.$this->_server['mysqlport'].'; echo "' . str_replace( '"', '\'', stripslashes( $sql ) ) . '" | mysql -u '.$this->_server['mysqlusername'].' -h '.$this->_server['mysqlipaddress'].' --password=\''.$this->_server['mysqlpassword'].'\'';

                                        // execute the query over a secure connection //
                                        $result = ssh2_exec($connection, $ssh_query);

                                        // catch any stream errors that might occur.  This will return the command line's MySQL errors to help with query debugging if there's an error in the SQL statement
                                        $error_result = ssh2_fetch_stream($result, SSH2_STREAM_STDERR);

                                        // turn on stream blocking to save the query results and errors to variables
                                        stream_set_blocking($result, true);
                                        stream_set_blocking($error_result, true);

                                        // DEBUG, print the sql result:
                                        // print_r( stream_get_contents($result) );
                                        // print_r( stream_get_contents($error_result) );

                                        // parse the sql query.  all results come back as strings within a standard, tab delimited format that can be split into result sets
                                        $arr_1 = explode( "\n", stream_get_contents($result) );

                                        $keys = explode( "\t", $arr_1[0] );  // get the column names
                                        $results = array();

                                        for($i=1;$i< ( sizeof($arr_1) -1 );$i++) // parse the results
                                        {
                                                $values = explode( "\t", $arr_1[$i] );
                                                $return = new stdClass;
                                                $index = 0;
                                                foreach( $values as $v )
                                                {
                                                        $return->{$keys[$index]} = $v;
                                                        $index++;
                                                }
                                                $results[] = $return;
                                        }


                                        if(sizeof($results) > 0)
                                        {
                                                return array('status'=>'success','msg'=>'DB Query was successful.', 'dataset'=>$results, 'type'=>'ssh');
                                        } else {
                                                return array('status'=>'error', 'msg'=>'There is an error in your SQL statement, or your sql returned no results', 'errorset'=>stream_get_contents($error_result), 'dataset'=>array(), 'type'=>'ssh');
                                        }

                                        // close the SSH tunnel
                                        fclose($result);
                                if ( function_exists ( 'ssh2_disconnect' ) ) {
                                ssh2_disconnect ( $connection );
                                } else { // if no disconnect func is available, close conn, unset var
                                fclose ( $connection );
                                $connection = false;
                                }

                                } else {
                                        return array('status'=>'error', 'msg'=>'SSH Authentication Failed because of a bad username or password. Please check the SSH authentication settings and try again.','dataset'=>array(), 'type'=>'ssh');
                                }
                        } else {
                                return array('status'=>'error', 'msg'=>'SSH Authentication Failed because SSH2 Library is not installed on this server.<br/><br/><b>SSH2 Library is required for making SSH connections to remote servers.</b>','dataset'=>array(), 'type'=>'ssh');
                        }
                }

        }

?>
