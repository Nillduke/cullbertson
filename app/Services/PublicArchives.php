<?php
namespace App\Services;
use App\Models\{Category,Tag,Author,TeamMember,FormDefinition};
class PublicArchives {
 public function render(string $slug){
  $copy=FormDefinition::data('archive-copy');$perPage=max(1,min(50,(int)($copy['perPage']??10)));$page=1;
  $data=['archiveCopy'=>$copy,'styles'=>['/archives.css?v='.filemtime(public_path('archives.css'))],'bodyClass'=>'content-page archive-page'];
  if(preg_match('~^agency-staff(?:/page/([1-9][0-9]*))?$~D',$slug,$match)){
   $page=(int)($match[1]??request()->integer('page',1));
   $members=TeamMember::with('page')->whereHas('page',fn($q)=>$q->published()->where('type','staff'))->orderBy('position')->orderBy('id')->paginate($perPage,['*'],'page',$page);
   abort_if($page<1 || $page>$members->lastPage(),404);
   $data['teamCopy']=FormDefinition::data('team-design');$data['styles'][]='/team.css?v='.filemtime(public_path('team.css'));
   return view('content.team-index',array_merge($data,['members'=>$members,'title'=>$copy['teamTitle'],'description'=>$copy['teamIntro'],'basePath'=>'/agency-staff','archivePosts'=>$members]));
  }
  if(!preg_match('~^(category|tag|author)/(.+?)(?:/page/([1-9][0-9]*))?$~D',$slug,$match))return null;
  [$all,$kind,$path]=$match;$page=(int)($match[3]??request()->integer('page',1));
  $class=match($kind){'category'=>Category::class,'tag'=>Tag::class,'author'=>Author::class};
  $record=$class::where('slug',basename($path))->firstOrFail();
  abort_unless($record->path()===$kind.'/'.$path,404);
  $posts=$record->pages()->published()->where('type','post')->with(['categoryTerms','tags'])->latest('published_at')->orderByDesc('pages.id')->paginate($perPage,['pages.*'],'page',$page);
  abort_if($page<1 || $page>$posts->lastPage(),404);
  return view('content.archive',array_merge($data,['title'=>$record->name,'description'=>strip_tags($record->description?:$copy['intro']),'archiveRecord'=>$record,'archiveKind'=>$kind,'archivePosts'=>$posts,'basePath'=>'/'.$record->path()]));
 }
}
