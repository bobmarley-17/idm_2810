#!/usr/bin/env python3
import mysql.connector
import subprocess

# Database config - update these with your details
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


def format_email_html(accounts):
    html = """
    <html>
    <body>
    <p>Uncorrelated accounts detected after the latest sync:</p>
    <table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse;">
        <thead>
            <tr style="background-color:#f2f2f2;">
                <th>ID</th>
                <th>SourceID</th>
                <th>Email</th>
                <th>CreatedDate</th>
            </tr>
        </thead>
        <tbody>
    """
    for acc in accounts:
        html += f"""
            <tr>
                <td>{acc['id']}</td>
                <td>{acc['source_id']}</td>
                <td><a href="mailto:{acc['email']}">{acc['email']}</a></td>
                <td>{acc['created_at']}</td>
            </tr>
        """
    html += """
        </tbody>
    </table>
    </body>
    </html>
    """
    return html


def send_email(html_body):
    email_content = f"""To: {EMAIL_TO}
From: {EMAIL_FROM}
Subject: {EMAIL_SUBJECT}
MIME-Version: 1.0
Content-Type: text/html; charset=UTF-8

{html_body}
"""
    process = subprocess.Popen(['/usr/sbin/sendmail', '-t'], stdin=subprocess.PIPE)
    process.communicate(email_content.encode('utf-8'))


def main():
    accounts = fetch_uncorrelated_accounts()
    if not accounts:
        print("No uncorrelated accounts found. No email sent.")
        return

    html_body = format_email_html(accounts)
    send_email(html_body)
    print("Notification email sent.")


if __name__ == '__main__':
    main()

