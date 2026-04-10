"""
tests/test_csv_processor.py
Unit tests for the CSV processor module using unittest.
"""

import sys
import os
import unittest
import json

sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'python'))
from csv_processor import process_csv, generate_csv, validate_email, validate_phone


class TestContactCreation(unittest.TestCase):
    """Tests for contact creation via CSV parsing."""

    def test_valid_contact_minimal(self):
        csv = "first_name,last_name\nJohn,Doe"
        result = process_csv(csv)
        self.assertEqual(len(result['contacts']), 1)
        self.assertEqual(result['contacts'][0]['first_name'], 'John')
        self.assertEqual(result['contacts'][0]['last_name'], 'Doe')
        self.assertEqual(result['errors'], [])

    def test_valid_contact_full(self):
        csv = "first_name,last_name,phone,email,note\nJane,Smith,+48123456789,jane@example.com,VIP"
        result = process_csv(csv)
        self.assertEqual(len(result['contacts']), 1)
        c = result['contacts'][0]
        self.assertEqual(c['first_name'], 'Jane')
        self.assertEqual(c['last_name'], 'Smith')
        self.assertEqual(c['phone'], '+48123456789')
        self.assertEqual(c['email'], 'jane@example.com')
        self.assertEqual(c['note'], 'VIP')

    def test_multiple_contacts(self):
        csv = "first_name,last_name,email\nAlice,A,alice@a.com\nBob,B,bob@b.com\nCarol,C,"
        result = process_csv(csv)
        self.assertEqual(len(result['contacts']), 3)
        self.assertEqual(result['errors'], [])

    def test_missing_first_name(self):
        csv = "first_name,last_name\n,Doe"
        result = process_csv(csv)
        self.assertEqual(len(result['contacts']), 0)
        self.assertEqual(len(result['errors']), 1)
        self.assertIn('first_name', result['errors'][0])

    def test_missing_last_name(self):
        csv = "first_name,last_name\nJohn,"
        result = process_csv(csv)
        self.assertEqual(len(result['contacts']), 0)
        self.assertIn('last_name', result['errors'][0])

    def test_missing_required_columns(self):
        csv = "phone,email\n+123,test@test.com"
        result = process_csv(csv)
        self.assertEqual(len(result['contacts']), 0)
        self.assertTrue(len(result['errors']) > 0)
        self.assertIn('first_name', result['errors'][0])

    def test_whitespace_trimmed(self):
        csv = "first_name,last_name\n  John  ,  Doe  "
        result = process_csv(csv)
        self.assertEqual(result['contacts'][0]['first_name'], 'John')
        self.assertEqual(result['contacts'][0]['last_name'], 'Doe')

    def test_partial_valid_rows(self):
        csv = "first_name,last_name,email\nGood,Row,good@email.com\n,Bad,\nAlso,Good,"
        result = process_csv(csv)
        self.assertEqual(len(result['contacts']), 2)
        self.assertEqual(len(result['errors']), 1)


class TestContactSearch(unittest.TestCase):
    """Tests for search-related logic in CSV processing."""

    def test_search_by_name_present(self):
        csv = "first_name,last_name\nAlice,Wonder\nBob,Builder"
        result = process_csv(csv)
        names = [f"{c['first_name']} {c['last_name']}" for c in result['contacts']]
        self.assertIn('Alice Wonder', names)
        self.assertIn('Bob Builder', names)

    def test_empty_csv_returns_no_contacts(self):
        result = process_csv("first_name,last_name\n")
        self.assertEqual(result['contacts'], [])
        self.assertEqual(result['errors'], [])

    def test_all_invalid_rows(self):
        csv = "first_name,last_name\n,,\n ,,"
        result = process_csv(csv)
        self.assertEqual(result['contacts'], [])
        self.assertTrue(len(result['errors']) > 0)


class TestGroupAssignment(unittest.TestCase):
    """Tests for group-related metadata in contacts."""

    def test_note_field_preserved(self):
        csv = "first_name,last_name,note\nJohn,Doe,belongs to VIP group"
        result = process_csv(csv)
        self.assertEqual(result['contacts'][0]['note'], 'belongs to VIP group')

    def test_empty_optional_fields_default_empty_string(self):
        csv = "first_name,last_name\nJohn,Doe"
        result = process_csv(csv)
        c = result['contacts'][0]
        self.assertEqual(c.get('phone', ''), '')
        self.assertEqual(c.get('email', ''), '')
        self.assertEqual(c.get('note', ''), '')


class TestCSVImport(unittest.TestCase):
    """Tests for CSV import validation and parsing."""

    def test_invalid_email_rejected(self):
        csv = "first_name,last_name,email\nJohn,Doe,not-an-email"
        result = process_csv(csv)
        self.assertEqual(len(result['contacts']), 0)
        self.assertEqual(len(result['errors']), 1)
        self.assertIn('email', result['errors'][0])

    def test_valid_email_accepted(self):
        for email in ['user@domain.com', 'test.name+tag@example.org', 'a@b.co']:
            self.assertTrue(validate_email(email), f"Should accept: {email}")

    def test_invalid_email_detected(self):
        for email in ['notanemail', '@nodomain', 'missing@', 'spaces in@email.com']:
            self.assertFalse(validate_email(email), f"Should reject: {email}")

    def test_valid_phone_accepted(self):
        for phone in ['+48123456789', '123 456 7890', '(555) 123-4567', '']:
            self.assertTrue(validate_phone(phone), f"Should accept: {phone}")

    def test_invalid_phone_rejected(self):
        for phone in ['abc', '12', 'call-me-maybe']:
            self.assertFalse(validate_phone(phone), f"Should reject: {phone}")

    def test_generate_csv_output(self):
        contacts = [
            {'id': 1, 'first_name': 'John', 'last_name': 'Doe',
             'phone': '+123', 'email': 'j@d.com', 'note': '', 'created_at': '2024-01-01'},
        ]
        csv = generate_csv(contacts)
        self.assertIn('first_name', csv)
        self.assertIn('John', csv)
        self.assertIn('Doe', csv)

    def test_generate_csv_header_present(self):
        csv = generate_csv([])
        self.assertIn('first_name', csv)
        self.assertIn('last_name', csv)

    def test_roundtrip_csv(self):
        original = [
            {'id': 1, 'first_name': 'Alice', 'last_name': 'Smith',
             'phone': '+1234', 'email': 'alice@smith.com', 'note': 'test', 'created_at': '2024-01-01'}
        ]
        csv = generate_csv(original)
        # Re-parse (skip id/created_at as they're from export)
        result = process_csv(csv)
        self.assertEqual(len(result['contacts']), 1)
        self.assertEqual(result['contacts'][0]['first_name'], 'Alice')
        self.assertEqual(result['contacts'][0]['last_name'], 'Smith')

    def test_malformed_csv_handled_gracefully(self):
        result = process_csv("not,a,valid\ncsv,with,wrong,columns")
        # Should not raise, just return errors
        self.assertIsInstance(result, dict)
        self.assertIn('contacts', result)
        self.assertIn('errors', result)

    def test_unicode_names(self):
        csv = "first_name,last_name\nŁukasz,Wróbel"
        result = process_csv(csv)
        self.assertEqual(result['contacts'][0]['first_name'], 'Łukasz')
        self.assertEqual(result['contacts'][0]['last_name'], 'Wróbel')


if __name__ == '__main__':
    unittest.main(verbosity=2)
