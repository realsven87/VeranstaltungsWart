import html,json,re,sys,urllib.request,urllib.parse
base='http://127.0.0.1:8080/'
f=json.load(open('/tmp/vw-visibility.json'))
def get(query): return urllib.request.urlopen(base+'?'+urllib.parse.urlencode(query)).read().decode()
page=get({'p':f['hidden']})
assert 'Visibility details' in page and 'name="vw_reg_nonce_field"' in page
robots=re.findall(r'<meta\b[^>]*name=[\'\"]robots[\'\"][^>]*>',page,re.I)
assert len(robots)==1 and 'noindex' in robots[0] and not re.search(r'[\s,\"\']index[\s,\"\']',robots[0]),robots
public=get({'p':f['visible']})
assert not any('noindex' in r for r in re.findall(r'<meta\b[^>]*name=[\'\"]robots[\'\"][^>]*>',public,re.I))
if len(sys.argv)>1:
 sitemap=get({'sitemap':'vw_event','sitemap_n':1})
 assert '<urlset' in sitemap and (html.escape(f['hidden_url'])+'</loc>') not in sitemap and (html.escape(f['visible_url'])+'</loc>') in sitemap,sitemap[:1000]
 print('PASS: '+sys.argv[1]+' single robots tag and actual XML sitemap')
 sys.exit()
for query in [{'post_type':'vw_event'},{'s':'VisibilityFixture'},{'vw_event_category':'visibility-category'},{'feed':'rss2','post_type':'vw_event'}]:
 text=get(query)
 assert 'VisibilityFixture hidden' not in text,query
 assert 'VisibilityFixture visible' in text,query
rest=json.loads(get({'rest_route':'/wp/v2/search','search':'VisibilityFixture','subtype':'vw_event'}))
assert f['hidden'] not in [p['id'] for p in rest]
nonce=re.search(r'name="vw_reg_nonce_field" value="([^"]+)"',page).group(1)
data=dict(action='vw_submit_registration',event_id=f['hidden'],vw_reg_nonce_field=nonce,first_name='Direct',last_name='Visitor',email='directvisitor@example.org',guests='0',gdpr_consent='1')
response=urllib.request.urlopen(base+'wp-admin/admin-post.php',urllib.parse.urlencode(data).encode())
assert 'reg=pending' in response.url,response.url
print('PASS: anonymous direct link, registration, archives, taxonomy, search, feed and REST search')
