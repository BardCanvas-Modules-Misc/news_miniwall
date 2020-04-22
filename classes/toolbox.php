<?php
namespace hng2_modules\news_miniwall;

use hng2_modules\rauth_client\server;

class toolbox
{
    /**
     * @param server $server
     * @param int    $item_id
     * @param int    $account_id mandatory
     *
     * @return string
     * @throws \Exception
     */
    public function rauth_mark_item($server, $item_id, $account_id)
    {
        $url = sprintf(
            "%s/news_miniwall/scripts/mark_item_ra.php?wsh=%s",
            $server->auth_server_url,
            $server->auth_website_handle
        );
        
        $params = array(
            "item_id" => three_layer_encrypt(
                $item_id,
                $server->auth_server_encryption_key1,
                $server->auth_server_encryption_key2,
                $server->auth_server_encryption_key3
            ),
            "account" => three_layer_encrypt(
                $account_id,
                $server->auth_server_encryption_key1,
                $server->auth_server_encryption_key2,
                $server->auth_server_encryption_key3
            )
        );
        
        $res = $this->fetch($server->auth_server_title, $url, $params);
        return $res->message;
    }
    
    /**
     * @param string $url
     * @param string $passphrase
     * @param string $host
     * @param string $since      datetime | nothing
     * 
     * @return object
     * @throws \Exception
     */
    public function thirdpartyserver_get_feed($url, $passphrase, $host, $since)
    {
        $server_name = basename($url);
        $server_name = str_replace("http://",  "", $server_name);
        $server_name = str_replace("https://", "", $server_name);
        $server_name = str_replace("www.",     "", $server_name);
        $server_name = str_replace("/",        "", $server_name);
        
        $url = sprintf(
            "%s/news_miniwall/scripts/fetch_latest_feed_contents_3p.php",
            rtrim($url, " /")
        );
        
        $params = array(
            "host"       => $host,
            "passphrase" => three_layer_encrypt($passphrase, $host, $passphrase, $host . $passphrase),
            "since"      => empty($since) ? "" : three_layer_encrypt($since, $host, $passphrase, $host.$passphrase),
        );
        
        return $this->fetch($server_name, $url, $params);
    }
    
    /**
     * @param server $server
     * @param string $since      datetime | nothing
     * @param int    $account_id optional
     *
     * @return object
     * @throws \Exception
     */
    public function rauth_get_feed($server, $since, $account_id)
    {
        $url = sprintf(
            "%s/news_miniwall/scripts/fetch_latest_feed_contents_ra.php?wsh=%s",
            $server->auth_server_url,
            $server->auth_website_handle
        );
        
        $params = array(
            "since" => empty($since) ? "" : three_layer_encrypt(
                $since,
                $server->auth_server_encryption_key1,
                $server->auth_server_encryption_key2,
                $server->auth_server_encryption_key3
            ),
            "account" => empty($account_id) ? "" : three_layer_encrypt(
                $account_id,
                $server->auth_server_encryption_key1,
                $server->auth_server_encryption_key2,
                $server->auth_server_encryption_key3
            )
        );
    
        return $this->fetch($server->auth_server_title, $url, $params);
    }
    
    /**
     * @param string $server_name
     * @param string $url
     * @param array  $params
     * 
     * @return object
     * @throws \Exception
     */
    private function fetch($server_name, $url, $params = array())
    {
        global $modules;
        $current_module = $modules["news_miniwall"];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,            $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT,  true);
        
        if( ! empty($params) )
        {
            curl_setopt($ch, CURLOPT_POST,           true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,     http_build_query($params));
        }
        
        $contents = curl_exec($ch);
        
        if( curl_error($ch) ) throw new \Exception(sprintf(
            $current_module->language->messages->remote->error_connecting,
            $server_name,
            curl_error($ch)
        ));
        
        curl_close($ch);
        
        if( empty($contents) ) throw new \Exception(sprintf(
            $current_module->language->messages->remote->empty_response,
            $server_name
        ));
        
        $json = json_decode($contents);
        if( is_null($json) ) throw new \Exception(sprintf(
            $current_module->language->messages->remote->invalid_response,
            $server_name
        ));
        
        if( ! (is_object($json) || is_array($json)) ) throw new \Exception(sprintf(
            $current_module->language->messages->remote->invalid_response,
            $server_name
        ));
        
        if( $json->message != "OK" ) throw new \Exception(sprintf(
            $current_module->language->messages->remote->error_received,
            $server_name,
            $json->message
        ));
        
        return $json;
    }
}
