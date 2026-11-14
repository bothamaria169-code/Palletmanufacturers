<?php
// Durban's Pallets secrets — copy to config/secrets.php and fill in.
// config/secrets.php is in .gitignore: NEVER commit it. Upload it once to the
// server with cPanel File Manager; git deploys leave it alone.
return [
    // Live database (cPanel → MySQL® Databases). Use a NEW, EMPTY database.
    'db_host' => 'localhost',
    'db_name' => 'CPANELUSER_pallets',
    'db_user' => 'CPANELUSER_pallets',
    'db_pass' => 'CHANGE_ME',

    // Local XAMPP database (used automatically on localhost)
    'local_db_name' => 'durbanspallets',
    'local_db_user' => 'root',
    'local_db_pass' => '',

    // Public address of the live site, no trailing slash
    'app_url' => 'https://durbanspallets.co.za',

    // Admin account: created on first page load if it doesn't exist yet.
    // New quote requests and sign-ups are copied to this address.
    'admin_email'         => 'info@durbanspallets.co.za',
    'admin_seed_password' => 'CHANGE_ME',

    // Locked out of admin? Set a new password here and load any page once.
    'admin_reset_password' => '',
    'admin_reset_emails'   => [], // e.g. ['you@gmail.com']

    // Mailbox that sends quotes and system emails (cPanel → Email Accounts).
    // Leave smtp_pass blank to use the server's PHP mail() instead.
    'smtp_host' => 'mail.durbanspallets.co.za',
    'smtp_port' => 465,
    'smtp_user' => 'info@durbanspallets.co.za',
    'smtp_pass' => '',

    // Sales mailbox: enquiries, quotes, invoices and the admin Inbox (IMAP). Also editable in Admin → Settings → Email.
    'sales_email'     => 'sales@durbanspallets.co.za',
    'sales_smtp_user' => 'sales@durbanspallets.co.za',
    'sales_smtp_pass' => '',

    // Groq AI (console.groq.com → API Keys). Can also be set in Admin → Settings → AI.
    'groq_api_key' => '',

    // Long random string: signs printable quote links.
    'app_secret' => 'make-a-long-random-string',
];
