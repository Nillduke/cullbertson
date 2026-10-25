<?php
namespace App\Services;
use App\Models\Page;
use DOMDocument;
use DOMXPath;
class ArticleContent {
    public function render(Page $page):string {
        $html='';$removedTitle=false;$removedImage=false;
        $normal=fn($text)=>preg_replace('/[^\pL\pN]+/u','',mb_strtolower(html_entity_decode($text)));
        foreach($page->sections as $section){
            $dom=new DOMDocument('1.0','UTF-8');
            $previous=libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="UTF-8"><div id="article-content-root">'.$section->content_html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();libxml_use_internal_errors($previous);
            $xpath=new DOMXPath($dom);
            // Imported WordPress chrome is replaced by the shared database-backed article header.
            foreach(iterator_to_array($xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," content-page-heading ")] | //search')) as $node)$node->parentNode?->removeChild($node);
            foreach(iterator_to_array($xpath->query('//h1 | //h2')) as $node){
                if(!$removedTitle && $normal($node->textContent)===$normal($page->title)){$node->parentNode?->removeChild($node);$removedTitle=true;break;}
            }
            foreach(iterator_to_array($xpath->query('//img')) as $node){
                if(!$removedImage && parse_url($node->getAttribute('src'),PHP_URL_PATH)===parse_url($page->featured_image??'',PHP_URL_PATH)){$node->parentNode?->removeChild($node);$removedImage=true;break;}
            }
            // Remove legacy layout wrappers, keeping every editorial child node intact.
            foreach(iterator_to_array($xpath->query('//article[contains(concat(" ",normalize-space(@class)," ")," imported-content ")]')) as $node){while($node->firstChild)$node->parentNode->insertBefore($node->firstChild,$node);$node->parentNode->removeChild($node);}
            $root=$dom->getElementById('article-content-root');
            if($root)foreach($root->childNodes as $node)$html.=$dom->saveHTML($node);
        }
        return $html;
    }
}
