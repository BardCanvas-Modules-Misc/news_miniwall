<?php
/**
 * Module installation file
 *
 * @package    BardCanvas
 * @subpackage News Miniwall
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 * 
 * Trailing vars:
 * @var string $module_install_action install, uninstall, enable, disable
 * @var array  $messages
 * @var array  $errors
 * @var module $this_module
 * @var module $current_module
 */

use hng2_base\module;

$tables = array(
    "news_miniwall" => "
        create table if not exists news_miniwall (
            
            item_id           bigint unsigned not null default 0,
            item_source       varchar(32) not null default '',
            
            item_url          varchar(255) not null default '',
            item_title        varchar(100) not null default '',
            item_excerpt      varchar(255) not null default '',
            item_image_path   varchar(255) not null default '', 
            date_published    datetime not null,
            date_fetched      datetime not null,
            
            primary key     ( item_id ),
            index   by_url  ( item_source, item_url ),
            index   by_date ( item_source, date_fetched )
            
        ) engine=InnoDB default charset=utf8mb4 collate='utf8mb4_unicode_ci'
    ",
    "news_miniwall_read_items" => "
        create table if not exists news_miniwall_read_items (
            
            item_id     bigint unsigned not null default 0,
            account_id  bigint unsigned not null default 0,
            
            primary key ( item_id, account_id )
            
        ) engine=InnoDB default charset=utf8mb4 collate='utf8mb4_unicode_ci'
    ",
);

if( $module_install_action == "install" )
{
    foreach($tables as $table_name => $query)
    {
        try
        {
            $database->exec($query);
            $messages[] = replace_escaped_vars($language->install_messages->table_created_ok, '{$table_name}', $table_name);
        }
        catch( \Exception $e )
        {
            $errors[] = replace_escaped_vars(
                $language->install_messages->table_created_ko,
                array( '{$table_name}', '{$error}' ),
                array(   $table_name,     $e->getMessage()  )
            );
            
            return;
        }
    }
    
    return;
}

if( $module_install_action == "uninstall" )
{
    foreach($tables as $table_name => $query)
    {
        try
        {
            $database->exec("DROP TABLE if exists {$table_name}");
            $messages[] = replace_escaped_vars($language->install_messages->table_deleted_ok, '{$table_name}', $table_name);
        }
        catch( \Exception $e )
        {
            $errors[] = replace_escaped_vars(
                $language->install_messages->table_deleted_ko,
                array( '{$table_name}', '{$error}' ),
                array(   $table_name,     $e->getMessage() )
            );
        }
    }
    
    return;
}
