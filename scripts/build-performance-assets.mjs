import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath, pathToFileURL} from 'node:url';

// npm install, then npm run optimize:css. Original styles.css remains the editable source.
const compiler = process.argv[2] ? pathToFileURL(path.resolve(process.argv[2])).href : 'clean-css';
const {default: CleanCSS} = await import(compiler);
const postcssModule = process.argv[2] ? pathToFileURL(path.resolve(path.dirname(process.argv[2]), '../postcss/lib/postcss.js')).href : 'postcss';
const {default: postcss} = await import(postcssModule);
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const publicDir = path.join(root, 'public');
const manifest = JSON.parse(fs.readFileSync(path.join(publicDir, 'assets/optimized/manifest.json'), 'utf8'));
const css = fs.readFileSync(path.join(publicDir, 'styles.css'), 'utf8');
const start = css.indexOf('/* Customer shortcuts');
const imported = css.indexOf('/* Imported original');
const carriers = css.indexOf('/* Original Culbertson carrier');
const footer = css.indexOf('/* Photo-backed gradient footer');
if ([start, imported, carriers, footer].some(i=>i<0)) throw Error('Stylesheet sections changed; review shared bundle boundaries.');
const fontFace = css.match(/@font-face\{[^}]+\}/g)?.join('') || '';
const shared = css.slice(0,start)+fontFace+css.slice(imported,carriers)+css.slice(footer);
function optimize(source) {
  const backgrounds = new Map();
  const mapped = source.replace(/url\((['"]?)(\/?assets\/[^)'"\s]+)\1\)/g, (whole, quote, url) => {
    const entry = manifest['/'+url.replace(/^\//,'')];
    if (!entry) return whole;
    const variants = Object.entries(entry.variants);
    const variant = variants.find(([size])=>Number(size)>=1440)||variants.at(-1);
    const mobile = variants.find(([size])=>Number(size)>=768)||variants.at(-1);
    const key = '--site-photo-'+entry.hash;
    backgrounds.set(key,{desktop:variant[1],mobile:mobile[1]});
    return `var(${key})`;
  });
  const variables = ':root{'+[...backgrounds].map(([key,v])=>`${key}:url('${v.desktop}')`).join(';')+'}@media(max-width:640px){:root{'+[...backgrounds].map(([key,v])=>`${key}:url('${v.mobile}')`).join(';')+'}}';
  const result = new CleanCSS({level: 2, rebase: false}).minify(mapped+variables);
  if (result.errors.length) throw Error(result.errors.join('\n'));
  return result.styles;
}
for (const [name,source] of [['styles.home.min.css',css],['styles.shared.min.css',shared]]) {
  const result = optimize(source);
  fs.writeFileSync(path.join(publicDir,name),result);
  console.log(`${name}: ${Buffer.byteLength(result)} bytes`);
}
function critical(source,allowed) {
  const tree=postcss.parse(source);
  tree.walkComments(comment=>comment.remove());
  tree.walkRules(rule=>{
    const selectors=rule.selectors.filter(selector=>{
      const classes=[...selector.matchAll(/\.([\w-]+)/g)].map(m=>m[1]);
      if (/#(?!feedback-tab\b)/.test(selector)) return false;
      return classes.every(name=>allowed(name));
    });
    if (selectors.length) rule.selectors=selectors; else rule.remove();
  });
  tree.walkAtRules(rule=>{if(rule.nodes&&!rule.nodes.length)rule.remove();});
  return optimize(tree.toString()).replace(/@charset\s+[^;]+;/gi,'').replace(/url\((['"]?)assets\//g,'url($1/assets/');
}
const header=critical(shared,name=>/^(?:content-page|site-header|pro-header|utility[\w-]*|nav[\w-]*|desktop-nav|brand[\w-]*|menu-toggle|menu-icon[\w-]*|button[\w-]*|icon|skip-link|shell|scrolled|mobile-open|mega-trigger|mega-open|feedback-tab)$/.test(name));
const support=critical(fs.readFileSync(path.join(publicDir,'support-pages.css'),'utf8'),name=>/^(?:support-page|sp-shell|sp-hero[\w-]*|sp-breadcrumb|sp-eyebrow|sp-button[\w-]*|sp-photo-caption)$/.test(name));
for(const [name,content] of [['styles.header-critical.min.css',header],['styles.support-critical.min.css',support]]) {
  fs.writeFileSync(path.join(publicDir,name),content);
  console.log(`${name}: ${Buffer.byteLength(content)} bytes`);
}
