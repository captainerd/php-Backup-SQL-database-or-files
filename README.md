# phpbackup

**phpbackup** is a simple PHP script to help you easily upload and manage backups. It allows you to:

- Upload and unzip a backup ZIP file, optionally including an SQL file.
- Create a backup ZIP containing all PHP files and an optional SQL dump.

## Features
- Set an MD5 hash password for login (default: `12345`).
- Optionally configure default SQL credentials (or leave them blank).
- Upload `backup.php` to your root directory.
- Access the script by visiting `yourdomain.com/backup.php` and log in.

## Setup
1. Upload `backup.php` to your server's root directory.
2. Configure the script:
   - Set the password in the script (default is `12345`).
   - If needed, configure your SQL credentials.
3. Navigate to `yourdomain.com/backup.php` in your browser.
4. Log in and start managing your backups.

That's it! Enjoy seamless backup management.

