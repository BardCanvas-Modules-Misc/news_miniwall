<?php
/**
 * Mark item as read
 * 
 * @package    BardCanvas
 * @subpackage News Miniwall
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 * 
 * $_GET params:
 * @param int    item_id
 * @param string callback optional
 */

use hng2_modules\news_miniwall\news_miniwall_repository;
use hng2_modules\news_miniwall\toolbox;
use hng2_modules\rauth_client\server;

include "../../config.php";
include "../../includes/bootstrap.inc";
header("Content-Type: application/json; charset=utf-8");

if( ! $account->_exists ) throw_fake_401();
if( $account->state != "enabled" ) throw_fake_401();

$item_id = (int) $_GET["item_id"];

if( empty($item_id) ) die(
    (empty($_GET["callback"]) ? "" : "{$_GET["callback"]}(") .
    json_encode(array("message" => trim($current_module->language->messages->invalid_item_id))) .
    (empty($_GET["callback"]) ? "" : ")")
);

/**
 * @var bool   $is_rauth_client
 * @var bool   $is_3p_client
 * @var server $rauth_server
 * @var string $_3p_host_url
 * @var string $_3p_passphrase
 */
#region Imports evaluation
#=========================

$rauth_server    = null;
$is_rauth_client = false;
if(
    $settings->get("modules:news_miniwall.read_from_rauth_server") == "true"
    && $modules["rauth_client"]->enabled
) {
    try
    {
        $rauth_server    = new server();
        $is_rauth_client = true;
    }
    catch(\Exception $e)
    {
        $is_rauth_client = false;
    }
}
$is_3p_client   = false;
$_3p_host_url   = "";
$_3p_passphrase = "";
if( ! $is_rauth_client )
{
    $raw_server = $settings->get("modules:news_miniwall.read_from_server");
    if( ! empty($raw_server) )
    {
        $parts = explode(" - ", $raw_server);
        if( count($parts) == 2 )
        {
            if( filter_var($parts[0], FILTER_VALIDATE_URL) )
            {
                $_3p_host_url   = $parts[0];
                $_3p_passphrase = $parts[1];
                $is_3p_client   = true;
            }
        }
    }
}

#=========
#endregion

#
# Third Party Server case excluded. This is a cookie set locally.
#

if( $is_3p_client )
{
    die(
        (empty($_GET["callback"]) ? "" : "{$_GET["callback"]}(") .
        json_encode(array("message" => trim($current_module->language->messages->calls->not_allowed))) .
        (empty($_GET["callback"]) ? "" : ")")
    );
}

#
# Remote Auth Server case
#

if( $is_rauth_client )
{
    $toolbox = new toolbox();
    
    if( $account->_exists && in_array($account->user_name, $rauth_server->local_accounts) )
    {
        die(
            (empty($_GET["callback"]) ? "" : "{$_GET["callback"]}(") .
            json_encode(array("message" => "@KEEP_IN_COOKIE"))   .
            (empty($_GET["callback"]) ? "" : ")")
        );
    }
    
    try
    {
        $res = $toolbox->rauth_mark_item($rauth_server, $item_id, $account->id_account);
    }
    catch(\Exception $e)
    {
        $res = $e->getMessage();
    }
    
    die(
        (empty($_GET["callback"]) ? "" : "{$_GET["callback"]}(") .
        json_encode(array("message" => $res)) .
        (empty($_GET["callback"]) ? "" : ")")
    );
}

#
# Local case
#


$repository = new news_miniwall_repository();
$repository->mark_item_as_read($item_id, $account->id_account);

echo (empty($_GET["callback"]) ? "" : "{$_GET["callback"]}(") .
     json_encode(array("message" => "OK"))   .
     (empty($_GET["callback"]) ? "" : ")")
;
