"""
Build translation files: fill missing strings + compile .po -> .mo
"""

import re, struct, os, sys

# ── 1. Missing translations ───────────────────────────────────────────────────

FA_EXTRA = {
    "6Arshid Social Community requires PHP %s or higher.":
        "شبکه اجتماعی ۶ به PHP نسخه %s یا بالاتر نیاز دارد.",
    "6Arshid Social Community requires WordPress %s or higher.":
        "شبکه اجتماعی ۶ به وردپرس نسخه %s یا بالاتر نیاز دارد.",
    "Invitation Limit":
        "محدودیت دعوت‌نامه",
    "Maximum invitations a member can send. 0 = unlimited.":
        "حداکثر تعداد دعوت‌نامه‌هایی که عضو می‌تواند ارسال کند. ۰ = نامحدود.",
    "Hello %s,":
        "سلام %s،",
    "%s cover photo":
        "عکس کاور %s",
    "Edit Cover":
        "ویرایش کاور",
    "Search members":
        "جستجوی اعضا",
    "Message":
        "پیام",
    "6arshid":
        "6arshid",
    "https://github.com/6arshid/social-network-6":
        "https://github.com/6arshid/social-network-6",
}

DA_EXTRA = {
    "6Arshid Social Community requires PHP %s or higher.":
        "6Arshid Social Community kræver PHP %s eller højere.",
    "6Arshid Social Community requires WordPress %s or higher.":
        "6Arshid Social Community kræver WordPress %s eller højere.",
    "Social Network Dashboard":
        "6Arshid Social Community Dashboard",
    "Activity Items":
        "Aktivitetselementer",
    "Pending Reports":
        "Afventende rapporter",
    "Welcome to 6Arshid Social Community!":
        "Velkommen til 6Arshid Social Community!",
    "A complete, secure, responsive, multilingual social network plugin for WordPress with profiles, activity streams, groups, messaging, notifications, and more.":
        "Et komplet, sikkert, responsivt og flersproget socialt netværk plugin til WordPress med profiler, aktivitetsstrømme, grupper, beskeder, notifikationer og meget mere.",
    "6arshid":
        "6arshid",
    "https://github.com/6arshid/social-network-6":
        "https://github.com/6arshid/social-network-6",
    "Members":
        "Medlemmer",
    "Groups":
        "Grupper",
    "Messages":
        "Beskeder",
    "Notifications":
        "Notifikationer",
    "Settings":
        "Indstillinger",
    "Dashboard":
        "Dashboard",
    "Profile":
        "Profil",
    "Search":
        "Søg",
    "Friends":
        "Venner",
    "Follow":
        "Følg",
    "Unfollow":
        "Afhølg",
    "Block":
        "Bloker",
    "Unblock":
        "Fjern blokering",
    "Report":
        "Rapportér",
    "Save":
        "Gem",
    "Cancel":
        "Annuller",
    "Delete":
        "Slet",
    "Edit":
        "Rediger",
    "Submit":
        "Send",
    "Loading…":
        "Indlæser…",
    "Error":
        "Fejl",
    "Success":
        "Succes",
    "Moderation":
        "Moderering",
    "Verification":
        "Verifikation",
    "Marketplace":
        "Markedsplads",
    "Stories":
        "Historier",
    "Activity":
        "Aktivitet",
    "Post":
        "Opslag",
    "Comment":
        "Kommentar",
    "Like":
        "Synes godt om",
    "Share":
        "Del",
    "Upload":
        "Upload",
    "Download":
        "Download",
    "Preview":
        "Forhåndsvisning",
    "Published":
        "Udgivet",
    "Draft":
        "Kladde",
    "Active":
        "Aktiv",
    "Inactive":
        "Inaktiv",
    "Enabled":
        "Aktiveret",
    "Disabled":
        "Deaktiveret",
    "Yes":
        "Ja",
    "No":
        "Nej",
    "Name":
        "Navn",
    "Email":
        "E-mail",
    "Password":
        "Adgangskode",
    "Username":
        "Brugernavn",
    "Bio":
        "Biografi",
    "Location":
        "Placering",
    "Website":
        "Hjemmeside",
    "Birthday":
        "Fødselsdag",
    "Gender":
        "Køn",
    "Avatar":
        "Avatar",
    "Cover":
        "Forside",
    "Edit Cover":
        "Rediger forside",
    "Search members":
        "Søg medlemmer",
    "Message":
        "Besked",
    "Hello %s,":
        "Hej %s,",
    "%s cover photo":
        "%s forsidebillede",
    "Invitation Limit":
        "Invitationsgrænse",
    "Maximum invitations a member can send. 0 = unlimited.":
        "Maksimalt antal invitationer et medlem kan sende. 0 = ubegrænset.",
    "6Arshid Social Community: Some required pages are missing.":
        "6Arshid Social Community: Nogle nødvendige sider mangler.",
}

# ── 2. PO parser ─────────────────────────────────────────────────────────────

def parse_po(path):
    """Return ordered list of (msgid, msgstr, raw_block) tuples."""
    with open(path, encoding='utf-8') as f:
        text = f.read()
    entries = []
    blocks = re.split(r'\n{2,}', text.strip())
    for block in blocks:
        mid = re.search(r'^msgid "((?:[^"\\]|\\.)*)"', block, re.MULTILINE | re.DOTALL)
        mst = re.search(r'^msgstr "((?:[^"\\]|\\.)*)"', block, re.MULTILINE | re.DOTALL)
        if mid and mst:
            entries.append((mid.group(1), mst.group(1), block))
    return entries

def unescape(s):
    return s.replace('\\n', '\n').replace('\\t', '\t').replace('\\"', '"').replace('\\\\', '\\')

def escape(s):
    return s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n').replace('\t', '\\t')

# ── 3. Fill missing strings in a PO file ─────────────────────────────────────

def fill_po(po_path, extra_dict):
    entries = parse_po(po_path)
    existing_ids = {unescape(mid) for mid, _, _ in entries}

    with open(po_path, encoding='utf-8') as f:
        current = f.read()

    added = 0
    for msgid_raw, translation in extra_dict.items():
        if msgid_raw not in existing_ids:
            block = f'\nmsgid "{escape(msgid_raw)}"\nmsgstr "{escape(translation)}"\n'
            current = current.rstrip() + '\n' + block
            added += 1
        else:
            # Update empty msgstr
            pattern = r'(msgid "' + re.escape(escape(msgid_raw)) + r'"\nmsgstr )""\n'
            replacement = r'\1"' + escape(translation) + '"\n'
            new_content = re.sub(pattern, replacement, current)
            if new_content != current:
                current = new_content
                added += 1

    with open(po_path, 'w', encoding='utf-8', newline='\n') as f:
        f.write(current)
    print(f"  {os.path.basename(po_path)}: {added} entries added/updated")

# ── 4. MO compiler ───────────────────────────────────────────────────────────

def compile_mo(po_path, mo_path):
    entries = parse_po(po_path)

    # Build list of (msgid_bytes, msgstr_bytes) — skip empty msgid (header handled separately)
    pairs = []
    header_str = ""
    for mid, mst, _ in entries:
        mid_u = unescape(mid)
        mst_u = unescape(mst)
        if mid_u == "":
            header_str = mst_u
            continue
        if mst_u:
            pairs.append((mid_u.encode('utf-8'), mst_u.encode('utf-8')))

    # Add header as first entry (empty key)
    if header_str:
        pairs.insert(0, (b"", header_str.encode('utf-8')))
    else:
        pairs.insert(0, (b"", b""))

    pairs.sort(key=lambda p: p[0])  # sort by original string
    N = len(pairs)

    # Offsets:
    # header: 7 * 4 = 28 bytes
    # original string table: N * 8 bytes
    # translation string table: N * 8 bytes
    # then the string data
    orig_table_offset = 28
    trans_table_offset = orig_table_offset + N * 8
    strings_offset = trans_table_offset + N * 8

    orig_strings = b""
    trans_strings = b""
    orig_table = []
    trans_table = []

    for orig, trans in pairs:
        orig_table.append((len(orig), strings_offset + len(orig_strings)))
        orig_strings += orig + b"\x00"
        trans_table.append((len(trans), strings_offset + len(orig_strings) + len(trans_strings)))
        trans_strings += trans + b"\x00"

    # Recalculate trans offsets (they come after orig_strings in data)
    trans_strings_offset = strings_offset + len(orig_strings)
    trans_table = []
    off = trans_strings_offset
    for _, trans in pairs:
        trans_table.append((len(trans), off))
        off += len(trans) + 1

    buf = struct.pack('<IIIIIII',
        0x950412de,     # magic (little-endian)
        0,              # revision
        N,              # number of strings
        orig_table_offset,
        trans_table_offset,
        0,              # no hash table
        28 + N * 16,    # hash offset (unused)
    )

    for length, offset in orig_table:
        buf += struct.pack('<II', length, offset)

    for length, offset in trans_table:
        buf += struct.pack('<II', length, offset)

    buf += orig_strings
    buf += trans_strings

    with open(mo_path, 'wb') as f:
        f.write(buf)

    print(f"  {os.path.basename(mo_path)}: {N} strings compiled")

# ── 5. Run ────────────────────────────────────────────────────────────────────

base = r"f:\Github\Ejtem social network\languages"

print("Filling missing translations...")
fill_po(os.path.join(base, "social-network-6-fa_IR.po"), FA_EXTRA)
fill_po(os.path.join(base, "social-network-6-da_DK.po"), DA_EXTRA)

print("\nCompiling .mo files...")
compile_mo(
    os.path.join(base, "social-network-6-fa_IR.po"),
    os.path.join(base, "social-network-6-fa_IR.mo")
)
compile_mo(
    os.path.join(base, "social-network-6-da_DK.po"),
    os.path.join(base, "social-network-6-da_DK.mo")
)

print("\nDone!")
