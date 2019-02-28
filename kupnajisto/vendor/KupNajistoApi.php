<?php

/**
 * API for payment method Kup Najisto
 * http://www.kupnajisto.cz/
 * 
 * @link https://github.com/Usertech/Kup-Najisto-API-PHP
 * @author Michal Vlcek <vlcek@usertechnologies.com>
 * @copyright 2014 UserTechnologies s.r.o. - http://usertechnologies.com/
 * @version 0.1.2
 */
class KupNajistoApi
{
    /** @var string auth token  */
    private static $token = '';

    /** @var array  */
    private $headers = array(
        'Accept' => 'Accept: application/json',
        'Content-Type' => 'Content-Type: application/json'
    );

    /** @var string  */
    private $apiUrl = '';

    /** @var string  */
    private $username = '';

    /** @var string  */
    private $password = '';

    /** @var boolean  */
    private $retry = TRUE;

    public function KupNajistoApi($username = '', $password = '', $url = '') {

        $this->username = $username;
        $this->password = $password;
        $this->apiUrl = $url;

        $this->login($username, $password);
    }

    /**
     * Performs POST login request
     * @param  string $username
     * @param  string $password
     * @return array $response
     */
    private function login($username = '', $password = '')
    {
        $response = $this->request('POST', 'api/login/', compact('username', 'password'));
        self::$token = $response['token'];

        $this->headers['Authorization'] = 'Authorization: '.self::$token;
        return $response;
    }

    /**
     * Performs GET request to ask for order
     * @param  int $id
     * @return array $response json response
     */
    public function requestOrder($id) 
    {
        return $this->request('GET', 'api/order/'.$id);
    }

    /**
     * Performs POST request to create order
     * @param  array $data
     * @return array $response json response
     */
    public function createOrder($data = array())
    {
        return $this->request('POST', 'api/order/', $data);
    }

    /**
     * Performs PUT request to create order
     * @param  int $id
     * @return array $response json response
     */
    public function confirmOrder($id = NULL)
    {
        return $this->updateOrder($id, array());
    }

    /**
     * Performs PUT request to update order
     * @param  int $id
     * @param  array $data
     * @return array $response json response
     */
    public function updateOrder($id = NULL, $data = NULL)
    {
        return $this->request('PUT', 'api/order/'.$id.'/', $data);
    }

    /*
     * Perform an API request
     * @param string $method
     * @param string $url
     * @param array $data
     * @return array $response json response
     * @throws KupNajistoException if any error occur
     */
    private function request($method, $url, $data = NULL)
    {
        $curl = curl_init();

        if ($data !== NULL) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }

        if (in_array($method, array('POST', 'PUT', 'GET'))) {
            $len = ($data === NULL)? 0 : strlen(json_encode($data));
            $this->headers['Content-Length'] = 'Content-Length: '.$len;
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, array_values($this->headers));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_URL, $this->apiUrl.$url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            throw new KupNajistoException('Curl error: '.curl_error($curl), curl_errno($curl));
        }

        $info = curl_getinfo($curl);
        if ( !in_array($info['http_code'], array(200, 201)) ) {
            // expired token - try autologin
            if ($info['http_code'] === 403) {
                if ($this->retry) {
                    $this->retry = FALSE; // prevent cycle
                    $this->login($this->username, $this->password);
                    $response = $this->request($method, $url, $data);
                    $this->retry = TRUE;
                    return $response;
                } else {
                    throw new KupNajistoException('{ "messages": { "error": "Token expired" } }', $info['http_code']);
                }
            } else {
                throw new KupNajistoException($response, $info['http_code']);
            }
        }

        curl_close($curl);

        return json_decode($response, TRUE);
    }

}

/**
 * Custom exception class
 */
class KupNajistoException extends Exception
{
    
}

?>
