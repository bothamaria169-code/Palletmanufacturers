# Durban's Pallets — durbanspallets.co.za

Pallet shop where customers build a **quote request** (no card checkout), get a priced quote by email, and **accept it online** in their own dashboard. Staff price and send quotes from the **admin panel**.

**Stack:** PHP 8.1+ · MySQL/MariaDB (PDO) · PHPMailer · dompdf (PDFs) · built-in IMAP reader · Groq AI · plain CSS/JS (SWIFTDNA) · cPanel Git deploy

All 37 products, categories, photos, About text, return policy and the 4 blog articles were copied from the old palletmanufacturers.co.za WooCommerce site.

| Area | Where |
|---|---|
| Home page | `index.php` |
| Shop / category pages | `shop.php` (`?cat=wooden-pallets`) |
| Product page | `product.php?s=<slug>` |
| Quote basket + request form | `quote.php` |
| Quote page (customer: accept / decline / messages / print) | `quote-view.php` |
| Customer dashboard | `dashboard.php`, `quotes.php`, `account.php` |
| Sign in / sign up / reset | `login.php`, `register.php`, `forgot-password.php`, `reset-password.php` |
| About, Contact, Returns, Blog, Privacy, Terms | `about.php`, `contact.php`, `returns.php`, `blog.php` + `post.php`, `privacy.php`, `terms.php` |
| Admin panel | `admin/` — Dashboard, Quotes, Invoices, Inbox (sales@), Enquiries, Customers, Products, Categories, Blog, Email templates, Sent log, Settings, Audit log |
| Quote / invoice PDFs + print view | `document.php` (signed links) · layout in `includes/documents.php` |
| Invoices | `includes/invoices.php`, `admin/invoices.php`, `admin/invoice.php`, customer `invoices.php` |
| Email templates | `includes/templates.php` (defaults) · edited in **Admin → Email templates** |
| Inbox (read + reply to sales@) | `admin/inbox.php` · IMAP client `includes/mailreader.php` (no PHP imap extension needed) |
| Database tables | `database/migrate.php` (creates everything on the first page load) |
| Seed data from the old site | `database/seed/products.json`, `database/seed/content.json`, `assets/img/products/` |
| Business details, VAT, email, AI, Google/Meta | **Admin → Settings** (falls back to `config/secrets.php`) |
| Groq AI (product text, enquiry replies) | `includes/ai.php` |
| Deploy | `.cpanel.yml` → `public_html` |

## How a quote works

1. Customer adds pallets → **My quote** → fills in delivery details → **Send quote request**.
2. You get an email; the customer gets a copy with a tracking link (works without an account).
3. 🟩 **Admin → Quotes → Price it**: change unit prices, add delivery/discount, a note → **Save & email quote**.
4. Customer opens the link or their dashboard → **Accept quote** (or Decline). You get an email.
5. 🟩 On the quote → **Create invoice from this quote** → **Email invoice (PDF)**. When the money is in → **Mark as paid**.
6. Mark the quote **Delivered** when done. Customers can **Re-order** any old quote in one click.

Guest quotes move into the customer's account automatically when they sign up or sign in with the same email.

## The two mailboxes

| Mailbox | Role |
|---|---|
| `sales@durbanspallets.co.za` | **Sends and receives everything**: quote emails (PDF), invoices (PDF), enquiry replies, messages, password links. New quote requests, enquiries and customer messages are announced here. **Admin → Inbox** reads and answers it. Set in 🟩 Settings → Email. |
| `info@durbanspallets.co.za` | Shown on the website only; also the admin login username. |

If the web server can't reach `mail.durbanspallets.co.za` it retries through `localhost`, then the server's own mail(). Gmail needs SPF/DKIM to pass — see the setup guide, section 3.

## Run it locally (XAMPP)

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open http://localhost/phpmyadmin → **New** → database name `durbanspallets` → **Create**.
3. Copy `config/secrets.example.php` to `config/secrets.php`. Set `admin_seed_password` to a password of your choice.
4. Open http://localhost/Palletmanufacturers.co.za — tables, products and articles are created on that first load.
5. Sign in at `/login.php` with `info@durbanspallets.co.za` and the `admin_seed_password`.

> No Apache? In a terminal: `C:\xampp\php\php.exe -S localhost:8765 -t C:\xampp\htdocs\Palletmanufacturers.co.za` and open http://localhost:8765.
> Emails don't send on XAMPP — that's normal. They're still listed in **Admin → Emails** (as failed).

## Deploy

Full click-by-click steps: **[docs/SETUP-GUIDE.md](docs/SETUP-GUIDE.md)**.

**For the client:** [docs/Durbans-Pallets-Guide.pdf](docs/Durbans-Pallets-Guide.pdf) — customer and admin journeys with screenshots. Rebuild it with `php docs/build-guide.php` (add `--logins` to also make the private login sheet from `_private/logins.json`).

Every change after that: push to GitHub → 🟧 cPanel → **Git™ Version Control** → **Manage** → **Pull or Deploy** → **Update from Remote** → **Deploy HEAD Commit**.

## Troubleshooting

| You see | Fix |
|---|---|
| "We'll be right back" + a hint | Read the hint: wrong DB name/user/password in `config/secrets.php`, or the user isn't added to the database. |
| "Setting up the database failed" | The database isn't empty (old WordPress tables). Make a **new empty** database and point `secrets.php` at it. |
| Can't sign in as admin | Add `'admin_reset_password' => 'NewPass123'` to `config/secrets.php`, load any page, sign in, then remove the line. |
| Quote emails don't arrive | 🟩 **Admin → Settings → Email** → fill in the mailbox → **Send test email**. Check **Admin → Emails** for the error. |
| Old Google links | Old `/product/...`, `/product-category/...`, `/shop/`, `/about-us/`, `/contact-us/` addresses redirect automatically (`.htaccess`). |
