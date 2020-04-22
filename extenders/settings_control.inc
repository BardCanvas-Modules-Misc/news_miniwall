<?php
/**
 * Settings field type extender
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

use hng2_base\module;

global $config, $settings;

/** @var array $params [name => string, value => string, options => SimpleXMLElement] */
$params = $config->globals["specs_renderer/news_miniwall_control"];
?>

<div id="news_miniwall_items_container">
    
    <textarea class="target_value" wrap="off" name="names[<?= $params["name"] ?>]"
              style="display: none"><?= $params["value"] ?></textarea>
    
    <div class="target">
        <?
        if( ! empty($params["value"]) )
        {
            foreach(explode("\n", $params["value"]) as $line)
            {
                $line = trim($line);
                if( empty($line) ) continue;
                
                list($title, $url, $icon_path, $state) = explode("\t", $line);
                $toggle_on  = $state != "enabled" ? "display: none" : "";
                $toggle_off = $state == "enabled" ? "display: none" : "";
                $class      = $state != "enabled" ? "not_enabled"   : "";
                ?>
                <fieldset class="<?= $class ?>">
                    <input type="hidden" data-field="title" value="<?= htmlspecialchars($title) ?>">
                    <legend>
                        <?= $title ?>
                        <button onclick="remove_news_miniwall_item(this); return false;">
                            <i class="fa fa-trash"></i>
                            <?= $this_module->language->remove ?>
                        </button>
                    </legend>
                    <table class="nav_table_inner">
                        <tr>
                            <th nowrap><?= $this_module->language->form_fields->url->caption ?></th>
                            <td width="100%" nowrap>
                                <input type="url" data-field="url" maxlength="255"
                                       style="width: calc(100% - 26px)" onblur="rebuild_news_miniwall_items()"
                                       value="<?= htmlspecialchars($url) ?>">
                                <span class="inline-block nowrap">
                                    <span class='fa-pseudo-switch' data-field="state"
                                          data-value-on='enabled' data-value-off='disabled'
                                          data-current-value="<?= $state ?>"
                                          onclick="toggle_fa_pseudo_switch(this); rebuild_news_miniwall_items(); var $this = $(this); $this.closest('fieldset').toggleClass('not_enabled', $this.attr('data-current-value') !== 'enabled');">
                                        <input type='hidden' data-field='state' value='<?= $state ?>'>
                                        <span class='toggler toggle-on  fa fa-toggle-on'  style='<?= $toggle_on  ?>'></span>
                                        <span class='toggler toggle-off fa fa-toggle-off' style='<?= $toggle_off ?>'></span>
                                    </span>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th nowrap><?= $this_module->language->form_fields->icon->caption ?></th>
                            <td width="100%">
                                <div class="image_selector_container">
                                    <span class="icon_preview pseudo_link"
                                         onclick="set_news_miniwall_icon(this)">
                                        <img class="settings_image_selector"
                                             src="<?= $config->full_root_path ?><?= $icon_path ?>"
                                             data-empty-src="<?= $config->full_root_path ?>/media/missing_image.png"
                                             >
                                    </span>
                                    <span class="icon_data">
                                        <input type="text" readonly class="src_image_value" data-field="icon"
                                               value="<?= $icon_path ?>"><br>
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
                <?
            }
        }
        ?>
    </div>
    
    <div>
        <span class="framed_content inlined pseudo_link state_ok"
              onclick="add_news_miniwall_template()">
            <i class="fa fa-plus"></i>
            <?= $this_module->language->add_new_source ?>
        </span>
    </div>
    
</div>
