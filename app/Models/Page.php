<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Page extends Model
{
    protected $guarded = ['id'];
    public function categoryTerms(){return $this->belongsToMany(Category::class,'category_page');}
    public function tags(){return $this->belongsToMany(Tag::class,'page_tag');}
    public function author(){return $this->belongsTo(Author::class);}
    public function teamMember(){return $this->hasOne(TeamMember::class);}
    protected function casts(): array { return ['categories'=>'array','presentation'=>'array','insurance_content'=>'array','published_at'=>'datetime']; }
    public function sections(): HasMany { return $this->hasMany(PageSection::class)->orderBy('position')->orderBy('id'); }
    public function scopePublished($query) { return $query->where('status','published')->where(fn($q)=>$q->whereNull('published_at')->orWhere('published_at','<=',now())); }
    public function categoryLabels():array {$labels=$this->categoryTerms->pluck('name')->merge($this->tags->pluck('name'))->unique()->values()->all();return $labels?:($this->categories?:['Insurance']);}
    public function article(): array { return ['id'=>$this->id,'title'=>$this->title,'url'=>'/'.$this->slug.'/','image'=>$this->featured_image,'categories'=>$this->categoryLabels(),'published'=>$this->published_at?->toDateTimeString()]; }
}
