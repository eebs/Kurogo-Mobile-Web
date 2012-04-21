<?php

class RedisCache extends KurogoMemoryCache {

    protected $connection;
    protected $host = 'localhost';
    protected $port = 6379;

    protected function init($args){
        parent::init($args);

        if(isset($args['CACHE_HOST'])) {
            $this->host = $args['CACHE_HOST'];
        }
        if(isset($args['CACHE_PORT'])) {
            $this->port = $args['CACHE_PORT'];
        }
        if(isset($args['CACHE_RECONNECT'])) {
            $this->reconnect = $args['CACHE_RECONNECT'];
        }

        $this->connect($this->host, $this->port);
    }

    protected function connect($host, $port){
        if(!empty($this->connection)){
            fclose($this->connection);
            $this->connection = null;
        }
        $socket = fsockopen($host, $port, $errornumber, $errorstring);
        if(!$socket){
            throw new KurogoConfigurationException('Connection error: '.$errornumber.':'.$errorstring);
        }
        $this->connection = $socket;
        return $socket;
    }

    protected function send($args){
        if(empty($this->connection)){
            $this->connect($this->host, $this->port);
        }
        $command = '*'.count($args)."\r\n";
        foreach($args as $arg){
            $command .= "$".strlen($arg)."\r\n".$arg."\r\n";
        }
        fwrite($this->connection, $command);
        return $this->parseResponse();
    }

    protected function parseResponse(){
        if(empty($this->connection)){
            $this->connect($this->host, $this->port);
        }
        $server_response = fgets($this->connection);
        $reply = trim($server_response);
        $response = null;

        switch ($reply[0])
        {
            /* Error reply */
            case '-':
                throw new KurogoException('Error: '.$reply);
            /* Inline reply */
            case '+':
                return substr($reply, 1);
            /* Bulk reply */
            case '$':
                if ($reply=='$-1'){
                    return null;
                }
                $response = null;
                $size     = intval(substr($reply, 1));
                if ($size > 0){
                    $response = stream_get_contents($this->connection, $size);
                }
                fread($this->connection, 2); /* discard crlf */
                break;
            /* Multi-bulk reply */
            case '*':
                $count = substr($reply, 1);
                if ($count=='-1'){
                    return null;
                }
                $response = array();
                for ($i = 0; $i < $count; $i++){
                    $response[] = $this->parseResponse();
                }
                break;
            /* Integer reply */
            case ':':
                return intval(substr($reply, 1));
                break;
            default:
                throw new KurogoException('Non-protocol response: '.print_r($server_response, 1));
                return false;
        }
        return $response;
    }

    public function get($key){
        return unserialize($this->send(array('get', $key)));
    }

    /* only store the value if it does not exist */
    public function add($key, $value, $ttl = null){
        $response = $this->send(array('setnx', $key, serialize($value)));
        if(!$ttl){
            $ttl = $this->ttl;
        }
        $this->send(array('expire', $key, $ttl));
        return $response;
    }

    /* store unconditionally */
    public function set($key, $value, $ttl = null){
        $response = $this->send(array('set', $key, serialize($value)));
        if(!$ttl){
            $ttl = $this->ttl;
        }
        $this->send(array('expire', $key, $ttl));
        return $response;
    }

    public function delete($key){
        return $this->send(array('del', $key));
    }

    public function clear(){
        return $this->send(array('flushdb'));
    }
}
