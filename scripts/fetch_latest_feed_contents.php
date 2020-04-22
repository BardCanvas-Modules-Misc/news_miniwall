<?php
/**
 * Get latest feed contents in JSON format
 *
 * @package    BardCanvas
 * @subpackage News Miniwall
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 * 
 * $_GET params:
 * @param string since    datetime of the latest fetched item (server side)
 * @param string callback optional
 */

use hng2_modules\news_miniwall\news_miniwall_repository;
use hng2_modules\news_miniwall\toolbox;
use hng2_modules\rauth_client\server;

include "../../config.php";
include "../../includes/bootstrap.inc";
header("Content-Type: application/json; charset=utf-8");

$latest_ts = trim(stripslashes($_GET["since"]));
$limit     = 30;

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
# Polling the Third Party Server
#

if( $is_3p_client )
{
    $toolbox = new toolbox();
    
    try
    {
        $res = $toolbox->thirdpartyserver_get_feed($_3p_host_url, $_3p_passphrase, $_SERVER["HTTP_HOST"], $latest_ts);
    }
    catch(\Exception $e)
    {
        die(
            (empty($_GET["callback"]) ? "" : "{$_GET["callback"]}(") .
            json_encode(array("message" => $e->getMessage())) .
            (empty($_GET["callback"]) ? "" : ")")
        );
    }
    
    die(
        (empty($_GET["callback"]) ? "" : "{$_GET["callback"]}(") .
        json_encode($res) .
        (empty($_GET["callback"]) ? "" : ")")
    );
}

#
# Polling the Remote Auth Server
#

if( $is_rauth_client )
{
    $toolbox    = new toolbox();
    $account_id = "";
    if( $account->_exists && ! in_array($account->user_name, $rauth_server->local_accounts) )
        $account_id = $account->id_account;
    
    try
    {
        $res = $toolbox->rauth_get_feed($rauth_server, $latest_ts, $account_id);
    }
    catch(\Exception $e)
    {
        die(
            (empty($_GET["callback"]) ? "" : "{$_GET["callback"]}(") .
            json_encode(array("message" => $e->getMessage())) .
            (empty($_GET["callback"]) ? "" : ")")
        );
    }
    
    die(
        (empty($_GET["callback"]) ? "" : "{$_GET["callback"]}(") .
        json_encode($res) .
        (empty($_GET["callback"]) ? "" : ")")
    );
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

if( $account->_exists )
{
    $filter[] = "
        item_id not in (
            select ri.item_id from news_miniwall_read_items ri
            where ri.account_id = '{$account->id_account}'
        )
    ";
}
else
{
    if( ! empty($latest_ts) )
    {
        try
        {
            new \DateTime($latest_ts);
            $filter[] = "date_fetched > '$latest_ts'";
        }
        catch(\Exception $e)
        {
            $latest_ts = "";
        }
    }
}

$repository = new news_miniwall_repository();
$nwm_recs   = $repository->find($filter, $limit, 0, "date_fetched desc");

if( empty($nwm_recs) ) die(
    (empty($_GET["callback"]) ? "" : "{$_GET["callback"]}(") .
    json_encode(array("message" => "OK", "last_fetched_ts" => $latest_ts, "data" => array())) .
    (empty($_GET["callback"]) ? "" : ")")
);

#
# Forge return data
#

$latest_ts = $nwm_recs[0]->date_fetched;
foreach($nwm_recs as $nwm_rec)
{
    $nwm_rec->source_name      = $feed_sources[$nwm_rec->item_source]->name;
    $nwm_rec->source_icon      = $config->full_root_url . $feed_sources[$nwm_rec->item_source]->icon_path;
    $nwm_rec->parsed_pubdate   = time_today_string($nwm_rec->date_published);
    $nwm_rec->parsed_fetchdate = time_today_string($nwm_rec->date_fetched);
    
    if( ! empty($nwm_rec->item_image_path) )
        $nwm_rec->item_image_path = $config->full_root_url  . "/mediaserver/" . $nwm_rec->item_image_path;
}

$nwm_recs = array_reverse($nwm_recs);
echo (empty($_GET["callback"]) ? "" : "{$_GET["callback"]}(") .
     json_encode(array("message" => "OK", "last_fetched_ts" => $latest_ts, "data" => $nwm_recs))   .
     (empty($_GET["callback"]) ? "" : ")")
;
