<?php
namespace hng2_modules\news_miniwall;

use hng2_base\account;
use hng2_media\media_repository;
use hng2_repository\abstract_repository;
use hng2_tools\cli_colortags;

class news_miniwall_repository extends abstract_repository
{
    protected $row_class                = 'hng2_modules\news_miniwall\news_miniwall_record';
    protected $table_name               = 'news_miniwall';
    protected $key_column_name          = 'item_id';
    protected $additional_select_fields = array();
    
    /**
     * @param $id
     *
     * @return news_miniwall_record|null
     * @throws \Exception
     */
    public function get($id)
    {
        return parent::get($id);
    }
    
    /**
     * @param array  $where
     * @param int    $limit
     * @param int    $offset
     * @param string $order
     * 
     * @return news_miniwall_record[]
     * @throws \Exception
     */
    public function find($where, $limit, $offset, $order)
    {
        return parent::find($where, $limit, $offset, $order);
    }
    
    /**
     * @param news_miniwall_record $record
     * 
     * @return int
     * @throws \Exception
     */
    public function save($record)
    {
        global $database;
        
        $this->validate_record($record);
        
        if( empty($record->item_source) ) return 0;
        if( empty($record->item_url) ) return 0;
        if( empty($record->item_title) ) return 0;
        
        if( empty($record->item_id) ) $record->set_new_id();
        if( empty($record->date_fetched) ) $record->date_fetched = date("Y-m-d H:i:s");
        if( empty($record->date_published) ) $record->date_published = date("Y-m-d H:i:s");
        
        $obj = $record->get_for_database_insertion();
        
        return $database->exec("
            insert ignore into $this->table_name set
            item_id         = '{$obj->item_id        }',
            item_source     = '{$obj->item_source    }',
            item_url        = '{$obj->item_url       }',
            item_title      = '{$obj->item_title     }',
            item_excerpt    = '{$obj->item_excerpt   }',
            item_image_path = '{$obj->item_image_path}', 
            date_published  = '{$obj->date_published }', 
            date_fetched    = '{$obj->date_fetched   }' 
        ");
    }
    
    /**
     * @param news_miniwall_record $record
     * 
     * @throws \Exception
     */
    public function validate_record($record)
    {
        if( ! $record instanceof news_miniwall_record )
            throw new \Exception(
                "Invalid object class! Expected: {$this->row_class}, received: " . get_class($record)
            );
    }
    
    /**
     * @param         $url
     * @param account $author
     * @param string  $referer
     *
     * @throws \Exception
     * @return string
     */
    public function fetch_remote_image($url, $author, $referer = "")
    {
        global $config;
        
        static $media_repository = null;
        if( is_null($media_repository) ) $media_repository = new media_repository();
        
        $filename  = basename(parse_url($url, PHP_URL_PATH));
        $parts     = explode(".", $filename);
        $extension = strtolower(end($parts));
    
        cli_colortags::write("<cyan>   Fetching «</cyan><light_cyan>$url</light_cyan><cyan>» ... </cyan>");
        
        if( ! in_array($extension, array("png", "jpg", "jpeg", "gif")) )
        {
            cli_colortags::write(
                "\n<yellow>   WARNING: $filename is not a valid image.</yellow>\n"
            );
            
            return "";
        }
        
        $directory = "{$config->datafiles_location}/tmp";
        if( ! is_dir($directory) )
        {
            if( ! @mkdir($directory, 0777, true) )
            {
                cli_colortags::write(
                    "\n<yellow>   WARNING: cannot create temporary directory for image saves.</yellow>\n"
                );
    
                return "";
            }
        }
        
        $filename = "nmwd-" . $filename;
        $path     = "$directory/$filename";
        $file     = array(
            "name"     => $filename,
            "type"     => "image/$extension",
            "tmp_name" => $path,
            "error"    => null,
            "size"     => 0,
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,            $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT,      "Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.77 Safari/537.36");
        curl_setopt($ch, CURLOPT_REFERER,        $referer);
        
        $contents = curl_exec($ch);
        
        $error = curl_error($ch);
        if( $error )
        {
            cli_colortags::write(
                "\n<yellow>   WARNING: Can't fetch image from {$url}:</yellow> <light_purple>{$error}</light_purple>\n"
            );
            
            return "";
        }
        
        curl_close($ch);
        
        if( empty($contents) )
        {
            cli_colortags::write(
                "\n<yellow>   WARNING: Image from {$url} is empty.</yellow>\n"
            );
            
            return "";
        }
        
        if( ! @file_put_contents($file["tmp_name"], $contents) )
        {
            cli_colortags::write(
                "\n<yellow>   WARNING: Cannot save image {$file["tmp_name"]}.</yellow>\n"
            );
            
            return "";
        }
        
        $file["size"] = filesize($file["tmp_name"]);
        
        $item_data = array(
            "title"          => str_replace(array("-", "_", "."), " ", $filename),
            "description"    => $url,
            "main_category"  => "0000000000000",
            "visibility"     => "public",
            "status"         => "published",
            "password"       => "",
            "allow_comments" => "1",
        );
        
        $res = $media_repository->receive_and_save($item_data, $file, true, true, $author);
        if( is_string($res) )
        {
            cli_colortags::write(
                "\n<yellow>   WARNING: Cannot save media item: </yellow> <light_purple>$res</light_purple>\n"
            );
            
            return "";
        }
    
        cli_colortags::write(
            "<light_green>OK!</light_green>\n"
        );
        return $res->path;
    }
    
    public function mark_item_as_read($item_id, $account_id)
    {
        global $database;
        
        $database->exec("
            insert ignore into news_miniwall_read_items
            set item_id = '$item_id', account_id = '$account_id'
        ");
    }
}
