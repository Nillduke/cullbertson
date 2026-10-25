<?php
namespace App\Services;
use App\Models\MailSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class AdminMailTransport {
 public function configuration(MailSetting $s):array {
  if($s->provider==='log')return ['transport'=>'log','channel'=>'single'];
  return [
   'transport'=>'smtp','scheme'=>$s->encryption==='smtps'?'smtps':'smtp',
   'host'=>$s->host,'port'=>(int)$s->port,'username'=>$s->authentication?$s->username:null,
   'password'=>$s->authentication?$s->password:null,'auto_tls'=>$s->encryption!=='none',
   'require_tls'=>$s->encryption==='starttls','timeout'=>(int)$s->timeout,
   'local_domain'=>parse_url(config('app.url'),PHP_URL_HOST)?:'localhost',
  ];
 }
 public function send(MailSetting $s,array $recipients,Mailable $mail):?string {
  $mailer=Mail::build($this->configuration($s));$mail->from($s->from_address,$s->from_name);
  if($s->reply_to)$mail->replyTo($s->reply_to);
  $sent=$mailer->to($recipients)->send($mail);
  return $sent?->getMessageId();
 }
 public function check(MailSetting $s):void {
  if($s->provider==='log')throw new \RuntimeException('Log mode has no SMTP connection.');
  $transport=Mail::build($this->configuration($s))->getSymfonyTransport();
  try{$transport->start();}finally{$transport->stop();}
 }
 public static function safeError(\Throwable $e):string {
  $text=strtolower($e->getMessage());
  if($e instanceof \Illuminate\Contracts\Encryption\DecryptException)return 'The stored password cannot be decrypted. Enter it again in SMTP settings.';
  if(str_contains($text,'auth')||str_contains($text,'535'))return 'SMTP authentication failed. Check the username, password and provider SMTP access.';
  if(str_contains($text,'certificate')||str_contains($text,'tls')||str_contains($text,'ssl'))return 'TLS negotiation or certificate validation failed. Check the server name, port and encryption.';
  if(str_contains($text,'connect')||str_contains($text,'timed out')||str_contains($text,'resolve'))return 'Could not connect to the SMTP server. Check the host, port and hosting firewall.';
  return 'The mail server did not accept the message. Check sender, recipients and relay permissions.';
 }
}
