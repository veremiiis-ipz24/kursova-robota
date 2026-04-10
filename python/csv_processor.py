"""
python/csv_processor.py
Handles CSV validation, processing, and data preparation for import.
Can be called standalone or via PHP shell_exec.
"""

import csv
import json
import sys
import re
from io import StringIO
from datetime import datetime


REQUIRED_COLUMNS = {'first_name', 'last_name'}
OPTIONAL_COLUMNS = {'phone', 'email', 'note'}
ALL_COLUMNS = REQUIRED_COLUMNS | OPTIONAL_COLUMNS

# UTF-8 BOM — tells Excel/LibreOffice the file is UTF-8 so Cyrillic
# and other multibyte characters are displayed correctly.
UTF8_BOM = '\ufeff'


def validate_email(email: str) -> bool:
    if not email:
        return True  # optional field
    pattern = r'^[^@\s]+@[^@\s]+\.[^@\s]+$'
    return bool(re.match(pattern, email))


def validate_phone(phone: str) -> bool:
    if not phone:
        return True  # optional
    # Strip leading tab (used for text-forcing in exports) before validating
    phone = phone.lstrip('\t')
    pattern = r'^[\d\s\+\-\(\).]{4,20}$'
    return bool(re.match(pattern, phone))


def process_csv(content: str) -> dict:
    """
    Validate and process CSV content.
    Returns dict with 'contacts' (list) and 'errors' (list).
    """
    contacts = []
    errors = []

    # Strip UTF-8 BOM if present so header row parses cleanly
    content = content.lstrip(UTF8_BOM).lstrip('\ufeff')

    try:
        reader = csv.DictReader(StringIO(content.strip()))
        headers = set(reader.fieldnames or [])

        missing = REQUIRED_COLUMNS - headers
        if missing:
            return {
                'contacts': [],
                'errors': [f"Missing required columns: {', '.join(missing)}"]
            }

        for i, row in enumerate(reader, start=2):
            row_errors = []
            first_name = row.get('first_name', '').strip()
            last_name = row.get('last_name', '').strip()
            email = row.get('email', '').strip()
            # Strip leading tab that was added during export to force text formatting
            phone = row.get('phone', '').lstrip('\t').strip()
            note = row.get('note', '').strip()

            if not first_name:
                row_errors.append('first_name is required')
            if not last_name:
                row_errors.append('last_name is required')
            if not validate_email(email):
                row_errors.append(f'invalid email: {email}')
            if not validate_phone(phone):
                row_errors.append(f'invalid phone: {phone}')

            if row_errors:
                errors.append(f"Row {i}: {'; '.join(row_errors)}")
            else:
                contacts.append({
                    'first_name': first_name,
                    'last_name': last_name,
                    'phone': phone,
                    'email': email,
                    # Always store note as string so empty values are '' not None,
                    # keeping every field's UTF-8 context consistent.
                    'note': note,
                })

    except Exception as e:
        errors.append(f"Parse error: {str(e)}")

    return {'contacts': contacts, 'errors': errors}


def generate_csv(contacts: list) -> str:
    """
    Generate a UTF-8 BOM CSV string from a list of contact dicts.

    Two encoding fixes applied here:
    1. UTF-8 BOM prepended — Excel and LibreOffice read this as a signal to
       use UTF-8, so Cyrillic characters are displayed correctly instead of
       appearing as garbled symbols.
    2. Phone numbers prefixed with a tab character — spreadsheet apps detect
       the leading whitespace and keep the cell as text, preventing long digit
       strings from being silently converted to scientific notation
       (e.g. 380931000000 → 3.80931E+11).
    """
    # Use utf-8-sig writer so Python writes the BOM automatically
    output = StringIO()
    fieldnames = ['id', 'first_name', 'last_name', 'phone', 'email', 'note', 'created_at']
    writer = csv.DictWriter(output, fieldnames=fieldnames, extrasaction='ignore')
    writer.writeheader()
    for contact in contacts:
        row = dict(contact)
        # Force phone to text by prepending tab; preserve empty string for no phone
        phone = str(row.get('phone') or '')
        row['phone'] = ('\t' + phone) if phone else ''
        # Ensure note is always a string, never None
        row['note'] = str(row.get('note') or '')
        writer.writerow(row)

    # Prepend BOM to the finished CSV content
    return UTF8_BOM + output.getvalue()


if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'Usage: python csv_processor.py <action> [args]'}))
        sys.exit(1)

    action = sys.argv[1]

    if action == 'validate':
        content = sys.stdin.read()
        result = process_csv(content)
        print(json.dumps(result, ensure_ascii=False))

    elif action == 'generate':
        content = sys.stdin.read()
        contacts = json.loads(content)
        # Write bytes with BOM to stdout for piping to a file
        sys.stdout.buffer.write(generate_csv(contacts).encode('utf-8'))

    else:
        print(json.dumps({'error': f'Unknown action: {action}'}))
        sys.exit(1)
