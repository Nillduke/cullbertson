"""Prepare a cPanel upload from tracked source and a sanitized local content DB."""
import argparse, json, re, shutil, sqlite3, subprocess, zipfile
from pathlib import Path

root=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser()
parser.add_argument('--output',required=True)
parser.add_argument('--zip',action='store_true',help='Package an already prepared staging folder with production vendor installed')
args=parser.parse_args()
out=Path(args.output).resolve()
if out.is_relative_to(root):raise SystemExit('Choose a staging directory outside the Git project.')
app=out/'culbertson_app'; public=out/'public_html'
private_tables=['contact_files','users','password_reset_tokens','sessions','cache','cache_locks','jobs','job_batches','failed_jobs','quote_requests','contact_enquiries','feedback_entries']
if not args.zip:
 if (app/'database/database.sqlite').exists():raise SystemExit('A prepared snapshot already exists; choose a new output directory.')
 out.mkdir(parents=True,exist_ok=True);app.mkdir(exist_ok=True);public.mkdir(exist_ok=True)
 tracked=subprocess.check_output(['git','ls-files','-z'],cwd=root).decode().split('\0')
 for name in filter(None,tracked):
  rel=Path(name)
  if rel.parts[0] in ['tests','scripts'] or name in ['phpunit.xml','start-local.ps1','LOCAL-SETUP.txt','.editorconfig','.gitattributes','.gitignore','package.json','vite.config.js']:continue
  if name.startswith('public/'):
   dest=public/Path(*rel.parts[1:])
  else:dest=app/rel
  dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(root/rel,dest)
 for folder in ['app/private','app/public','framework/cache/data','framework/sessions','framework/testing','framework/views','logs']:(app/'storage'/folder).mkdir(parents=True,exist_ok=True)
 (app/'bootstrap/cache').mkdir(parents=True,exist_ok=True)
 if (root/'public/assets/media').exists():shutil.copytree(root/'public/assets/media',public/'assets/media',dirs_exist_ok=True)
 else:(public/'assets/media').mkdir(parents=True,exist_ok=True)
 index=(public/'index.php').read_text();index=index.replace("__DIR__.'/../storage/","__DIR__.'/../culbertson_app/storage/").replace("__DIR__.'/../vendor/","__DIR__.'/../culbertson_app/vendor/").replace("__DIR__.'/../bootstrap/","__DIR__.'/../culbertson_app/bootstrap/").replace('$app->handleRequest(Request::capture());','$app->usePublicPath(__DIR__);\n$app->handleRequest(Request::capture());');(public/'index.php').write_text(index)
 source=sqlite3.connect(root/'database/database.sqlite');db=sqlite3.connect(app/'database/database.sqlite');source.backup(db);source.close()
 db.execute('PRAGMA secure_delete=ON');db.execute('PRAGMA foreign_keys=ON')
 tables={row[0] for row in db.execute("SELECT name FROM sqlite_master WHERE type='table'")}
 for table in private_tables:
  if table in tables:db.execute('DELETE FROM '+table)
  if 'sqlite_sequence' in tables:db.execute('DELETE FROM sqlite_sequence WHERE name=?',(table,))
 public_tables=['pages','page_sections','site_blocks','form_definitions','categories','tags','authors','team_members','url_redirects','legacy_query_redirects','media_assets']
 def normalize(value):
  if isinstance(value,str):return re.sub(r'https?://(?:localhost|127\.0\.0\.1)(?::[0-9]+)?(?=/)', '', value)
  if isinstance(value,list):return [normalize(x) for x in value]
  if isinstance(value,dict):return {k:normalize(v) for k,v in value.items()}
  return value
 changes=0
 for table in public_tables:
  columns=[r[1] for r in db.execute('PRAGMA table_info('+table+')')]
  for row in db.execute('SELECT * FROM '+table).fetchall():
   for column,value in zip(columns,row):
    if not isinstance(value,str):continue
    try:parsed=json.loads(value);replacement=json.dumps(normalize(parsed),ensure_ascii=False,separators=(',',':')) if isinstance(parsed,(list,dict)) else normalize(value)
    except (ValueError,TypeError):replacement=normalize(value)
    if value!=replacement:db.execute('UPDATE '+table+' SET "'+column+'"=? WHERE id=?',(replacement,row[columns.index('id')]));changes+=1
 db.commit();db.execute('VACUUM')
 assert not db.execute('PRAGMA foreign_key_check').fetchall()
 for table in private_tables:
  if table in tables:assert db.execute('SELECT COUNT(*) FROM '+table).fetchone()[0]==0
 (out/'content-snapshot-check.json').write_text(json.dumps({'pages':db.execute('SELECT COUNT(*) FROM pages').fetchone()[0],'private_tables_empty':True,'normalized_text_fields':changes},indent=2));db.close()
 shutil.copy2(root/'DEPLOYMENT.txt',out/'READ-ME-FIRST.txt')
 print('Staging ready. Install production Composer dependencies in '+str(app)+' before packaging.')
else:
 if not (app/'vendor/autoload.php').is_file():raise SystemExit('Production Composer vendor folder is missing.')
 if (app/'.env').exists():raise SystemExit('Do not include an environment secret file in this archive.')
 for cache in (app/'bootstrap/cache').glob('*.php'):cache.unlink()
 for base in ['framework/cache/data','framework/sessions','framework/views','framework/testing','logs']:
  for file in (app/'storage'/base).rglob('*'):
   if file.is_file() and file.name!='.gitignore':file.unlink()
 db=sqlite3.connect(app/'database/database.sqlite')
 for table in private_tables:db.execute('DELETE FROM '+table)
 db.commit();db.execute('VACUUM');db.close()
 archive=out/'culbertson-cpanel-upload.zip'
 with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED,compresslevel=6) as z:
  for folder in [app,public]:
   for file in sorted(folder.rglob('*')):
    if file.is_file():z.write(file,file.relative_to(out).as_posix())
    elif not any(file.iterdir()):z.writestr(file.relative_to(out).as_posix()+'/',b'')
  z.write(out/'READ-ME-FIRST.txt','READ-ME-FIRST.txt')
 print('Archive ready: '+str(archive))
