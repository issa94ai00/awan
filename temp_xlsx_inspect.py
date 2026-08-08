import zipfile
import xml.etree.ElementTree as ET
path = 'daily.xlsx'
with zipfile.ZipFile(path) as z:
    print('files:')
    for name in z.namelist():
        print(' ', name)
    shared = z.read('xl/sharedStrings.xml')
    ss = ET.fromstring(shared)
    ns = {'a': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
    strings = []
    for si in ss.findall('a:si', ns):
        t = si.find('a:t', ns)
        if t is not None:
            strings.append(t.text or '')
        else:
            text = ''
            for r in si.findall('a:r', ns):
                t2 = r.find('a:t', ns)
                text += t2.text or ''
            strings.append(text)
    sheet = z.read('xl/worksheets/sheet1.xml')
    root = ET.fromstring(sheet)
    rows = root.findall('.//a:row', ns)
    print('rows=', len(rows))
    for idx, row in enumerate(rows[:7]):
        cells = []
        for c in row.findall('a:c', ns):
            r = c.attrib.get('r')
            t = c.attrib.get('t')
            v = c.find('a:v', ns)
            val = v.text if v is not None else ''
            if t == 's':
                val = strings[int(val)] if val.isdigit() and int(val) < len(strings) else val
            cells.append(f'{r}={val}')
        print('ROW', idx, ' '.join(cells))
