<?
/**
 * On-screen widget (post-footer)
 *
 * @package    BardCanvas
 * @subpackage News Miniwall
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 */


use hng2_modules\rauth_client\server;

global $account, $settings, $modules;

if( $template->layout == "popup" && $settings->get("modules:news_miniwall.show_in_popups") != "true" ) return;

if( $account->_exists && $account->engine_prefs["@news_miniwall:enabled"] == "false" ) return;

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

# If we're local...
if( ! $is_rauth_client && ! $is_3p_client )
{
    $raw_sources = $settings->get("modules:news_miniwall.rss_sources");
    if( empty($raw_sources) ) return;
}

$widget_class = "";
if( $account->_exists && $account->engine_prefs["@news_miniwall:collapsed"] == "true" ) $widget_class = "collapsed";
elseif( ! $account->_exists && $_COOKIE["nmw_widget_collapsed"] == "true" ) $widget_class = "collapsed";

$widget_title = $settings->get("modules:news_miniwall.widget_title");
if( empty($widget_title) ) $widget_title = $this_module->language->widget->title;

$can_disable = $account->_exists && $account->level >= (int) $settings->get("modules:news_miniwall.disabling_level");
?>

<!-- News Miniwall elements -->

<link rel="stylesheet" type="text/css" href="<?= $this_module->get_url() ?>/media/styles~v1.0.3-2.css">

<? if( $modules["messaging"]->enabled ): ?>
    <style type="text/css">
        #online_users_list[data-state="expanded"] { z-index: 300; }
    </style>
    <? if( $settings->get("modules:posts.disable_quick_post_button") == "true" ): ?>
        <style type="text/css">
            #news_miniwall_widget_container { bottom: 0; left: 20px; }
        </style>
    <? else: ?>
        <style type="text/css">
            #quick_post_floating_trigger { bottom: 40px; }
            #news_miniwall_widget_container { bottom: 0; left: 20px; z-index: 200; }
        </style>
    <? endif; ?>
<? else: ?>
    <style type="text/css">
        #news_miniwall_widget_container {
            bottom: 0; right: 10px;
        }
    </style>
<? endif; ?>

<script type="text/javascript">
    var nvm_is_rauth_client    = <?= $is_rauth_client ? "true" : "false" ?>;
    var nvm_is_3p_client       = <?= $is_3p_client    ? "true" : "false" ?>;
    var nwm_gfetcher_script    = '<?= $this_module->get_url() ?>/scripts/fetch_latest_feed_contents.php';
    var nwm_gmarker_script     = '<?= $this_module->get_url() ?>/scripts/mark_item.php';
    var nwm_gfetcher_heartbeat = 10 * 1000;
    var nwm_gfetcher_interval  = null;
    var nwm_gfetching          = false;
    var nwm_last_fetched_ts    = '';
</script>
<script type="text/javascript" src="<?= $this_module->get_url() ?>/media/functions~v1.0.6-7.js"></script>

<div id="news_miniwall_widget_container" class="<?= $widget_class ?>" style="display: none">
    <div class="footing">
        <span class="pseudo_link mark_all" onclick="mark_all_news_miniwall_items()"><i
              class="fa fa-check-circle-o"></i> <?= $this_module->language->widget->mark_all ?></span>
    </div>
    <div class="items"></div>
    <div class="title" onclick="toggle_news_miniwall_widget()">
        <? if($can_disable): ?>
            <span class="disable_icon pull-right" style="margin-left: 5px;">
                <i class="fa fa-ban fa-fw" title="<?= $this_module->language->disable_title ?>"
                   onclick="event.stopPropagation(); show_nmw_disable_prompt()"></i>
            </span>
        <? endif; ?>
        <span class="nwm_widget_toggle">
            <i class="widget_collapsed fa fa-chevron-right fa-fw"></i>
            <i class="widget_expanded  fa fa-chevron-up    fa-fw"></i>
        </span>
        <span class="caption"><?= $widget_title ?></span>
        <span class="count">0</span>
    </div>
</div>

<? if($can_disable): ?>
    <div id="news_miniwall_hiding_prompt" style="display: none" title="<?= $this_module->language->disable_title ?>"
         data-ok-caption="<?= $language->ok ?>" data-cancel-caption="<?= $language->cancel ?>">
        <?= $this_module->language->disable_prompt ?>
    </div>
<? endif; ?>

<template id="news_miniwall_widget_item_template">
    <div class="news_miniwall_widget_item" data-item-id="{$item_id}">
        <div class="closer pseudo_link" onclick="mark_nwm_item_as_read(this)"><i
             class="normal fa fa-circle-o fa-lg"></i><i
             class="hover  fa fa-check-circle-o fa-lg"></i></div>
        <div class="left">
            <img class="icon" src="{$source_icon}">
        </div>
        <div class="right">
            <div class="source_name">{$source_name}</div>
            <div class="item_pubdate">{$date_published}</div>
            <div class="item_title">
                <? if( $account->_exists ): ?>
                    <a href="{$item_url}" target="_blank" onclick="mark_nwm_item_as_read(this)">{$item_title}<br>
                        <img class="item_thumbnail" src="{$item_image_path}"></a>
                <? else: ?>
                    <a href="{$item_url}" target="_blank">{$item_title}<br>
                        <img class="item_thumbnail" src="{$item_image_path}"></a>
                <? endif; ?>
            </div>
            <div class="item_excerpt">
                {$item_excerpt}
            </div>
        </div>
    </div>
</template>
