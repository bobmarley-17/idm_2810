#!/usr/bin/env python3
import mysql.connector
import subprocess

# Database config - update with your credentials
db_config = {
    'host': 'localhost',
    'user': 'idm_user',
    'password': 'test123',
    'database': 'idmdb1209'
}

EMAIL_TO = "krishnamurala@corp.untd.com"
EMAIL_FROM = "test02@idm_1209.com"
EMAIL_SUBJECT = "IDM Uncorrelated Accounts"


def fetch_uncorrelated_accounts():
    conn = mysql.connector.connect(**db_config)
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT id, source_id, email, created_at FROM uncorrelated_accounts")
    results = cursor.fetchall()
    conn.close()
    return results


def format_email_markdown_table(accounts):
    # Header row
    lines = []
    lines.append("+--------+----------+-------------------------+---------------------+")
    lines.append("| ID     | SourceID | Email                   | CreatedDate         |")
    lines.append("+--------+----------+-------------------------+---------------------+")

    # Data rows
    for acc in accounts:
        email_md = f"[{acc['email']}](mailto:{acc['email']})"
        created_str = acc['created_at'].strftime('%Y-%m-%d %H:%M:%S') if hasattr(acc['created_at'], 'strftime') else str(acc['created_at'])
        lines.append(f"| {str(acc['id']).ljust(6)} | {str(acc['source_id']).ljust(8)} | {email_md.ljust(23)} | {created_str.ljust(19)} |")

    lines.append("+--------+----------+-------------------------+---------------------+")
    return "\n".join(lines)


def send_email_markdown(body):
    email_content = f"""To: {EMAIL_TO}
From: {EMAIL_FROM}
Subject: {EMAIL_SUBJECT}
Content-Type: text/plain; charset=UTF-8

{body}
"""
    process = subprocess.Popen(['/usr/sbin/sendmail', '-t'], stdin=subprocess.PIPE)
    process.communicate(email_content.encode('utf-8'))


def main():
    accounts = fetch_uncorrelated_accounts()
    if not accounts:
        print("No uncorrelated accounts found. No email sent.")
        return

    body_intro = "Uncorrelated accounts detected after the latest sync:\n\n"
    body = body_intro + format_email_markdown_table(accounts)

    send_email_markdown(body)
    print("Notification email sent.")


if __name__ == '__main__':
    main()

