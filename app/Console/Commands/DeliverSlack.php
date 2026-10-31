<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
class DeliverSlack extends Command {
 protected $signature='slack:deliver {--limit=20}';protected $description='Retry pending lead notifications to Slack';
 public function handle(\App\Services\LeadSlack $slack):int{$this->info('Processed '.$slack->pending(max(1,min(100,(int)$this->option('limit')))).' Slack notification(s).');return self::SUCCESS;}
}
