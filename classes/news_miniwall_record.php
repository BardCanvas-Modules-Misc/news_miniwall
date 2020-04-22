<?php
namespace hng2_modules\news_miniwall;

use hng2_repository\abstract_record;

class news_miniwall_record extends abstract_record
{
    public $item_id         = 0; # bigint unsigned not null default 0,
    public $item_source     = ""; # varchar(32) not null default '',
    
    public $item_url        = ""; # varchar(255) not null default '',
    public $item_title      = ""; # varchar(100) not null default '',
    public $item_excerpt    = ""; # varchar(255) not null default '',
    public $item_image_path = ""; # varchar(255) not null default '',
    public $date_published  = ""; # datetime not null,
    public $date_fetched    = ""; # datetime not null,
    
    public function set_new_id()
    {
        $this->item_id = make_unique_id("");
    }
}
