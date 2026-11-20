<?php
namespace App\Mail;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content,Envelope,Headers};

class ConversationReply extends Mailable {
 public function __construct(public string $replyBody,public string $customerName,public string $reference,public string $mailSubject,public int $deliveryId,public string $messageDomain){}
 public function envelope():Envelope{return new Envelope(subject:$this->mailSubject);}
 public function content():Content{return new Content(view:'emails.conversation-reply');}
 public function headers():Headers{return new Headers(messageId:'culbertson-reply-'.$this->deliveryId.'@'.$this->messageDomain);}
}
