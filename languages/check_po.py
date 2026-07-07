import re

def parse_po(path):
    with open(path, encoding='utf-8') as f:
        content = f.read()
    entries = {}
    blocks = re.split(r'\n\n+', content.strip())
    for block in blocks:
        mid = re.search(r'^msgid "(.*?)"', block, re.MULTILINE | re.DOTALL)
        mstr = re.search(r'^msgstr "(.*?)"', block, re.MULTILINE | re.DOTALL)
        if mid and mstr:
            key = mid.group(1)
            val = mstr.group(1)
            if key:
                entries[key] = val
    return entries

pot = parse_po(r'f:\Github\Ejtem social network\languages\social-network-6.pot')
fa  = parse_po(r'f:\Github\Ejtem social network\languages\social-network-6-fa_IR.po')
da  = parse_po(r'f:\Github\Ejtem social network\languages\social-network-6-da_DK.po')

print('=== Missing from FA ===')
for k in pot:
    if k not in fa or not fa[k]:
        print(repr(k))

print()
print('=== Missing from DA ===')
for k in pot:
    if k not in da or not da[k]:
        print(repr(k))
