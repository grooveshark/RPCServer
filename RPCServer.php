<?php

class RPCServer
{
    const REQUIRED = 0;
    const OPTIONAL = 1;
    const TYPE_INT = 2;
    const TYPE_BOOL = 4;
    const TYPE_FLOAT = 8;
    const TYPE_OBJECT = 0;
    const TYPE_STRING = 0;

    const ERR_DEFAULT = -10000;
    const ERR_REQUEST = -10001;
    const ERR_FORMAT = -10002;
    const ERR_METHOD = -10003;
    const ERR_PARAMETERS = -10004;

    public $format = 'json';
    public $request = [];
    public $methodMeta = [];
    public $responseHeaders = [];
    public $response = [];

    /* @var RPCServer_Service */
    public $service;

    /**
     * @param RPCServer_Service $service
     * @param string $rawRequest
     */
    public function serve($service, $rawRequest)
    {
        $this->service = $service;
        if (empty($rawRequest)) {
            $fault = new Fault('Cannot accept an empty request.', self::ERR_REQUEST);
            $this->sendFault($fault, true);
            return;
        }

        $this->request = $this->decode($rawRequest);
        if (empty($this->request['method'])) {
            $fault = new Fault('Unable to decode request.', self::ERR_FORMAT);
            $this->sendFault($fault, true);
            return;
        }

        // attempt to get the requested method's meta information
        $metaMethodName = $this->request['method'] . '_meta';
        if (!method_exists($service, $metaMethodName)) {
            $faultDetails = ['method' => $this->request['method']];
            $fault = new Fault('Method does not exist.', self::ERR_METHOD, $faultDetails);
            $this->sendFault($fault, true);
            return;
        }
        $this->methodMeta = $service->$metaMethodName();
        if (empty($this->methodMeta['call'])) {
            throw new Exception("Method meta for $metaMethodName is missing the call key: " . print_r($this->methodMeta, true));
        }

        // get the request arguments for calling the method
        $requestArguments = $this->parseRequestArguments();
        if (!empty($paramsResult['missing'])) {
            $faultDetails = ['missingParameters' => $paramsResult['missing']];
            $fault = new Fault('Missing required parameter(s) \'' . join(', ', $paramsResult['missing']) . '\'.', self::ERR_PARAMETERS, $faultDetails);
            $this->sendFault($fault, true);
            return;
        }

        $service->preProcessCallback($this);

        // call the method with the $callParameters array expanded to arguments
        $method = $this->methodMeta['call'];
        $result = $method[0]::$method[1](...$requestArguments['arguments']);

        $service->postProcessCallback($result);

        // check if the result is a Fault and return it if so
        if (isset($result) && is_object($result) && $result instanceof Fault) {
            $this->sendFault($result);
            return;
        }

        // format the result
        $returnType = $this->methodMeta['return'];
        if (!empty($returnType)) {
            $result = self::castIfNecessary($result, $returnType);
        }
        $this->response = array('headers' => $this->responseHeaders,
                                'result' => $result,
                                );

        $this->sendResponse();
    }

    public function parseRequestArguments()
    {
        $result = array('arguments' => array(), 'missing' => array());

        foreach ($this->methodMeta['params'] as $paramName => $flags) {
            if (isset($this->request['parameters'][$paramName])) {
                $result['arguments'][] = self::castIfNecessary($this->request['parameters'][$paramName], $flags);
            } elseif (($flags & self::OPTIONAL) === self::OPTIONAL) {
                $result['arguments'][] = null;
            } else {
                $result['missing'][] = $paramName;
            }
        }

        return $result;
    }

    public function sendFault($fault, $isInternal = false)
    {
        if (!($fault instanceof Fault)) {
            $fault = new Fault('Unknown error', self::ERR_DEFAULT, $fault);
        }

        $faultResponse = $fault->getFaultForSerialization();
        $this->response = array('headers' => $this->responseHeaders,
                                'fault' => $faultResponse,
                                );

        $this->service->serverFaultCallback($fault, $isInternal);
        $this->sendResponse();
    }

    public function sendResponse()
    {
        $response = $this->encode($this->response);
        echo $response;
    }

    public static function castIfNecessary($param, $flags)
    {
        if ($flags & self::TYPE_INT) {
            return (int)$param;
        } elseif ($flags & self::TYPE_BOOL) {
            return (bool)$param;
        } elseif ($flags & self::TYPE_FLOAT) {
            return (float)$param;
        }
        // no casting for objects or strings
        return $param;
    }

    public function encode($data)
    {
        switch ($this->format) {
            case 'json':
                return json_encode($data);
                break;
            case 'raw':
                return $data;
                break;
        }

        throw new Exception('encode format unknown: ' . $this->format);
    }

    public function decode($data)
    {
        switch ($this->format) {
            case 'json':
                return json_decode($data, true);
                break;
            case 'raw':
                return $data;
                break;
        }

        throw new Exception('encode format unknown: ' . $this->format);
    }
}
?>