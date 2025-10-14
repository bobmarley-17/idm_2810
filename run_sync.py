#!/usr/bin/env python3
import sys
import argparse
import mysql.connector
import json
import logging
from datetime import datetime
import os
import re


def load_snapshot(snapshot_path):
    if os.path.exists(snapshot_path):
        try:
            with open(snapshot_path, 'r') as f:
                data = json.load(f)
                # Convert list to dict keyed by email or employee_id
                snapshot = {}
                for row in data:
                    key = row.get('email') or row.get('employee_id')
                    if key:
                        snapshot[key] = row
                return snapshot
        except Exception as e:
            logging.error(f"Failed to load snapshot from {snapshot_path}: {e}")
            return {}
    return {}


def save_snapshot(data, snapshot_path):
    try:
        with open(snapshot_path, 'w') as f:
            json.dump(data, f, indent=2)
    except Exception as e:
        logging.error(f"Failed to save snapshot to {snapshot_path}: {e}")


def detect_deleted_accounts(prev_data, curr_data):
    """Compare previous and current data to find deleted users"""
    deleted_keys = set(prev_data.keys()) - set(curr_data.keys())
    return [prev_data[k] for k in deleted_keys]


# Configuration
CONFIG = {
    'db_host': 'localhost',
    'db_name': 'idmdb1209',
    'db_user': 'idm_user',
    'db_password': 'test123',
    'log_file': '/var/www/html/idmtool/sync.log',
    'default_correlation_field': 'email'
}

def setup_logging():
    """Configure logging"""
    log_dir = os.path.dirname(CONFIG['log_file'])
    if log_dir:
        os.makedirs(log_dir, exist_ok=True)
    logging.basicConfig(
        filename=CONFIG['log_file'],
        level=logging.INFO,
        format='%(asctime)s - %(levelname)s - %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    console = logging.StreamHandler()
    console.setLevel(logging.INFO)
    logging.getLogger().addHandler(console)
    logging.info('=== Starting Correlation Sync ===')

def get_db_connection():
    """Create database connection"""
    try:
        return mysql.connector.connect(
            host=CONFIG['db_host'],
            database=CONFIG['db_name'],
            user=CONFIG['db_user'],
            password=CONFIG['db_password'],
            autocommit=False
        )
    except Exception as e:
        logging.error(f"Database connection failed: {e}")
        raise

def get_correlation_rules(conn, source_id):
    """Fetch correlation rules from database"""
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
        SELECT match_field, match_type, priority
        FROM correlation_rules
        WHERE source_id = %s
        ORDER BY priority
    """, (source_id,))
    rules = cursor.fetchall()
    logging.info(f"Loaded {len(rules)} correlation rules for source {source_id}")
    return rules

def get_source_config(conn, source_id):
    """Get source configuration with field mappings"""
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
        SELECT * FROM account_sources
        WHERE id = %s
    """, (source_id,))
    source = cursor.fetchone()
    if source:
        source['config'] = json.loads(source['config'])
    return source

def load_xml_accounts(file_path, config):
    """Load user accounts from an XML file"""
    import xml.etree.ElementTree as ET
    accounts = []

    if not os.path.exists(file_path):
        logging.error(f"XML file not found: {file_path}")
        return accounts

    try:
        tree = ET.parse(file_path)
        root = tree.getroot()

        # Get user elements using XPath
        user_path = config.get('xml_user_path', '//user')

        # ElementTree doesn't support full XPath, so we need to handle the path parts
        path_parts = user_path.split('/')
        path_parts = [part for part in path_parts if part] # Remove empty strings from leading/trailing slashes

        # Start with root and traverse down
        elements = [root]
        for part in path_parts:
            if part == '':
                continue
            new_elements = []
            for element in elements:
                new_elements.extend(element.findall(part))
            elements = new_elements

        field_mappings = {
            'email': config.get('email_field', 'email'),
            'firstname': config.get('firstname_field', 'firstname'),
            #'username': config.get('username_field', 'username'),
            'lastname': config.get('lastname_field', 'lastname')
            #'employee_id': config.get('employee_id_field', 'employee_id')
        }

        for user in elements:
            account = {}
            for target_field, field_path in field_mappings.items():
                try:
                    # Handle direct child elements
                    if '/' not in field_path:
                        element = user.find(field_path)
                        if element is not None:
                            account[target_field] = element.text or ''
                        continue

                    # Handle nested paths
                    path_parts = field_path.split('/')
                    current = user
                    for part in path_parts:
                        if not part:
                            continue
                        current = current.find(part)
                        if current is None:
                            break
                    if current is not None:
                        account[target_field] = current.text or ''
                except Exception as e:
                    logging.warning(f"Error extracting field {field_path}: {str(e)}")
                    account[target_field] = ''

            # Handle additional fields if configured
            additional_fields = config.get('additional_fields', {})
            if additional_fields:
                extra_data = {}
                for field, xpath in additional_fields.items():
                    try:
                        element = user.find(xpath)
                        if element is not None:
                            extra_data[field] = element.text or ''
                    except Exception as e:
                        logging.warning(f"Error extracting additional field {xpath}: {str(e)}")
                if extra_data:
                    account['additional_data'] = json.dumps(extra_data)

            accounts.append(account)

        logging.info(f"Loaded {len(accounts)} accounts from XML file")
        return accounts

    except ET.ParseError as e:
        logging.error(f"Failed to parse XML file: {e}")
        return accounts
    except Exception as e:
        logging.error(f"Error loading XML accounts: {e}")
        return accounts

def load_csv_accounts(file_path, field_mappings):
    """Load and map CSV account data"""
    accounts = []
    try:
        with open(file_path, 'r', encoding='utf-8-sig') as f:
            headers = [h.strip().lower() for h in f.readline().strip().split(',')]

            # Create mapping from CSV headers to standard fields
            reverse_mapping = {v: k for k, v in field_mappings.items()}

            for line in f:
                values = line.strip().split(',')
                if len(values) != len(headers):
                    continue

                account = {}
                for header, value in zip(headers, values):
                    if header in reverse_mapping:
                        account[reverse_mapping[header]] = value.strip()
                    account[header] = value.strip()  # Keep original fields

                accounts.append(account)

    except Exception as e:
        logging.error(f"CSV processing failed: {str(e)}")
        raise

    return accounts

def correlate_accounts(conn, source_id, accounts):
    """Correlate accounts using rules from database"""
    cursor = conn.cursor(dictionary=True)
    rules = get_correlation_rules(conn, source_id)
    correlated = []
    unmatched = []

    for account in accounts:
        try:
            matched = False
            account_id = account.get('username') or account.get('email') or str(hash(frozenset(account.items())))

            # Try each rule in priority order
            for rule in rules:
                field_value = account.get(rule['match_field'])
                if not field_value:
                    continue

                query = build_correlation_query(rule)
                params = prepare_rule_parameters(field_value, rule)

                cursor.execute(query, params)
                user = cursor.fetchone()

                if user:
                    correlated.append({
                        'user_id': user['id'],
                        'account_id': account_id,
                        'account_data': account,
                        'matched_by': {
                            'field': rule['match_field'],
                            'type': rule['match_type'],
                            'priority': rule['priority']
                        }
                    })
                    matched = True
                    break

            # Fallback to default correlation field if no rules matched
            if not matched and CONFIG['default_correlation_field']:
                field_value = account.get(CONFIG['default_correlation_field'])
                if field_value:
                    cursor.execute(
                        f"SELECT id FROM users WHERE {CONFIG['default_correlation_field']} = %s LIMIT 1",
                        (field_value,)
                    )
                    user = cursor.fetchone()
                    if user:
                        correlated.append({
                            'user_id': user['id'],
                            'account_id': account_id,
                            'account_data': account,
                            'matched_by': {
                                'field': CONFIG['default_correlation_field'],
                                'type': 'default',
                                'priority': 999
                            }
                        })
                        matched = True

            if not matched:
                unmatched.append(account)

        except Exception as e:
            logging.error(f"Correlation failed for account: {str(e)}")
            unmatched.append(account)

    return correlated, unmatched

def build_correlation_query(rule):
    """Build SQL query based on correlation rule"""
    field = rule['match_field']
    if rule['match_type'] == 'exact':
        return f"SELECT id FROM users WHERE {field} = %s LIMIT 1"
    elif rule['match_type'] == 'partial':
        return f"SELECT id FROM users WHERE {field} LIKE %s LIMIT 1"
    elif rule['match_type'] == 'regex':
        return f"SELECT id FROM users WHERE {field} REGEXP %s LIMIT 1"
    else:
        return f"SELECT id FROM users WHERE {field} = %s LIMIT 1"

def prepare_rule_parameters(value, rule):
    """Prepare parameters based on rule type"""
    if rule['match_type'] == 'partial':
        return (f"%{value}%",)
    elif rule['match_type'] == 'regex':
        return (value,)
    else:  # exact
        return (value,)

def save_correlated_accounts(conn, source_id, correlated):
    """Save correlated accounts to database"""
    cursor = conn.cursor()
    for item in correlated:
        try:
            cursor.execute("""
                INSERT INTO user_accounts
                (user_id, source_id, account_id, username, email, additional_data, matched_by)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                username = VALUES(username),
                email = VALUES(email),
                additional_data = VALUES(additional_data),
                matched_by = VALUES(matched_by),
                updated_at = NOW()
            """, (
                item['user_id'],
                source_id,
                item['account_id'],
                #item['account_data'].get('username'),
                item['account_data'].get('firstname'),
                item['account_data'].get('email'),
                json.dumps(item['account_data']),
                json.dumps(item['matched_by'])
            ))
        except Exception as e:
            logging.error(f"Failed to save account: {str(e)}")
            continue
    conn.commit()

def save_uncorrelated_accounts(conn, source_id, unmatched):
    """Save uncorrelated accounts to database"""
    cursor = conn.cursor()
    # Build a set of account_ids for unmatched accounts
    unmatched_ids = set()
    for account in unmatched:
        account_id = account.get('username') or account.get('email') or str(hash(frozenset(account.items())))
        unmatched_ids.add(account_id)

    # Remove any uncorrelated accounts for this source that are no longer unmatched, but do NOT delete those assigned to a role
    if unmatched_ids:
        format_strings = ','.join(['%s'] * len(unmatched_ids))
        cursor.execute(f"""
            DELETE FROM uncorrelated_accounts
            WHERE source_id = %s AND account_id NOT IN ({format_strings}) AND role_account_id IS NULL
        """, tuple([source_id] + list(unmatched_ids)))
    else:
        # If no unmatched accounts, remove all for this source that are not assigned to a role
        cursor.execute("""
            DELETE FROM uncorrelated_accounts WHERE source_id = %s AND role_account_id IS NULL
        """, (source_id,))

    # Insert or update current unmatched accounts
    for account in unmatched:
        try:
            account_id = account.get('username') or account.get('email') or str(hash(frozenset(account.items())))
            # Check if this account already exists and has a role assigned
            cursor.execute("SELECT role_account_id FROM uncorrelated_accounts WHERE source_id = %s AND account_id = %s", (source_id, account_id))
            existing = cursor.fetchone()
            if existing and existing[0]:
                # Preserve the existing role_account_id
                cursor.execute("""
                    UPDATE uncorrelated_accounts SET
                        username = %s,
                        email = %s,
                        account_data = %s,
                        created_at = NOW()
                    WHERE source_id = %s AND account_id = %s
                """, (
                    account.get('username'),
                    account.get('email'),
                    json.dumps(account),
                    source_id,
                    account_id
                ))
            else:
                # Insert or update, allow role_account_id to be set if present
                role_account_id = account.get('role_account_id')
                cursor.execute("""
                    INSERT INTO uncorrelated_accounts
                    (source_id, account_id, username, email, account_data, role_account_id)
                    VALUES (%s, %s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                        username = VALUES(username),
                        email = VALUES(email),
                        account_data = VALUES(account_data),
                        role_account_id = VALUES(role_account_id),
                        created_at = NOW()
                """, (
                    source_id,
                    account_id,
                    account.get('username'),
                    account.get('email'),
                    json.dumps(account),
                    role_account_id
                ))
        except Exception as e:
            logging.error(f"Failed to save uncorrelated account: {str(e)}")
            continue
    conn.commit()

def get_role_accounts(conn):
    """Fetch all role accounts"""
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT id, name, description FROM role_accounts ORDER BY name")
    return cursor.fetchall()

def create_role_account(conn, name, description=None):
    """Create a new role account"""
    cursor = conn.cursor()
    cursor.execute("""
        INSERT INTO role_accounts (name, description) VALUES (%s, %s)
    """, (name, description))
    conn.commit()
    return cursor.lastrowid

# Implementation for baseline user insert
def insert_baseline_users(conn, accounts_list, source_id):
    cursor = conn.cursor()
    for acc in accounts_list:
        # Insert or update user in users table
        cursor.execute(
            """
            INSERT INTO users (username, first_name, last_name, email, employee_id, status, supervisor_email)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                username = VALUES(username),
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                email = VALUES(email),
                status = VALUES(status),
                supervisor_email = VALUES(supervisor_email),
                updated_at = NOW()
            """,
            (
                acc.get('username', ''),
                acc.get('first_name', acc.get('firstname', '')),
                acc.get('last_name', acc.get('lastname', '')),
                acc.get('email', ''),
                acc.get('employee_id', ''),
                acc.get('status', 'active'),
                acc.get('supervisor_email', '')
            )
        )
        # Get user id for the inserted or existing user
        cursor.execute(
            "SELECT id FROM users WHERE email = %s",
            (acc.get('email', ''),)
        )
        user_id = cursor.fetchone()[0]

        # Insert or update corresponding user_accounts row to reflect accounts in the source
        cursor.execute(
            """
            INSERT INTO user_accounts (user_id, source_id, account_id, username, email, matched_by, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                username = VALUES(username),
                email = VALUES(email),
                matched_by = VALUES(matched_by),
                updated_at = NOW()
            """,
            (
                user_id,
                source_id,
                acc.get('employee_id', acc.get('email', '')),  # or other unique account id
                acc.get('first_name', acc.get('username', '')),
                acc.get('email', ''),
                '{"type": "baseline"}'  # JSON meta indicating baseline import
            )
        )
    conn.commit()

def handle_defunct_users(conn, baseline_accounts, source_id):
    """Detect users missing from baseline or other sources and handle their defunct status."""
    cursor = conn.cursor(dictionary=True)

    # Baseline source logic
    if source_id == 1:  # SSHRData baseline
        baseline_empids = {acc['employee_id'] for acc in baseline_accounts if acc.get('employee_id')}
        baseline_emails = {acc['email'] for acc in baseline_accounts if acc.get('email')}

        cursor.execute("SELECT id, employee_id, email FROM users WHERE status='active'")
        db_users = cursor.fetchall()
        defunct = []

        for user in db_users:
            empid, email = user.get('employee_id'), user.get('email')
            if (empid and empid not in baseline_empids) or (email and email not in baseline_emails):
                defunct.append(user)

        for user in defunct:
            cursor.execute("SELECT DISTINCT ua.source_id FROM user_accounts ua WHERE ua.user_id=%s", (user['id'],))
            sources = cursor.fetchall()
            for src in sources:
                cursor.execute("""
                    INSERT INTO defunct_users (user_id, source_id, employee_id, email, deleted_at, status)
                    VALUES (%s, %s, %s, %s, NOW(), 'pending')
                    ON DUPLICATE KEY UPDATE status='pending', deleted_at=NOW()
                """, (user['id'], src['source_id'], user['employee_id'], user['email']))
            cursor.execute("UPDATE users SET status='inactive' WHERE id=%s", (user['id'],))

        conn.commit()
        if defunct:
            logging.info(f"Added {len(defunct)} users to defunct_users and marked inactive.")

    else:
        # Non-baseline sources
        cursor.execute("SELECT du.user_id, du.source_id FROM defunct_users du WHERE du.source_id=%s AND du.status='pending'", (source_id,))
        pending = cursor.fetchall()
        for entry in pending:
            found = False
            cursor.execute("SELECT email, employee_id FROM users WHERE id=%s", (entry['user_id'],))
            user_data = cursor.fetchone()
            if user_data:
                for acc in baseline_accounts:
                    if (acc.get('email') and acc.get('email') == user_data['email']) or \
                       (acc.get('employee_id') and acc.get('employee_id') == user_data['employee_id']):
                        found = True
                        break
            #if not found:
            #    logging.info(f"User {entry['user_id']} not found in source {source_id}, marking as deleted")
            #    cursor.execute("UPDATE defunct_users SET status='deleted', deleted_at=NOW() WHERE user_id=%s AND source_id=%s", (entry['user_id'], source_id))
            #    cursor.execute("UPDATE user_accounts SET status='deleted', updated_at=NOW() WHERE user_id=%s AND source_id=%s", (entry['user_id'], source_id))
            #    conn.commit()

def sync_correlation_source(source_id):
    """Main sync function"""
    logging.info(f"Starting correlation sync for source {source_id}")
    conn = None
    try:
        conn = get_db_connection()

        # Get source configuration
        source = get_source_config(conn, source_id)
        if not source:
            logging.error(f"Source ID {source_id} not found")
            return False

        # Load accounts based on source type
        if source['type'] == 'CSV':
            accounts = load_csv_accounts(
                source['config']['file_path'],
                source['config'].get('field_mapping', {})
            )
        elif source['type'] == 'XML':
            accounts = load_xml_accounts(
                source['config']['file_path'],
                source['config']
            )
        else:
            logging.error(f"Unsupported source type: {source['type']}")
            return False

        accounts_list = accounts  # preserve original list for defunct logic

        # === Snapshot Comparison ===
        filename = os.path.basename(source["config"]["file_path"])
        snapshot_path = os.path.join('uploads', 'previous_' + filename)
        prev_data = load_snapshot(snapshot_path)
        curr_data = {
            acc.get('email') or acc.get('employee_id'): acc
            for acc in accounts if acc.get('email') or acc.get('employee_id')
        }

        accounts_list = list(curr_data.values())


        if prev_data:
            deleted_accounts = detect_deleted_accounts(prev_data, curr_data)
            cursor = conn.cursor()
            for acc in deleted_accounts:
                identifier = acc.get('email') or acc.get('employee_id')
                logging.info(f"[{source['name']}] Detected missing account (will mark pending): {identifier}")
                cursor.execute("SELECT id FROM users WHERE email=%s OR employee_id=%s LIMIT 1", (acc.get('email'), acc.get('employee_id')))
                user = cursor.fetchone()
                if user:
                    user_id = user[0]
                    cursor.execute("""
                        INSERT INTO defunct_users (user_id, source_id, employee_id, email, deleted_at, status)
                        VALUES (%s, %s, %s, %s, NOW(), 'pending')
                        ON DUPLICATE KEY UPDATE status='pending', deleted_at=NOW()
                    """, (user_id, source_id, acc.get('employee_id'), acc.get('email')))
                    cursor.execute("UPDATE users SET status='inactive' WHERE id=%s", (user_id,))
            conn.commit()


        # Save snapshot for next sync
        save_snapshot(list(curr_data.values()), snapshot_path)
	
        # === Reactivate users who reappeared ===
        cursor = conn.cursor()
        for key, acc in curr_data.items():
            cursor.execute("""
                SELECT ua.user_id, ua.status
                FROM user_accounts ua
                JOIN users u ON ua.user_id = u.id
                WHERE ua.source_id = %s AND (u.email = %s OR u.employee_id = %s)
                LIMIT 1
            """, (source_id, acc.get('email'), acc.get('employee_id')))
            result = cursor.fetchone()
            if result and result[1] == 'deleted':
                logging.info(f"[{source['name']}] Reactivating user {key}")
                # Update main users table
                cursor.execute("""
                    UPDATE users SET status='active', updated_at=NOW() WHERE id=%s
                """, (result[0],))
                # Update user_accounts
                cursor.execute("""
                    UPDATE user_accounts SET status='active', updated_at=NOW()
                    WHERE user_id=%s AND source_id=%s
                """, (result[0], source_id))
                # Update defunct_users
                cursor.execute("""
                    UPDATE defunct_users SET status='pending', deleted_at=NULL
                    WHERE user_id=%s AND source_id=%s
                """, (result[0], source_id))
        conn.commit()

        # === BASELINE LOGIC ===
        if source['is_baseline'] == 1:
            # Baseline: Insert users, skip correlation
            insert_baseline_users(conn, accounts_list, source_id)
            logging.info(f"Baseline user import completed for {source['name']}: {len(accounts)} users inserted.")

            # Detect and handle defunct users (delete from users, add to defunct_users)
            handle_defunct_users(conn, accounts_list, source_id)

            # Update last sync time
            cursor = conn.cursor()
            cursor.execute("""
                UPDATE account_sources
                SET last_sync = NOW()
                WHERE id = %s
            """, (source_id,))
            conn.commit()
            return True

        # === NON-BASELINE LOGIC: Check for defunct users to mark as deleted ===
        handle_defunct_users(conn, accounts_list, source_id)
        conn.commit()

        # Correlate accounts
        correlated, unmatched = correlate_accounts(conn, source_id, accounts)

        # Save results
        save_correlated_accounts(conn, source_id, correlated)
        save_uncorrelated_accounts(conn, source_id, unmatched)

        # Update last sync time
        cursor = conn.cursor()
        cursor.execute("""
            UPDATE account_sources
            SET last_sync = NOW()
            WHERE id = %s
        """, (source_id,))
        conn.commit()

        # Log results
        logging.info(f"""
            Correlation completed for {source['name']}:
            - Accounts processed: {len(accounts)}
            - Successfully correlated: {len(correlated)}
            - Unmatched accounts: {len(unmatched)}
        """)
        return True
    except Exception as e:
        logging.error(f"Sync failed for source {source_id}: {str(e)}", exc_info=True)
        if conn:
            conn.rollback()
        return False
    finally:
        if conn and conn.is_connected():
            conn.close()
        logging.info("Database connection closed")

if __name__ == "__main__":
    setup_logging()
    parser = argparse.ArgumentParser(description='IDM Correlation Sync Tool')
    parser.add_argument('--source', type=int, required=True, help='Source ID to sync')
    args = parser.parse_args()
    success = sync_correlation_source(args.source)
    sys.exit(0 if success else 1)

