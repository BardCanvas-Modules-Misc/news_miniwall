<?php
/**
 * Pre-rendering template extender, only for preferences page.
 * Removes widget switch if the user level is below the allowed.
 *
 * @package    BardCanvas
 * @subpackage News Miniwall
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 */

global $config, $settings;

if( ! $account->_exists ) return;
if( $current_module->name != "accounts" ) return;
if( basename($_SERVER["SCRIPT_NAME"]) != "preferences.php" ) return;
if( empty($config->globals["editable_prefs_collection"]) ) return;
if( ! is_array($config->globals["editable_prefs_collection"]) ) return;

$min_level = (int) $settings->get("modules:news_miniwall.disabling_level");
if( $account->level >= $min_level ) return;

foreach($config->globals["editable_prefs_collection"] as $key => $specs)
{
    foreach($specs as $skey => $sxml)
    {
        if( $skey == "@news_miniwall:enabled" )
        {
            unset($config->globals["editable_prefs_collection"][$key]);
            
            return;
        }
    }
}
