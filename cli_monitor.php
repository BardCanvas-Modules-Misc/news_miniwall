<?php
/**
 * CLI monitor for News miniwall
 * Requires cron job to be set every 4 hours:
 *
 * @package    BardCanvas
 * @subpackage News Miniwall
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 */

use hng2_base\account;
use hng2_media\media_repository;
use hng2_modules\news_miniwall\news_miniwall_record;
use hng2_modules\news_miniwall\news_miniwall_repository;
use hng2_tools\cli_colortags;

chdir(__DIR__);

include "../config.php";
include "../includes/bootstrap.inc";
include "../includes/self_running_checker.inc";
set_time_limit(300);

if( ! $modules["news_miniwall"]->enabled ) die();

$current_module = $modules["news_miniwall"];

#region Prechecks

if( ! php_sapi_name() == "cli" ) die( "This program cannot be called this way.");
$now = date("Y-m-d H:i:s");

define("LOCK_FILE", "{$config->datafiles_location}/news_miniwall_monitor.pid");
if( ! is_writable($config->datafiles_location) )
{
    cli_colortags::write(
        "<red>[$now] Critical: cannot create lockfile on</red> <light_red>$config->datafiles_location</light_red>!\n" .
        "Please make sure that {$config->datafiles_location} is writable. If not, chmod it to 777. " .
        "<red>Aborting.</red>\n\n"
    );
    
    die();
}

$raw_sources = $settings->get("modules:news_miniwall.rss_sources");
if( empty($raw_sources) )
{
    cli_colortags::write(
        "<red>[$now] Critical: source feeds unset</red>\n" .
        "<red>Please open the settings editor and define the RSS sources to fetch.</red>\n" .
        "<red>Aborting.</red>\n\n"
    );
    
    die();
}

$feed_sources = array();
foreach(explode("\n", $raw_sources) as $line)
{
    $line  = trim($line);
    list($title, $url, $icon_path, $state) = explode("\t", $line);
    if( $state != "enabled" ) continue;
    
    $feed_sources[$title] = (object) array(
        "url"       => $url,
        "icon_path" => $icon_path,
        "state"     => $state,
    );
}

if( empty($feed_sources) )
{
    cli_colortags::write("\n<yellow>[$now] All sources are disabled. Aborting run.</yellow>\n\n");
    
    die();
}

if( self_running_checker() )
{
    cli_colortags::write("\n<red>[$now] Another instance is running. Aborting.</red>\n\n");
    
    die();
}

$last_pull_times = array();
$raw_pull_times = $settings->get("modules:news_miniwall.last_pull_times");
if( ! empty($raw_pull_times) )
{
    foreach( explode("\n", $raw_pull_times) as $line )
    {
        $line = trim($line);
        if( empty($line) ) continue;
        
        list($feed_name, $time)      = explode(" | ", $line);
        $last_pull_times[$feed_name] = $time;
    }
}

#endregion

$start = time();
cli_colortags::write("<light_cyan>[$now] - Starting run.</light_cyan>\n\n");

$author_account   = new account(100000000000000);
$feeds_repository = new news_miniwall_repository();
$media_repository = new media_repository();

$total_items_imported = 0;
foreach( $feed_sources as $feed_name => $feed_data )
{
    $feed_url = $feed_data->url;
    
    if( $last_pull_times[$feed_name] )
    {
        if( stristr($feed_url, "?") === false ) $feed_url .= "?since=" . urlencode($last_pull_times[$feed_name]);
        else                                    $feed_url .= "&since=" . urlencode($last_pull_times[$feed_name]);
    }
    
    cli_colortags::write(
        "<light_gray>Fetching </light_gray>" .
        "<light_blue>$feed_name</light_blue> " .
        "<light_gray>from {$feed_url} ...</light_gray> "
    );
    
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL,            $feed_url);
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true     );
    curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5        );
    curl_setopt( $ch, CURLOPT_TIMEOUT,        10       );
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true     );
    $t   = time();
    $res = curl_exec($ch);
    if( curl_error($ch) )
    {
        $error = curl_error($ch);
        
        cli_colortags::write(
            "\n<yellow>WARNING: Error fetching the feed:</yellow>\n" .
            "<yellow>$error</yellow>\n" .
            "<yellow>Skipping it.</yellow>\n\n"
        );
        
        continue;
    }
    
    cli_colortags::write(sprintf(
        "<light_green>OK!</light_green> <white>%s KiB downloaded in %s seconds.</white>\n",
        number_format(strlen($res) / 1024, 2),
        time() - $t
    ));
    curl_close($ch);
    
    $feed = @simplexml_load_string($res);
    
    if( empty($feed) )
    {
        cli_colortags::write(
            "<yellow>WARNING: Feed malformed!</yellow>\n" .
            "<yellow>The feed contents doesn't seem to be an XML file.</yellow>\n" .
            "<yellow>Please check the URL and fetch it manually, then validate it.</yellow>\n" .
            "<yellow>Notifying and skipping it.</yellow>\n\n"
        );
        
        broadcast_to_moderators("error", replace_escaped_objects(
            $current_module->language->error_notification,
            array('{$name}' => $feed_name)
        ));
        
        continue;
    }
    
    $icount = count($feed->channel->item);
    $index  = 0;
    foreach($feed->channel->item as $item)
    {
        $index++;
        
        $record = new news_miniwall_record(array(
            "item_source"     => $feed_name,
            "item_url"        => trim($item->link),
            "item_title"      => trim($item->title),
            "item_excerpt"    => make_excerpt_of(trim($item->description), 250),
            "item_image_path" => "",
            "date_published"  => date("Y-m-d H:i:s", strtotime(trim($item->pubDate))),
            "date_fetched"    => date("Y-m-d H:i:s"),
        ));
        
        $filter = array("item_source" => $record->item_source, "item_url" => $record->item_url);
        $found  = $feeds_repository->find($filter, 1, 0, "date_fetched asc");
        
        if( ! empty($found) )
        {
            cli_colortags::write(
                "<purple> • [$index/$icount] Item «</purple>" .
                "<light_purple>{$record->item_title}</light_purple>" .
                "<purple>» already downloaded.</purple>\n"
            );
            
            continue;
        }
        
        $this_zone   = new DateTimeZone(date_default_timezone_get());
        $source_time = new \DateTime($item->pubDate);
        $tz_offset   = timezone_offset_get($this_zone, $source_time) / 3600;
        if( $tz_offset == 0 ) $tz_offset = "+ 0";
        elseif( $tz_offset < 0 ) $tz_offset = str_replace("-", "+ ", $tz_offset);
        elseif( $tz_offset > 0 ) $tz_offset = str_replace("+", "- ", $tz_offset);
        $origin_time = date("Y-m-d H:i:s", strtotime("$record->date_published $tz_offset hours"));
        
        $item_title = make_excerpt_of($record->item_title);
        cli_colortags::write(
            "<light_gray> • [$index/$icount] Processing item «</light_gray>" .
            "<white>{$item_title}</white>" .
            "<light_gray>» published on {$origin_time} ({$record->date_published} locally)...</light_gray>\n"
        );
        
        if( $item->enclosure )
        {
            $imgurl = $item->enclosure["url"];
            $found  = $media_repository->find(array("description" => $imgurl), 1, 0, "creation_date desc");
            if( ! empty($found) )
            {
                cli_colortags::write(
                    "<light_blue>   Note: featured image already downloaded. Assigning it from archive.</light_blue>\n"
                );
                $record->item_image_path = $found[0]->path;
            }
            else
            {
                $record->item_image_path = $feeds_repository->fetch_remote_image(
                    $imgurl, $author_account, $record->item_url
                );
            }
        }
        
        $record->set_new_id();
        $resx = $feeds_repository->save($record);
        if( $resx == 0 )
        {
            cli_colortags::write(
                "<purple>   Duplicate found! Skipped.</purple>\n"
            );
        }
        else
        {
            cli_colortags::write(
                "<green>   Item saved successfully.</green>\n"
            );
            
            if( empty($last_pull_times[$feed_name]) || $origin_time > $last_pull_times[$feed_name] )
                $last_pull_times[$feed_name] = $origin_time;
            
            $total_items_imported++;
            sleep(1);
        }
    }
    
    cli_colortags::write("<light_cyan>Feed $feed_name finished.</light_cyan>\n\n");
}

if( ! empty($last_pull_times) )
{
    $raw_pull_times = array();
    foreach($last_pull_times as $feed_name => $date)
        $raw_pull_times[] = "$feed_name | $date";
    $raw_pull_times = implode("\n", $raw_pull_times);
    
    $settings->set("modules:news_miniwall.last_pull_times", $raw_pull_times);
}

$seconds = time() - $start;
cli_colortags::write("<cyan>Import finished in $seconds seconds. $total_items_imported items imported.</cyan>\n\n");
@unlink(LOCK_FILE);
