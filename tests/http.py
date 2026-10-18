"""Exercise real anonymous admin-post requests against the isolated test site."""
import concurrent.futures
import html
import json
import re
import time
import urllib.parse
import urllib.request
BASE = 'http://127.0.0.1:8080'
event = json.load(open('/tmp/vw-http-fixture.json'))['id']
for attempt in range(30):
    try:
        page = urllib.request.urlopen(BASE + '/?p=' + str(event)).read().decode()
        break
    except OSError:
        time.sleep(.2)
else:
    raise RuntimeError('Test WordPress did not start')
match = re.search(r'name="vw_reg_nonce_field" value="([^"]+)"', page)
assert match, 'Form nonce missing'
nonce = html.unescape(match.group(1))
def submit(**changes):
    data = dict(action='vw_submit_registration', event_id=event, vw_reg_nonce_field=nonce,
                first_name='Élodie', last_name="O'Connor", email='http@example.org',
                guests='0', message='Test', gdpr_consent='1', website='')
    data.update(changes)
    response = urllib.request.urlopen(BASE + '/wp-admin/admin-post.php', urllib.parse.urlencode(data).encode())
    return urllib.parse.parse_qs(urllib.parse.urlparse(response.url).query)
assert submit(guests='1')['reg_error'] == ['invalid_guests']
assert submit(gdpr_consent='')['reg_error'] == ['invalid_data']
assert submit(message='')['reg_error'] == ['invalid_message']
assert submit(website='spam')['reg_error'] == ['invalid_data']
assert submit(vw_reg_nonce_field='invalid')['reg_error'] == ['nonce_fail']
assert submit()['reg'] == ['pending']
assert submit()['reg_error'] == ['duplicate']
assert submit(email='trusted@example.org')['reg'] == ['ok']
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
    results = list(pool.map(lambda _: submit(email='repeat@example.org'), range(4)))
assert sum(result.get('reg') == ['pending'] for result in results) == 1
assert sum(result.get('reg_error') == ['duplicate'] for result in results) == 3
print('PASS: HTTP validation, international names, duplicate protection and trusted registration')
