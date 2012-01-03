<?php

// To make a Service for RPCServer, either extend this class or use the same structure
abstract class RPCServer_Service
{
    private static $server;
    public static $instance;

    public function __construct(RPCServer $server)
    {
        self::$server = $server;
        self::$instance = $this;
    }

    public function preProcessCallback($server)
    {
    }

    public function postProcessCallback(&$result)
    {
    }

    public function serverFaultCallback(&$result, $isInternal)
    {
    }

    /*
     * Example of a public facing method:
     * public function describeService_meta()
     * {
     *     return array('call' => 'RPCizzle::describeService',
     *                  'description' => 'Describes all methods in the service.',
     *                  'params' => array(),
     *                  'return' => self::TYPE_STRING,
     *                  'flags' => 0,
     *                  );
     * }
     */
}

?>