<?php

namespace App\Console\Commands;

use App\Services\WordPressLeadImporter;
use Illuminate\Console\Command;
use PDO;

class ImportWordPressLeads extends Command
{
    protected $signature='leads:import-wordpress {--config= : Absolute path to the old wp-config.php} {--dry-run} {--exclude=akshay,nillduke : Comma-separated test lead exclusions}';
    protected $description='Import historical WordPress leads without sending notifications';

    public function handle(WordPressLeadImporter $importer):int
    {
        $file=$this->option('config');if(!$file||!is_file($file)){$this->error('Provide an existing private WordPress configuration file.');return self::FAILURE;}
        $config=file_get_contents($file);
        $get=function($key)use($config){preg_match('~define\s*\(\s*[\'"]'.preg_quote($key,'~').'[\'"]\s*,\s*([\'"])(.*?)\1\s*\)~s',$config,$m);return $m[2]??'';};
        preg_match('~\$table_prefix\s*=\s*[\'"]([A-Za-z0-9_]+)[\'"]~',$config,$match);
        if(empty($match[1])||!$get('DB_NAME')){$this->error('Could not read the source database configuration.');return self::FAILURE;}
        $host=explode(':',$get('DB_HOST'));
        $pdo=new PDO('mysql:host='.$host[0].';port='.($host[1]??3306).';dbname='.$get('DB_NAME').';charset=utf8mb4',$get('DB_USER'),$get('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $report=$importer->import($pdo,$match[1],(bool)$this->option('dry-run'),array_map('trim',explode(',',$this->option('exclude'))));
        $this->line(json_encode($report,JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}
