<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\LeadMail;
class DeliverAgencyMail extends Command {
 protected $signature='mail:deliver {--limit=20}';
 protected $description='Process pending or retryable agency email notifications';
 public function handle(LeadMail $mail):int {$count=$mail->pending(max(1,min(100,(int)$this->option('limit'))));$this->info('Processed '.$count.' notification(s).');return self::SUCCESS;}
}
