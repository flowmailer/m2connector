<?php

/*
 * This file is part of the Flowmailer Magento 2 Connector package.
 * Copyright (c) 2018 Flowmailer BV
 */

namespace Flowmailer\M2Connector\Helper\API;

class FlowmailerAPI
{
    private $authURL = 'https://login.flowmailer.net/oauth/token';
    private $baseURL = 'https://api.flowmailer.net';

    private $apiVersion = '1.4';

    private $maxAttempts        = 3;
    private $maxMultiAttempts   = 10;
    private $multiMaxConcurrent = 10;

    private $authToken;
    private $authTime;

    private $channel;
    private $logger;
    private $accountId;
    private $clientId;
    private $clientSecret;
    private $curlMulti;

    public function __construct($accountId, $clientId, $clientSecret)
    {
        $this->accountId    = $accountId;
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;

        $mh = curl_multi_init();
        curl_multi_setopt($mh, CURLMOPT_MAXCONNECTS, 10);
        $this->curlMulti = $mh;

        $this->channel = curl_init();
    }

    public function __destruct()
    {
        curl_multi_close($this->curlMulti);
    }

    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    private function log($text)
    {
        if ($this->logger) {
            $this->logger->debug($text);
        } else {
            echo $text."\r\n";
        }
    }

    private function parseHeaders($header)
    {
        $headers            = [];
        $fields             = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
        $responseCodeHeader = explode(' ', $fields[0]);
        if (isset($responseCodeHeader[1])) {
            $headers['ResponseCode'] = $responseCodeHeader[1];
        } else {
            $headers['ResponseCode'] = '000';
        }

        foreach ($fields as $field) {
            if (preg_match('/([^:]+): (.+)/m', $field, $match)) {
                $match[1] = preg_replace_callback(
                    '/(?<=^|[\x09\x20\x2D])./',
                    function ($matches) {
                        return strtoupper($matches[0]);
                    },
                    strtolower(trim($match[1]))
                );

                if (isset($headers[$match[1]])) {
                    $headers[$match[1]] = [$headers[$match[1]], $match[2]];
                } else {
                    $headers[$match[1]] = trim($match[2]);
                }
            }
        }

        return $headers;
    }

    private function getToken()
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        curl_setopt($ch, CURLOPT_URL, $this->authURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $fields = [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type'    => 'client_credentials',
        ];
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));

        $response = curl_exec($ch);

        $return             = [];
        $return['response'] = $response;

        $headerSize        = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $return['headers'] = $this->parseHeaders(substr($return['response'], 0, $headerSize));
        $return['auth']    = json_decode(substr($return['response'], $headerSize));

        curl_close($ch);

        if ($return['headers']['ResponseCode'] == 200) {
            return $return;
        } else {
            $authToken = null;
            $this->log($response);

            return false;
        }
    }

    private function ensureToken()
    {
        if ($this->authToken === null || $this->authTime <= (time() - 30)) {
            $success  = false;
            $attempts = 0;
            do {
                ++$attempts;
                $response = $this->getToken();
                if ($response !== false) {
                    $success         = true;
                    $this->authTime  = time() + $response['auth']->expires_in;
                    $this->authToken = $response['auth']->access_token;
                }
            } while (!$success && $attempts < $this->maxAttempts);
        }
    }

    private function refreshToken()
    {
        $success  = false;
        $attempts = 0;
        do {
            ++$attempts;
            $response = $this->getToken();
            if ($response !== false) {
                $success         = true;
                $this->authTime  = time() + $response['auth']->expires_in;
                $this->authToken = $response['auth']->access_token;
            }
        } while (!$success && $attempts < $this->maxAttempts);
    }

    private function curlExecWithMulti($handle)
    {
        curl_multi_add_handle($this->curlMulti, $handle);

        $running = 0;
        do {
            curl_multi_exec($this->curlMulti, $running);
            curl_multi_select($this->curlMulti);
        } while ($running > 0);

        $output = curl_multi_getcontent($handle);
        curl_multi_remove_handle($this->curlMulti, $handle);

        return $output;
    }

    private function curlExecArrayWithMulti($handles)
    {
        $todohandles = $handles;

        $running = null;
        do {
            while ($running < $this->multiMaxConcurrent && count($todohandles) > 0) {
                $handle = array_pop($todohandles);
                curl_multi_add_handle($this->curlMulti, $handle);
                ++$running;
            }
        } while ($running > 0 || count($todohandles) > 0);

        $outputs = [];
        foreach ($handles as $handle) {
            $output    = curl_multi_getcontent($handle);
            $outputs[] = $output;
            curl_multi_remove_handle($this->curlMulti, $handle);
        }

        return $outputs;
    }

    private function tryCall($uri, $expectedCode, $extraHeaders = null, $method = 'GET', $postData = null)
    {
        $ch = $this->channel;
        curl_setopt($ch, CURLOPT_URL, $this->baseURL.'/'.$this->accountId.'/'.$uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);

        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            if ($postData != null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            }
        }

        $headers = [
            'Connection: Keep-Alive',
            'Keep-Alive: 300',
            'Authorization: Bearer '.$this->authToken,
            'Content-Type: application/vnd.flowmailer.v'.$this->apiVersion.'+json;charset=UTF-8',
            'Accept: application/vnd.flowmailer.v'.$this->apiVersion.'+json;charset=UTF-8',
            'Expect:',
        ];

        if ($extraHeaders !== null) {
            $headers = array_merge($headers, $extraHeaders);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $return             = [];
        $return['response'] = $this->curlExecWithMulti($ch);
        if ($return['response'] === false) {
            $this->log('cURL returned false: '.print_r(curl_getinfo($ch), true));
        }

        $headerSize        = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $return['headers'] = $this->parseHeaders(substr($return['response'], 0, $headerSize));

        $return['data'] = json_decode(substr($return['response'], $headerSize));

        return $return;
    }

    private function tryMultiCall($uri, $expectedCodes, $extraHeaders = null, $method = 'GET', $postDataArray = null)
    {
        $channels = [];

        $ch = false;
        foreach ($postDataArray as $postData) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseURL.'/'.$this->accountId.'/'.$uri);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 1);

            if ($method == 'POST') {
                curl_setopt($ch, CURLOPT_POST, 1);
                if ($postData != null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
                }
            }

            $headers = [
                'Connection: Keep-Alive',
                'Keep-Alive: 300',
                'Authorization: Bearer '.$this->authToken,
                'Content-Type: application/vnd.flowmailer.v'.$this->apiVersion.'+json;charset=UTF-8',
                'Accept: application/vnd.flowmailer.v'.$this->apiVersion.'+json;charset=UTF-8',
                'Expect:',
            ];

            if ($extraHeaders !== null) {
                $headers = array_merge($headers, $extraHeaders);
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $channels[] = $ch;
        }

        $outputs = $this->curlExecArrayWithMulti($channels);
        $returns = [];
        foreach ($outputs as $output) {
            $return             = [];
            $return['response'] = $output;

            if ($return['response'] === false && $ch !== false) {
                $this->log('cURL returned false: '.print_r(curl_getinfo($ch), true));
            }

            $parts             = explode("\r\n\r\n", $return['response'], 2);
            $return['headers'] = $this->parseHeaders($parts[0]);

            if (isset($parts[1])) {
                $return['data'] = json_decode($parts[1]);
            }

            $returns[] = $return;
        }

        return $returns;
    }

    private function call($uri, $expectedCode, $headers = null, $method = 'GET', $postData = null)
    {
        $this->ensureToken();

        $return = [];
        foreach (range(0, $this->maxAttempts) as $attempt) {
            $return = $this->tryCall($uri, $expectedCode, $headers, $method, $postData);
            if ($return['headers']['ResponseCode'] == $expectedCode) {
                return $return;
            }

            if ($return['headers']['ResponseCode'] == 401) {
                $this->refreshToken();
                continue;
            }

            $this->log('retrying: '.print_r($return, true));
            sleep(1);
        }

        return $return;
    }

    private function multiCall($uri, $expectedCodes, $headers = null, $method = 'GET', $postDataArray = null)
    {
        $this->ensureToken();

        $indexes = range(0, count($postDataArray) - 1);

        $attempts   = 0;
        $allreturns = [];
        do {
            ++$attempts;

            $returns = $this->tryMultiCall($uri, $expectedCodes, $headers, $method, $postDataArray);

            $returnmap     = array_map(null, $postDataArray, $returns, $indexes);
            $postDataArray = [];
            $indexes       = [];
            $refreshToken  = false;
            foreach ($returnmap as list($postData, $return, $index)) {
                if (in_array($return['headers']['ResponseCode'], $expectedCodes)) {
                    $allreturns[$index] = $return;
                } else {
                    $this->log('return: '.print_r($return, true));

                    $allreturns[$index] = $return;

                    $postDataArray[] = $postData;
                    $indexes[]       = $index;
                }

                if ($return['headers']['ResponseCode'] == 401) {
                    $refreshToken = true;
                }
            }

            if (empty($postDataArray)) {
                return $allreturns;
            }

            $this->log('retrying: '.count($postDataArray));

            if ($refreshToken) {
                $this->refreshToken();
                continue;
            }

            sleep(1);
        } while ($attempts < $this->maxMultiAttempts);
    }

    public function submitMessage(SubmitMessage $message)
    {
        return $this->call('/messages/submit', 201, null, 'POST', $message);
    }
}
