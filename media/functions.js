
// Imports
var nvm_is_rauth_client   ;
var nvm_is_3p_client      ;
var nwm_last_fetched_ts   ;
var nwm_gfetcher_script   ;
var nwm_gfetcher_heartbeat;
var nwm_gfetcher_interval ;
var nwm_gfetching         ;

function stop_news_miniwall_fetcher()
{
    clearInterval(nwm_gfetcher_interval);
}

function start_news_miniwall_fetcher()
{
    nwm_gfetcher_interval = setInterval("fetch_news_miniwall_gfeeds()", nwm_gfetcher_heartbeat);
}

function toggle_news_miniwall_widget()
{
    var $container = $('#news_miniwall_widget_container');
    var collapsed  = $container.hasClass('collapsed');
    var new_value  = collapsed ? '' : 'true';
    
    $container.toggleClass('collapsed', ! collapsed);
    
    if( $_CURRENT_USER_ID_ACCOUNT === "" || nvm_is_3p_client )
        $.cookie("nmw_widget_collapsed", new_value, {path: '/', expires: 365});
    else
        set_engine_pref('@news_miniwall:collapsed', new_value, function() {
            $container.toggleClass('collapsed', ! collapsed);
        });
}

function fetch_news_miniwall_gfeeds()
{
    if( nwm_gfetching ) return;
    
    var $container = $('#news_miniwall_widget_container');
    
    nwm_gfetching = true;
    $.getJSON(nwm_gfetcher_script, {since: nwm_last_fetched_ts}, function(data)
    {
        if( data.message !== 'OK' )
        {
            console.warn(data.message);
            stop_news_miniwall_fetcher();
            
            return;
        }
        
        nwm_last_fetched_ts = data.last_fetched_ts;
        nwm_gfetching = false;
        var items = data.data;
        render_news_miniwall_items(data.data);
        
        var unread_count = $container.find('.items .news_miniwall_widget_item:not(.read)').length;
        $container.find('.title .count').text(unread_count);
        show_hide_miniwall_widget();
    });
}

function render_news_miniwall_items(items)
{
    var $container      = $('#news_miniwall_widget_container');
    var $target         = $container.find('.items');
    var template_markup = $('#news_miniwall_widget_item_template').html();
    var items_rendered  = 0;
    
    var rids = $.cookie('nvm_read_items');
    if( ! rids ) rids = []; else rids = rids.split(',');
    
    for(var i in items)
    {
        var item    = items[i];
        var item_id = item.item_id;
        var search  = sprintf('.news_miniwall_widget_item[data-item-id="%s"]', item_id);
        if( $target.find(search).length > 0 ) continue;
        
        var template = template_markup;
        
        template = template.replace(/{\$item_id}/gi, item_id);
        template = template.replace(/{\$date_published}/gi, item.parsed_pubdate);
        template = template.replace(/{\$item_url}/gi, item.item_url);
        template = template.replace(/{\$item_title}/gi, item.item_title);
        template = template.replace(/{\$item_excerpt}/gi, item.item_excerpt);
        
        if( item.source_icon !== '' )
            template = template.replace(/{\$source_icon}/gi, item.source_icon);
        
        if( item.source_name !== '' )
            template = template.replace(/{\$source_name}/gi, item.source_name);
        
        if( item.item_image_path === '' )
            template = template.replace(/{\$item_image_path}/gi, $_FULL_ROOT_PATH + '/media/spacer.png');
        else
            template = template.replace(/{\$item_image_path}/gi, item.item_image_path);
        
        var $forged_item = $(template);
    
        if( item.source_icon     === '' ) $forged_item.find('.left').remove();
        if( item.source_name     === '' ) $forged_item.find('.source_name').remove();
        if( item.item_image_path === '' ) $forged_item.find('.item_thumbnail').remove();
        
        var increase_count = true;
        if( $_CURRENT_USER_ID_ACCOUNT === "" || nvm_is_3p_client )
        {
            if( rids.indexOf(item_id) >= 0 )
            {
                $forged_item.toggleClass('read', true);
                increase_count = false;
            }
        }
        else if( $_CURRENT_USER_ID_ACCOUNT !== "" && rids.indexOf(item_id) >= 0 )
        {
            // Case for local accounts on remote auth support
            $forged_item.toggleClass('read', true);
            increase_count = false;
        }
        
        $target.prepend($forged_item);
        if( increase_count ) items_rendered++;
    }
    
    if( items_rendered > 0 && typeof play_notification_sound === 'function' ) play_notification_sound('question2');
}

function mark_nwm_item_as_read(trigger, callback)
{
    var $container   = $('#news_miniwall_widget_container');
    var $count       = $container.find('.title .count');
    var unread_count = parseInt($count.text());
    var $item        = $(trigger).closest('.news_miniwall_widget_item');
    var id           = $item.attr('data-item-id');
    
    if( $_CURRENT_USER_ID_ACCOUNT === "" || nvm_is_3p_client )
    {
        set_read_id(id);
        $item.toggleClass('read', true);
        unread_count--;
        $count.text(unread_count);
        
        return;
    }
    
    $item.block(blockUI_medium_params);
    $.getJSON(nwm_gmarker_script, {item_id: id}, function(data)
    {
        if( data.message === '@KEEP_IN_COOKIE' )
        {
            set_read_id(id);
            $item.toggleClass('read', true);
            unread_count--;
            $count.text(unread_count);
            
            if( typeof callback !== 'undefined' ) callback();
            else                                  show_hide_miniwall_widget();
            
            return;
        }
        
        if( data.message !== 'OK' )
        {
            $item.unblock();
            
            console.warn(data.message);
            return;
        }
        
        $item.toggleClass('read', true);
        unread_count--;
        $count.text(unread_count);
        
        if( typeof callback !== 'undefined' ) callback();
        else                                  show_hide_miniwall_widget();
    });
}

function show_hide_miniwall_widget()
{
    var $container   = $('#news_miniwall_widget_container');
    var unread_count = $container.find('.items .news_miniwall_widget_item:not(.read)').length;
    
    if( unread_count === 0 )
    {
        $container.find('.mark_all').hide();
        $container.hide('slide', {direction: 'down'});
        $('body').toggleClass('with_nmw_widget', false);
    }
    else
    {
        $container.find('.mark_all').show();
        $container.show('slide', {direction: 'down'});
        $('body').toggleClass('with_nmw_widget', true);
    }
}

var nwm_unread_count = 0;
function mark_all_news_miniwall_items()
{
    stop_news_miniwall_fetcher();
    
    var $unread_items = $('#news_miniwall_widget_container').find('.items .news_miniwall_widget_item:not(.read)');
    nwm_unread_count  = $unread_items.length;
    
    $unread_items.each(function()
    {
        var $this = $(this);
        mark_nwm_item_as_read( $this.find('.closer')[0], conclude_news_miniwall_multimark );
    });
}

function conclude_news_miniwall_multimark()
{
    nwm_unread_count--;
    
    if( nwm_unread_count > 0 ) return;
    
    show_hide_miniwall_widget();
    start_news_miniwall_fetcher();
}

function set_read_id(id)
{
    var rids = $.cookie("nvm_read_items");
    if( ! rids ) rids = []; else rids = rids.split(',');
    
    rids.push(id);
    $.cookie("nvm_read_items", rids.join(','), {path: '/', expires: 365, domain: $_COOKIES_DOMAIN});
}

function clean_old_version_cookies()
{
    var cookies = $.cookie();
    for( var i in cookies )
        if( i.indexOf('nmw_item_read_') >= 0 )
            $.removeCookie(i);
}

function show_nmw_disable_prompt()
{
    var $dialog = $('#news_miniwall_hiding_prompt');
    if( $dialog.length === 0 ) return;
    
    $dialog.dialog({
        modal:    true,
        close:    function() { $(this).dialog('destroy'); },
        buttons:  [
            {
                text:  $dialog.attr('data-ok-caption'),
                icons: { primary: "ui-icon-check" },
                click: function() {
                    var $dialog    = $('#news_miniwall_hiding_prompt');
                    var $container = $('#news_miniwall_widget_container');
                    $dialog.closest('.ui-dialog').block(blockUI_medium_params);
                    set_engine_pref('@news_miniwall:enabled', 'false', function() {
                        stop_news_miniwall_fetcher();
                        $container.find('.mark_all').hide();
                        $container.hide('slide', {direction: 'down'});
                        $('body').toggleClass('with_nmw_widget', false);
                        $dialog.closest('.ui-dialog').unblock();
                        $dialog.dialog('close');
                    })
                }
            }, {
                text:  $dialog.attr('data-cancel-caption'),
                icons: { primary: "ui-icon-cancel" },
                click: function() { $(this).dialog('close'); }
            }
        ]
    })
}

$(document).ready(function()
{
    clean_old_version_cookies();
    fetch_news_miniwall_gfeeds()
    start_news_miniwall_fetcher();
});
