<?php
/**
 * Get latest feed contents in JSON format
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
 * @param string since    empty | encrypted datetime
 * @param int    account  empty | encrypted account
 * 
 * @return string json object {message:string, data:array}
 */

use hng2_base\account;
use hng2_modules\news_miniwall\news_miniwall_repository;
use hng2_modules\rauth_server\toolbox;

include "../../config.php";
include "../../includes/bootstrap.inc";
header("Content-Type: application/json; charset=utf-8");

$ws_handle  = trim(stripslashes($_GET["wsh"]));
$latest_ts  = trim(stripslashes($_POST["since"]));
$account_id = trim(stripslashes($_POST["account"]));
$limit      = 30;

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

if( ! empty($latest_ts) )
{
    $latest_ts = three_layer_decrypt(
        $latest_ts, $wsdata["encryption_key1"], $wsdata["encryption_key2"], $wsdata["encryption_key3"]
    );
    
    try
    {
        new \DateTime($latest_ts);
    }
    catch(\Exception $e)
    {
        $latest_ts = "";
    }
    
}

if( ! empty($account_id) )
{
    $account_id = three_layer_decrypt(
        $account_id, $wsdata["encryption_key1"], $wsdata["encryption_key2"], $wsdata["encryption_key3"]
    );
    
    if( ! is_numeric($account_id) )
    {
        die(json_encode(array(
            "message" => trim($current_module->language->messages->calls->invalid_account)
        )));
    }
}

#
# Prepare feeds
#

$raw_sources  = $settings->get("modules:news_miniwall.rss_sources");
$feed_sources = array();
if( ! empty($raw_sources) )
{
    foreach(explode("\n", $raw_sources) as $line)
    {
        $line  = trim($line);
        list($title, $url, $icon_path, $state) = explode("\t", $line);
        if( $state != "enabled" ) continue;
        
        $feed_sources[$title] = (object) array(
            "name"      => $title,
            "url"       => $url,
            "icon_path" => $icon_path,
            "state"     => $state,
        );
    }
}

#
# Fetch data
#

$filter = array();

if( ! empty($account_id) )
{
    $filter[] = "
        item_id not in (
            select ri.item_id from news_miniwall_read_items ri
            where ri.account_id = '{$account_id}'
        )
    ";
    
    $account = new account($account_id);
    if( $account->_exists )
    {
        $boundary = date("Y-m-d H:i:s", strtotime("$account->creation_date - 1 day"));
        $hard_one = date("Y-m-d H:i:s", strtotime("today - 3 days"));
        if( $boundary < $hard_one ) $boundary = date("Y-m-d H:i:s", strtotime("today - 1 day"));
        $filter[] = "date_fetched >= '$boundary'";
    }
}
else
{
    if( ! empty($latest_ts) )
    {
        $filter[] = "date_fetched > '$latest_ts'";
    }
}

$repository = new news_miniwall_repository();
$nwm_recs   = $repository->find($filter, $limit, 0, "date_fetched desc");

if( empty($nwm_recs) ) die(json_encode(array(
    "message" => "OK", "last_fetched_ts" => $latest_ts, "data" => array()
)));

#
# Forge return data
#

$latest_ts = $nwm_recs[0]->date_fetched;
foreach($nwm_recs as $nwm_rec)
{
    $nwm_rec->source_name      = empty($feed_sources[$nwm_rec->item_source]) ? ""
                               : $feed_sources[$nwm_rec->item_source]->name;
    $nwm_rec->source_icon      = empty($feed_sources[$nwm_rec->item_source]) ? ""
                               : ($config->full_root_url . $feed_sources[$nwm_rec->item_source]->icon_path);
    $nwm_rec->parsed_pubdate   = time_today_string($nwm_rec->date_published);
    $nwm_rec->parsed_fetchdate = time_today_string($nwm_rec->date_fetched);
    
    if( ! empty($nwm_rec->item_image_path) )
        $nwm_rec->item_image_path = $config->full_root_url  . "/mediaserver/" . $nwm_rec->item_image_path;
}

$nwm_recs = array_reverse($nwm_recs);
echo json_encode(array(
    "message" => "OK", "last_fetched_ts" => $latest_ts, "data" => $nwm_recs
));
