import logging

def handle_defunct_users(conn, baseline_accounts, source_id):
    """Detect users missing from baseline or other sources and handle their defunct status."""
    cursor = conn.cursor(dictionary=True)
    total_sources_affected = 0
    
    # For baseline source (SSHRData)
    if source_id == 1:  # Assuming SSHRData has source_id = 1
        # Build set of employee_ids and emails from baseline
        baseline_empids = set()
        baseline_emails = set()
        for acc in baseline_accounts:
            if acc.get('employee_id'): baseline_empids.add(acc['employee_id'])
            if acc.get('email'): baseline_emails.add(acc['email'])

        # Find users in DB not present in baseline
        cursor.execute("SELECT id, employee_id, email FROM users WHERE status = 'active'")
        db_users = cursor.fetchall()
        defunct = []
        
        for user in db_users:
            empid = user.get('employee_id')
            email = user.get('email')
            # Mark as defunct if either employee_id or email is missing from baseline
            if (empid and empid not in baseline_empids) or (email and email not in baseline_emails):
                defunct.append(user)

        # For each defunct user
        for user in defunct:
            # Get all sources where the user has accounts
            cursor.execute("""
                SELECT DISTINCT ua.source_id
                FROM user_accounts ua
                WHERE ua.user_id = %s
            """, (user['id'],))
            sources = cursor.fetchall()
            
            # Add entry to defunct_users for each source
            user_sources = []
            for src in sources:
                cursor.execute("""
                    INSERT INTO defunct_users 
                    (user_id, source_id, employee_id, email, deleted_at, status)
                    VALUES (%s, %s, %s, %s, NOW(), 'pending')
                    ON DUPLICATE KEY UPDATE
                    status = 'pending',
                    deleted_at = NOW()
                """, (user['id'], src['source_id'], user['employee_id'], user['email']))
                user_sources.append(src['source_id'])
                total_sources_affected += 1

            # Delete from users table (will cascade to user_accounts)
            cursor.execute("DELETE FROM users WHERE id = %s", (user['id'],))
            logging.info(f"User {user['email']} ({user['employee_id']}) marked as defunct in sources: {user_sources}")
            
        conn.commit()
        if defunct:
            logging.info(f"Added {len(defunct)} users to defunct_users across {total_sources_affected} source entries.")
            
    else:
        # For non-baseline sources: check if any users in defunct_users are missing from this source
        cursor.execute("""
            SELECT du.user_id, du.source_id, du.email, du.employee_id
            FROM defunct_users du
            WHERE du.source_id = %s AND du.status = 'pending'
        """, (source_id,))
        pending = cursor.fetchall()
        
        for entry in pending:
            # Check if the user's account exists in the current source's data
            found = False
            for acc in baseline_accounts:
                if ((entry['email'] and acc.get('email') == entry['email']) or 
                    (entry['employee_id'] and acc.get('employee_id') == entry['employee_id'])):
                    found = True
                    break
            
            if not found:
                cursor.execute("""
                    UPDATE defunct_users 
                    SET status = 'deleted', deleted_at = NOW()
                    WHERE user_id = %s AND source_id = %s
                """, (entry['user_id'], source_id))
                
                # Remove the account from user_accounts
                cursor.execute("""
                    DELETE FROM user_accounts 
                    WHERE user_id = %s AND source_id = %s
                """, (entry['user_id'], source_id))
                
        conn.commit()
