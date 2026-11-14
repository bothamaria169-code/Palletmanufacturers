# Durban's Pallets — setup guide

Follow the sections in order. Each step starts with where you do it:
🟦 **ACCOUNT** (Google, Meta, Groq websites) · 🟩 **ADMIN** (our admin panel at `/admin`) · 🟧 **CPANEL** (your hosting).

## Every URL you'll paste

| Where | Field | Paste exactly |
|---|---|---|
| 🟧 cPanel Git | Clone URL | `https://github.com/bothamaria169-code/durbanspallets.git` |
| 🟧 cPanel Git | Repository Path | `repositories/durbanspallets` |
| `config/secrets.php` | `app_url` | `https://durbanspallets.co.za` |
| 🟦 Google Search Console | Sitemap | `https://durbanspallets.co.za/sitemap.php` |
| 🟦 Google Business Profile | Website | `https://durbanspallets.co.za` |

---

## 1. Domain & SSL (15 min + DNS wait)

1. 🟧 Buy/point **durbanspallets.co.za** to the hosting (nameservers from your host).
2. 🟧 cPanel → **Domains** — make sure `durbanspallets.co.za` is the main domain (folder `public_html`).
3. 🟧 cPanel → **SSL/TLS Status** → tick the domain → **Run AutoSSL**.

✅ **Test:** open https://durbanspallets.co.za — a padlock shows (an empty or default page is fine for now).

## 2. Database (5 min)

> Use a **NEW, EMPTY** database. Don't reuse the old WordPress one — its tables have the same names and the site won't install.

1. 🟧 cPanel → **MySQL® Databases** → **Create New Database** → name `pallets` → **Create Database**. Note the full name (e.g. `cpuser_pallets`).
2. **Add New User** → username `pallets` → **Password Generator** → copy the password → **Create User**.
3. **Add User To Database** → pick the user and database → **Add** → tick **ALL PRIVILEGES** → **Make Changes**.

✅ **Test:** the database shows the user under **Privileged Users**.

## 3. Email mailbox (5 min)

1. 🟧 cPanel → **Email Accounts** → **Create** → `info@durbanspallets.co.za` → set a password → **Create**.
2. Click **Connect Devices** next to it and note the **Outgoing Server** and **SMTP Port** (465).

✅ **Test:** cPanel → **Check Email** → the mailbox opens in Webmail.

## 4. Put the code on the server (10 min)

1. 🟧 cPanel → **Git™ Version Control** → **Create**.
2. Turn **Clone a Repository** on. **Clone URL:** see the table above. **Repository Path:** `repositories/durbanspallets`. **Repository Name:** `durbanspallets` → **Create**.
3. **Manage** → **Pull or Deploy** → **Deploy HEAD Commit**. This copies the site into `public_html`.

> If `public_html` still has the old WordPress files, move them into a backup folder first (File Manager → select all → **Move** → `old-site-backup`). Leave `.well-known` and `cgi-bin`.
> "Deploy" greyed out? cPanel → **Terminal**: `cd ~/repositories/durbanspallets && git fetch origin && git checkout -B main origin/main`, then try again.

✅ **Test:** File Manager → `public_html` contains `index.php`, `admin`, `assets`.

## 5. secrets.php — the one file not in Git (5 min)

1. 🟧 File Manager → `public_html/config` → select `secrets.example.php` → **Copy** → name it `secrets.php`.
2. Right-click `secrets.php` → **Edit** and fill in:
   - `db_name`, `db_user`, `db_pass` from section 2 (full names with the `cpuser_` prefix).
   - `admin_email` → `info@durbanspallets.co.za`, `admin_seed_password` → the admin password you want.
   - `smtp_pass` → the mailbox password from section 3.
   - `app_secret` → any long random text (e.g. 40 random letters and numbers).
3. **Save Changes**.
4. 🟧 cPanel → **Select PHP Version** (or **MultiPHP Manager**) → choose **PHP 8.2** for the domain.

✅ **Test:** open https://durbanspallets.co.za — the home page shows 37 pallets. (The first load creates all tables.)

## 6. First admin sign-in (5 min)

1. Open https://durbanspallets.co.za/login.php → `info@durbanspallets.co.za` + your `admin_seed_password` → **Sign in**.
2. 🟩 **Settings → Business** — check name, phone, WhatsApp, email, address and **working hours** → **Save**.
3. 🟩 **Settings → Quotes** — set where new quote requests go, and whether website prices include VAT → **Save**.
4. 🟩 **Settings → Email** — fill in the mailbox → **Save** → **Send test email**.

✅ **Test:** the test email arrives in your inbox (check spam the first time).

## 7. Try a full quote (5 min)

1. In a private window, add 2 pallets to the quote → **My quote** → fill in the form → **Send quote request**.
2. 🟩 **Quotes** → open it → change a price, add **Delivery** → **Save & email quote**.
3. Open the email → **View & accept quote** → **Accept quote**.

✅ **Test:** the quote shows **Accepted** in 🟩 **Quotes**, and you received an "accepted" email.

## 8. Groq AI (5 min, free)

1. 🟦 https://console.groq.com → sign in → **API Keys** → **Create API Key** → copy it.
2. 🟩 **Settings → AI (Groq)** → paste the key → **Save** → **Test AI**.
3. 🟩 **Products** → open any product → **✨ Write with AI** → check the text → **Save product**.

✅ **Test:** send yourself a message on the Contact page — the thank-you email has a personal answer.

## 9. Google (30 min)

1. 🟦 **Google Business Profile** (business.google.com) — name `Durban's Pallets`, address `68 Columbine Place, Glen Anil, Durban, 4051`, phone `078 727 5235`, website from the table. Keep these exactly the same as on the site.
2. 🟦 **Search Console** (search.google.com/search-console) → **Add property** → **URL prefix** → `https://durbanspallets.co.za` → **HTML tag** → copy only the `content="…"` value → 🟩 **Settings → Google & Meta → Google Search Console code** → **Save** → back in Search Console **Verify**.
3. Search Console → **Sitemaps** → `sitemap.php` → **Submit**.
4. 🟦 **Google Analytics** → **Admin** → **Create property** → **Web** → copy the `G-…` ID → 🟩 **Settings → Google & Meta** → **Save**.

✅ **Test:** Analytics → **Realtime** shows you when you open the site.

## 10. Meta Pixel (10 min)

1. 🟦 business.facebook.com → **Events Manager** → **Connect data sources** → **Web** → name it → copy the **Pixel ID**.
2. 🟩 **Settings → Google & Meta → Meta Pixel ID** → **Save**.

✅ **Test:** Events Manager → **Test events** → open the site → a PageView appears.

## Troubleshooting

> Locked out of admin: add `'admin_reset_password' => 'NewPass123',` to `config/secrets.php`, load any page, sign in, then delete that line.
> Every later update: push to GitHub → 🟧 **Git™ Version Control** → **Manage** → **Pull or Deploy** → **Update from Remote** → **Deploy HEAD Commit**.
