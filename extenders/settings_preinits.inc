<?php
/**
 * Settings field type extenders: preinits
 *
 * @package    BardCanvas
 * @subpackage News Miniwall
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 * 
 * Trailing globals:
 * @var module[] $modules
 * @var module   $current_module settings
 * @var module   $this_module    self
 */

global $language;

use hng2_base\module;
?>

<!-- News Miniwall Scripts -->

<style type="text/css">
    #news_miniwall_items_container .target                        { max-height: 60vh; overflow: auto; padding: 5px;
                                                                    border: 2px inset silver; border-radius: 5px;
                                                                    margin-bottom: 10px; }
    #news_miniwall_items_container .target fieldset               { margin-bottom: 10px; }
    #news_miniwall_items_container .target fieldset:last-child    { margin-bottom: 0; }
    #news_miniwall_items_container .target fieldset legend        { padding: 0 5px; background-color: white;
                                                                    border: 2px outset silver; border-radius: 5px; }
    #news_miniwall_items_container .target fieldset legend button { font-size: 7pt; padding: 0 3px; text-transform: uppercase; vertical-align: middle; }
    #news_miniwall_items_container .target fieldset.new           { box-shadow: 0 0 5px green; }
    #news_miniwall_items_container .target fieldset.not_enabled        { background-color: lightpink; }
    #news_miniwall_items_container .target fieldset.not_enabled legend { background-color: lightpink; }
    
    #news_miniwall_items_container .target .icon_preview     { display: inline-block; vertical-align: top; }
    #news_miniwall_items_container .target .icon_preview img { width: 42px; height: 42px; }
    #news_miniwall_items_container .target .icon_data        { display: inline-block; vertical-align: top;
                                                               padding-left: 5px; width: calc(100% - 46px); }
    #news_miniwall_items_container .target .icon_data .src_image_value { margin-bottom: 5px; }
    
</style>

<script type="text/javascript">
    
    function add_news_miniwall_template()
    {
        var html    = $('#news_miniwall_item_template').html();
        var $target = $('#news_miniwall_items_container').find('.target');
        $target.append(html);
        $target.scrollTo('max', 'fast');
    }
    
    function remove_news_miniwall_item(trigger)
    {
        if( ! confirm($_GENERIC_CONFIRMATION) ) return;
        
        $(trigger).closest('fieldset').hide('fast', function()
        {
            $(this).remove();
            rebuild_news_miniwall_items();
        })
    }
    
    function rebuild_news_miniwall_items()
    {
        var $container = $('#news_miniwall_items_container');
        var $textarea  = $container.find('textarea.target_value');
        var options    = [];
        
        $container.find('.target fieldset').each(function() {
            var $this   = $(this);
            options.push(sprintf(
                '%s\t%s\t%s\t%s',
                $this.find('input[data-field="title"]').val().trim().replace(/\|/g, ''),
                $this.find('input[data-field="url"]').val().trim().replace(/\|/g, '%7C'),
                $this.find('input[data-field="icon"]').val().trim().replace(/\|/g, '%7C'),
                $this.find('input[data-field="state"]').val().trim()
            ));
        });
        
        if( options.length > 0 ) $textarea.val(options.join('\n'));
        else                     $textarea.val('');
    }
    
    
    
    function set_news_miniwall_icon(trigger)
    {
        selected_image_trigger = trigger;
        return_image_size      = 'full';
        
        var url = $_FULL_ROOT_PATH
            + '/gallery/index.php'
            + '?embedded_mode=true'
            + '&search_type=' + 'image'
            + '&callback='    + 'parent.' + 'assign_news_miniwall_icon'
            + '&wasuuup='     + wasuuup();
        
        $('#image_selector_target').attr('src', url);
        $('#image_selector_dialog').dialog('open');
    }
    
    function assign_news_miniwall_icon(
        id_media, type, file_url, thumbnail_url, width, height, embed_width
    ) {
        if( type !== 'image' )
        {
            alert( invalid_type_for_image );
            rebuild_news_miniwall_items();
            
            return;
        }
    
        $('#image_selector_dialog').dialog('close');
        
        var image_to_return = return_image_size === 'full' ? file_url : thumbnail_url;
        var $container      = $(selected_image_trigger).closest('.image_selector_container');
        $container.find('input.src_image_value').val(image_to_return);
        $container.find('.settings_image_selector').attr('src', image_to_return);
        $container.find('.settings_image_selector').closest('a').attr('href', image_to_return);
        rebuild_news_miniwall_items();
    }
    
    function remove_news_miniwall_icon(trigger)
    {
        var $container = $(trigger).closest('.image_selector_container');
        var empty_src = $container.find('.settings_image_selector').attr('data-empty-src');
        $container.find('input.src_image_value').val('');
        $container.find('.settings_image_selector').attr('src', empty_src);
        rebuild_news_miniwall_items();
    }
</script>

<template id="news_miniwall_item_template">
    <fieldset class="new">
        <input type="hidden" data-field="state" value="enabled">
        <legend>
            <?= $this_module->language->add_new_source ?>
            <button onclick="$(this).closest('fieldset').hide('fast', function() { $(this).remove(); }); return false;">
                <i class="fa fa-ban"></i>
                <?= $language->cancel ?>
            </button>
        </legend>
        <table class="nav_table_inner">
            <tr>
                <th nowrap><?= $this_module->language->form_fields->title->caption ?></th>
                <td width="100%">
                    <input type="text" data-field="title" maxlength="32"
                           placeholder="<?= $this_module->language->form_fields->title->placeholder ?>"
                           style="width: 100%" onblur="rebuild_news_miniwall_items()">
                </td>
            </tr>
            <tr>
                <th nowrap><?= $this_module->language->form_fields->url->caption ?></th>
                <td width="100%">
                    <input type="url" data-field="url" maxlength="255"
                           style="width: 100%" onblur="rebuild_news_miniwall_items()">
                </td>
            </tr>
            <tr>
                <th nowrap><?= $this_module->language->form_fields->icon->caption ?></th>
                <td width="100%">
                    <div class="image_selector_container">
                        <span class="icon_preview">
                            <img class="settings_image_selector"
                                 src="<?= $config->full_root_path ?>/media/missing_image.png"
                                 data-empty-src="<?= $config->full_root_path ?>/media/missing_image.png"
                                 >
                        </span>
                        <span class="icon_data">
                            <input type="text" readonly class="src_image_value" data-field="icon"><br>
                            <span class="pseudo_link" onclick="set_news_miniwall_icon(this)">
                                <?= $this_module->language->set ?>
                            </span>
                            &nbsp;
                            <span class="pseudo_link" onclick="remove_news_miniwall_icon(this)">
                                <?= $this_module->language->remove ?>
                            </span>
                        </span>
                    </div>
                </td>
            </tr>
        </table>
    </fieldset>
</template>
