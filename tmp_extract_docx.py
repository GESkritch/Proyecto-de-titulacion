from pathlib import Path
import zipfile
from xml.etree import ElementTree as ET

paths = [
    Path(r'c:\Users\naxit\OneDrive\Iplacex\Proyecto de titulacion\Proyecto de titulacion\Mauricio_Carrillo.docx'),
    Path(r'c:\Users\naxit\OneDrive\Iplacex\Proyecto de titulacion\Proyecto de titulacion\Mauricio_Carrillo_Fuentes.docx'),
]

for path in paths:
    print(f'===== {path.name} =====')
    with zipfile.ZipFile(path) as z:
        xml = z.read('word/document.xml')
    root = ET.fromstring(xml)
    ns = {'w': 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'}
    texts = []
    for p in root.findall('.//w:p', ns):
        parts = []
        for t in p.findall('.//w:t', ns):
            if t.text:
                parts.append(t.text)
        if parts:
            texts.append(''.join(parts))
    print('\n'.join(texts))
    print('\n')
