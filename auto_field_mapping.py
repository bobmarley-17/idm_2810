import csv
import json
import pymysql

# ---- CONFIG ----
DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "YOUR_DB_PASSWORD",
    "database": "idmdb"
}
SOURCE_ID = 22  # Change to the account_sources.id you want to update
#LOGICAL_FIELDS = ["first_name", "last_name", "email", "username", "employee_id"]
LOGICALFIELDS = ["email", "firstname", "lastname"]

# ---- FUNCTIONS ----
def get_csv_headers(file_path):
    with open(file_path, newline='', encoding='utf-8') as csvfile:
        reader = csv.reader(csvfile)
        headers = next(reader)
    return headers

def update_field_mapping(source_id, file_path):
    headers = get_csv_headers(file_path)
    print("\nDetected CSV Headers:", headers)

    mapping = {}
    for logical in LOGICAL_FIELDS:
        print(f"\nEnter the CSV header that matches '{logical}' (or leave blank to skip):")
        user_input = input("> ").strip()
        if user_input:
            mapping[logical] = user_input

    # Fetch existing config
    conn = pymysql.connect(**DB_CONFIG)
    cur = conn.cursor()
    cur.execute("SELECT config FROM account_sources WHERE id=%s", (source_id,))
    row = cur.fetchone()
    if not row:
        print("Source not found!")
        return
    config = json.loads(row[0])

    # Update config
    config["field_mapping"] = mapping

    cur.execute("UPDATE account_sources SET config=%s WHERE id=%s", (json.dumps(config), source_id))
    conn.commit()
    conn.close()

    print("\n✅ field_mapping updated for source", source_id)
    print(json.dumps(mapping, indent=2))

# ---- MAIN ----
if __name__ == "__main__":
    # Load current CSV path from DB
    conn = pymysql.connect(**DB_CONFIG)
    cur = conn.cursor()
    cur.execute("SELECT config FROM account_sources WHERE id=%s", (SOURCE_ID,))
    row = cur.fetchone()
    conn.close()
    if not row:
        print("Source not found!")
    else:
        file_path = json.loads(row[0])["file_path"]
        update_field_mapping(SOURCE_ID, file_path)

