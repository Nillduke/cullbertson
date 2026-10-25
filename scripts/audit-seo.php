<?php
// Read-only HTTP-kernel crawl of every URL in the original migration inventory.
$options=getopt('',['output:','indexing:']);$root=dirname(__DIR__);
putenv('SESSION_DRIVER=array');putenv('CACHE_STORE=array');
require $root.'/vendor/autoload.php';$app=require $root.'/bootstrap/app.php';$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);$kernel->bootstrap();
if(isset($options['indexing']))config(['app.search_engine_indexing'=>$options['indexing']==='on']);
$records=json_decode(file_get_contents($root.'/database/import/wordpress-structure.json'),true)['expected_urls'];$cache=[];$result=[];
function uriOf(string $url):string{$p=parse_url($url);return ($p['path']??'/').(isset($p['query'])?'?'.$p['query']:'');}
function captureHead(string $html):array{
 $dom=new DOMDocument();$before=libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="UTF-8"?>'.$html);libxml_clear_errors();libxml_use_internal_errors($before);$x=new DOMXPath($dom);$meta=[];
 foreach($x->query('//head/meta[@name or @property]') as $node){$key=strtolower($node->getAttribute('name')?:$node->getAttribute('property'));if($key==='csrf-token')continue;$meta[$key][]=$node->getAttribute('content');}
 $links=[];foreach($x->query('//head/link') as $node)if(in_array($node->getAttribute('rel'),['canonical','alternate','prev','next']))$links[]=['rel'=>$node->getAttribute('rel'),'href'=>$node->getAttribute('href'),'hreflang'=>$node->getAttribute('hreflang')];
 $schemas=[];foreach($x->query('//script[@type="application/ld+json"]') as $node)$schemas[]=json_decode($node->textContent,true);
 $h1=[];foreach($x->query('//h1') as $node)$h1[]=trim(preg_replace('/\s+/u',' ',$node->textContent));
 $missingAlt=0;foreach($x->query('//main//img') as $node)if(!$node->hasAttribute('alt'))$missingAlt++;
 return ['title'=>$x->evaluate('string(//head/title)'),'meta'=>$meta,'links'=>$links,'schemas'=>$schemas,'lang'=>$x->evaluate('string(/html/@lang)'),'h1'=>$h1,'h1_count'=>count($h1),'missing_alt'=>$missingAlt];
}
foreach($records as $i=>$record){$uri=uriOf($record['url']);$current=$uri;$chain=[];$visited=[];
 while(true){if(isset($visited[$current]))throw new RuntimeException('Redirect loop '.$uri);$visited[$current]=true;
  if(!isset($cache[$current])){$request=Illuminate\Http\Request::create('http://localhost:8083'.$current);$response=$kernel->handle($request);$cache[$current]=['status'=>$response->getStatusCode(),'location'=>$response->headers->get('Location'),'x_robots_tag'=>$response->headers->get('X-Robots-Tag',''),'head'=>$response->isSuccessful()?captureHead($response->getContent()):null];$kernel->terminate($request,$response);}
  $response=$cache[$current];if($response['status']>=300&&$response['status']<400&&$response['location']){$next=uriOf($response['location']);$chain[]=['from'=>$current,'to'=>$next,'status'=>$response['status']];$current=$next;continue;}break;
 }
 $result[]=$record+['requested_uri'=>$uri,'final_uri'=>$current,'status'=>$response['status'],'redirects'=>$chain,'x_robots_tag'=>$response['x_robots_tag'],'head'=>$response['head']];
 if(($i+1)%100===0)fwrite(STDERR,'Checked '.($i+1).'/'.count($records)."\n");
}
file_put_contents($options['output'],json_encode(['indexing_enabled'=>config('app.search_engine_indexing'),'urls'=>$result],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));echo 'Captured '.count($result).' URLs and '.count($cache)." unique requests.\n";
