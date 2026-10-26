<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use PDO;

class WordPressLeadImporter
{
    public function import(PDO $source,string $prefix,bool $dryRun=false,array $exclude=[]): array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/',$prefix))throw new \InvalidArgumentException('Invalid source table prefix.');
        $source->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        $forms=[];
        foreach($source->query("SELECT f.*,m.display_meta FROM {$prefix}gf_form f LEFT JOIN {$prefix}gf_form_meta m ON m.form_id=f.id") as $form)$forms[$form['id']]=$form;
        $meta=[];$notes=[];$values=[];
        foreach($source->query("SELECT * FROM {$prefix}gf_entry_meta ORDER BY id") as $row)$meta[$row['entry_id']][]=$row;
        foreach($source->query("SELECT * FROM {$prefix}gf_entry_notes ORDER BY id") as $row)$notes[$row['entry_id']][]=$row;
        foreach($source->query("SELECT * FROM {$prefix}e_submissions_values ORDER BY id") as $row)$values[$row['submission_id']][]=$row;
        $report=['source'=>0,'imported'=>0,'duplicates'=>0,'excluded'=>0,'quotes'=>0,'contacts'=>0,'partial'=>0,'spam'=>0,'trash'=>0];
        $run=function()use($source,$prefix,$forms,$meta,$notes,$values,$exclude,$dryRun,&$report){
            foreach($source->query("SELECT * FROM {$prefix}gf_entry ORDER BY id") as $entry){
                $form=$forms[$entry['form_id']]??['title'=>'Deleted form #'.$entry['form_id'],'display_meta'=>'{}'];
                $definition=json_decode($form['display_meta']??'{}',true)?:[];
                $raw=$meta[$entry['id']]??[];$fields=[];$answers=[];$normalized=[];
                foreach($raw as $value)$answers[(string)$value['meta_key']][]=$value['meta_value'];
                foreach($definition['fields']??[] as $field){
                    $id=(string)$field['id'];$label=trim(strip_tags($field['label']??''))?:'Field '.$id;
                    $parts=[];
                    if(isset($answers[$id]))$parts[] = implode('; ',$answers[$id]);
                    foreach($field['inputs']??[] as $input){$key=(string)$input['id'];if(isset($answers[$key])){$text=implode('; ',$answers[$key]);$parts[]=$text;$fields[$label.' — '.($input['label']??$key).' (#'.$key.')']=$text;}}
                    if(!$parts)continue;
                    $text=trim(implode(' ',array_filter($parts,fn($v)=>$v!=='')));
                    $fields[$label.' (#'.$id.')']=isset($answers[$id])?implode('; ',$answers[$id]):$text;
                    $type=$field['type']??'';
                    if($type==='name'||preg_match('/^(?:full |your |contact )?name$/i',$label))$normalized['name']??=$text;
                    if($type==='email'||preg_match('/email/i',$label))$normalized['email']??=$text;
                    if($type==='phone'||preg_match('/phone|telephone|mobile/i',$label))$normalized['phone']??=$text;
                    if($type==='textarea'||preg_match('/message|comments|question/i',$label))$normalized['message']??=$text;
                }
                $flat=[];foreach($answers as $key=>$items)$flat[$key]=count($items)===1?$items[0]:$items;
                $partial=array_key_exists('partial_entry_percent',$flat)&&$flat['partial_entry_percent']!=='';
                $title=$form['title'];$service=$this->service($title);$kind=$service?'quotes':'contacts';
                $wp=['source'=>'Gravity Forms','entry_id'=>$entry['id'],'form_id'=>$entry['form_id'],'form_name'=>$title,'original_status'=>$entry['status'],'fields'=>$fields,'entry'=>$entry,'meta'=>$raw,'notes'=>$notes[$entry['id']]??[]];
                $payload=$normalized+['topic'=>$title,'wordpress'=>$wp];
                if($kind==='quotes'){$payload['answers']=$normalized;$payload['serviceName']=preg_replace('/\s+(?:quote.*|form.*)$/i','',$title);}
                $this->store('WP-GF-'.$entry['id'],$kind,$service,$payload,$entry['status'],(bool)$entry['is_read'],$entry['date_created'],$entry['date_updated']?:$entry['date_created'],$partial,(int)($flat['partial_entry_percent']??100),$exclude,$dryRun,$report);
            }
            foreach($source->query("SELECT * FROM {$prefix}e_submissions ORDER BY id") as $entry){
                $fields=[];$normalized=[];
                foreach($values[$entry['id']]??[] as $value){$key=$value['key'];$fields[$key]=$value['value'];foreach(['name','email','phone','message'] as $field)if(preg_match('/'.$field.'/i',$key))$normalized[$field]??=$value['value'];}
                $title=$entry['form_name']?:'Elementor form';
                $payload=$normalized+['topic'=>$title,'wordpress'=>['source'=>'Elementor','entry_id'=>$entry['id'],'form_name'=>$title,'original_status'=>$entry['status'],'fields'=>$fields,'entry'=>$entry,'values'=>$values[$entry['id']]??[]]];
                $created=$entry['created_at_gmt']?:$entry['created_at'];$updated=$entry['updated_at_gmt']?:$entry['updated_at'];
                $this->store('WP-EL-'.$entry['id'],'contacts',null,$payload,$entry['status'],(bool)$entry['is_read'],$created,$updated,false,100,$exclude,$dryRun,$report);
            }
        };
        if($dryRun)$run();else DB::transaction($run);
        return $report;
    }

    private function service(string $title):?string
    {
        foreach(['classic|collector'=>'classic-car','auto|car insurance'=>'auto','home insurance|homeowners'=>'home','condo'=>'condo','renter'=>'renters','flood'=>'flood','umbrella'=>'umbrella','boat'=>'boat','motorcycle'=>'motorcycle','golf'=>'golf-cart','life insurance'=>'life','rv|mobile home'=>'rv'] as $pattern=>$key)if(preg_match('/'.$pattern.'/i',$title))return $key;
        return null;
    }

    private function store(string $reference,string $kind,?string $service,array $payload,string $original,bool $read,string $created,string $updated,bool $partial,int $progress,array $exclude,bool $dryRun,array &$report):void
    {
        $report['source']++;
        $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
        foreach($exclude as $word)if($word!==''&&stripos($json,$word)!==false){$report['excluded']++;return;}
        $table=$kind==='quotes'?'quote_requests':'contact_enquiries';
        if(DB::table($table)->where('reference',$reference)->exists()){$report['duplicates']++;return;}
        $status=in_array($original,['spam','trash'])?$original:($read?'reviewed':'new');
        $row=['reference'=>$reference,'payload'=>$json,'status'=>$status,'created_at'=>$created,'updated_at'=>$updated];
        if($kind==='quotes')$row+=['service'=>$service,'completion_status'=>$partial?'incomplete':'completed','progress_percent'=>$partial?max(0,min(100,$progress)):100,'last_step_label'=>$partial?'Imported WordPress partial entry':null,'completed_at'=>$partial?null:$created];
        // Query builder deliberately avoids model observers: historical leads must not email anyone.
        if(!$dryRun)DB::table($table)->insert($row);
        $report['imported']++;$report[$kind]++;if($partial)$report['partial']++;if(in_array($status,['spam','trash']))$report[$status]++;
    }
}
