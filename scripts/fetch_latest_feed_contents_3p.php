<?php
/**
 * Get latest feed contents in JSON format
 * Version for 3rd party clients
 *
 * @package    BardCanvas
 * @subpackage News Miniwall
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 * 
 * $_POST params:
 * @param string host
 * @param string passphrase encrypted
 * @param string since      empty or encrypted datetime
 */

use hng2_modules\news_miniwall\news_miniwall_repository;

include "../../config.php";
include "../../includes/bootstrap.inc";
header("Content-Type: application/json; charset=utf-8");

$host                = strtolower(trim(stripslashes($_POST["host"])));
$incoming_passphrase = trim(stripslashes($_POST["passphrase"]));
$latest_ts           = trim(stripslashes($_POST["since"]));
$limit               = 30;

if( empty($host) )
{
    die(json_encode(array(
        "message" => trim($current_module->language->messages->calls->host_missing)
    )));
}

if( empty($incoming_passphrase) )
{
    die(json_encode(array(
        "message" => trim($current_module->language->messages->calls->passphrase_missing)
    )));
}

#
# Input validation
#

$raw_3p_clients = $settings->get("modules:news_miniwall.third_party_clients");
if( empty($raw_3p_clients) )
{
    die(json_encode(array(
        "message" => trim($current_module->language->messages->calls->service_disabled)
    )));
}

$host_passphrase = "";
foreach(explode("\n", $raw_3p_clients) as $line)
{
    $line = trim($line);
    
    if( empty($line) ) continue;
    if( substr($line, 0, 1) == "#" ) continue;
    
    $parts = explode(" - ", $line);
    $this_host = strtolower($parts[0]);
    
    if( stristr($this_host, $host) )
    {
        $host_passphrase = trim($parts[1]);
        
        break;
    }
    
    $host_without_www = str_replace("www.", "", $host);
    if( stristr($this_host, $host_without_www) )
    {
        $host_passphrase = trim($parts[1]);
        
        break;
    }
}

if( empty($host_passphrase) )
{
    die(json_encode(array(
        "message" => trim($current_module->language->messages->calls->host_not_found)
    )));
}

$decrypted_passphrase = three_layer_decrypt(
    $incoming_passphrase,
    $host,
    $host_passphrase,
    $host . $host_passphrase
);

if( $decrypted_passphrase != $host_passphrase )
{
    die(json_encode(array(
        "message" => trim($current_module->language->messages->calls->passphrase_mismatch)
    )));
}

if( ! empty($latest_ts) )
{
    $latest_ts = three_layer_decrypt(
        $latest_ts,
        $host,
        $host_passphrase,
        $host . $host_passphrase
    );
    
    try
    {
        new \DateTime($latest_ts);
    }
    catch(\Exception $e)
    {
        die(json_encode(array(
            "message" => trim($current_module->language->messages->calls->invalid_timestamp)
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
if( ! empty($latest_ts) ) $filter[] = "date_fetched > '$latest_ts'";

$repository = new news_miniwall_repository();
$nwm_recs   = $repository->find($filter, $limit, 0, "date_fetched desc");

if( empty($nwm_recs) ) die(
    json_encode(array("message" => "OK", "last_fetched_ts" => $latest_ts, "data" => array()))
);

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
