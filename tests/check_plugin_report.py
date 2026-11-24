"""Fail releases on Plugin Check errors; retain all warnings for review."""
import json
import sys
from pathlib import Path
text = Path(sys.argv[1]).read_text()
rows = []
for line in text.splitlines():
    if line.strip().startswith('['):
        rows.extend(json.loads(line))
if not rows and 'Success' not in text:
    raise SystemExit('Plugin Check did not return a valid report')
errors = [row for row in rows if row.get('type') == 'ERROR']
print(f'Plugin Check: {len(errors)} errors; {len(rows) - len(errors)} warnings retained for review')
if errors:
    raise SystemExit(1)
