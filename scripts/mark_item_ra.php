<?php
/**
 * Mark item as read
 * Version for Remote Auth support.
 * 
 * @package    BardCanvas
 * @subpackage News Miniwall
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 * 
 * $_GET params:
 * @param string wsh      host key
 * 
 * $_POST params:
 * @param int    item_id encrypted
 * @param int    account encrypted
 *
 * @return string json object {message:string, data:array}
 */

use hng2_base\account;
use hng2_modules\news_miniwall\news_miniwall_repository;
use \hng2_modules\rauth_server\toolbox;

include "../../config.php";
include "../../includes/bootstrap.inc";
header("Content-Type: application/json; charset=utf-8");

$ws_handle  = trim(stripslashes($_GET["wsh"]));
$item_id    = trim(stripslashes($_POST["item_id"]));
$account_id = trim(stripslashes($_POST["account"]));

#
# Validate input
#

if( $settings->get("modules:news_miniwall.serve_to_rauth_clients") != "true" )
{
    die(json_encode(array(
        "message" => trim($current_module->language->messages->calls->service_disabled)
    )));
}

try
{
    check_sql_injection(array($ws_handle));
}
catch(\Exception $e)
{
    die(json_encode(array(
        "message" => $e->getMessage()
    )));
}

if( empty($item_id) ) die(
    json_encode(array("message" => trim($current_module->language->messages->invalid_item_id)))
);

if( empty($account_id) ) die(
    json_encode(array("message" => trim($current_module->language->messages->calls->no_account)))
);

$ra_toolbox = new toolbox();
try
{
    $wsdata = $ra_toolbox->init_website($ws_handle, false);
}
catch(\Exception $e)
{
    die(json_encode(array(
        "message" => $e->getMessage()
    )));
}

$item_id = three_layer_decrypt(
    $item_id, $wsdata["encryption_key1"], $wsdata["encryption_key2"], $wsdata["encryption_key3"]
);

if( ! is_numeric($item_id) )
{
    die(json_encode(array(
        "message" => trim($current_module->language->messages->invalid_item_id)
    )));
}

$account_id = three_layer_decrypt(
    $account_id, $wsdata["encryption_key1"], $wsdata["encryption_key2"], $wsdata["encryption_key3"]
);

if( ! is_numeric($account_id) )
{
    die(json_encode(array(
        "message" => trim($current_module->language->messages->calls->invalid_account)
    )));
}

$account = new account($account_id);

if( ! $account->_exists )
{
    die(json_encode(array(
        "message" => trim($current_module->language->messages->calls->account_not_found)
    )));
}

$repository = new news_miniwall_repository();

$item = $repository->get($item_id);

if( is_null($item) )
{
    die(json_encode(array(
        "message" => trim($current_module->language->messages->calls->item_not_found)
    )));
}

$repository->mark_item_as_read($item_id, $account->id_account);
echo json_encode(array("message" => "OK"));
