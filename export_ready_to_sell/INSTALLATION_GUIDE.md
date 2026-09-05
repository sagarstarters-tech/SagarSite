# 🚀 E-Commerce Platform — Quick Installation Guide

This guide provides simple step-by-step instructions to install and configure the E-Commerce store on any standard web hosting (CPanel, Hostinger, Plesk) or local server (XAMPP).

---

## 📋 System Requirements
* **PHP Version:** PHP 7.4, 8.0, 8.1, or 8.2
* **Database:** MySQL 5.7+ or MariaDB 10.3+
* **Apache Modules:** `mod_rewrite` enabled
* **PHP Extensions:** `pdo_mysql`, `curl`, `mbstring`, `json`, `gd`, `openssl`

---

## 🛠️ Step-by-Step Setup

### Step 1: Upload Project Files
1. Upload the project files into your server's public directory:
   - For main domain: `public_html/`
   - For subdomain/subfolder: `public_html/store/` (or `c:/xampp/htdocs/store/` if using XAMPP).
2. Ensure directory permissions:
   - Make the `uploads/` and `logs/` folders writable (`chmod 755` or `775`).

---

### Step 2: Create & Import Database
1. Go to your hosting control panel and open **phpMyAdmin** (or MySQL CLI).
2. Create a new empty database (e.g., `ecommerce_db` with collation `utf8mb4_general_ci`).
3. Click on the **Import** tab.
4. Choose the file `clean_schema.sql` included in this package and click **Import / Go**.

---

### Step 3: Configure Environment (`.env`)
1. In the project root directory, find the file `.env.example`.
2. Rename or copy it to `.env`.
3. Open `.env` in a text editor and enter your credentials:
   - Set `SITE_URL` to your full website domain (e.g. `https://yourstore.com`).
   - Fill in your database details (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
   - Enter your official `LICENSE_KEY` provided by your vendor (e.g. `LICENSE_KEY=LIC-xxxxx...`).
   - (Optional) Enter your PhonePe credentials (`PHONEPE_MERCHANT_ID`, `PHONEPE_SALT_KEY`).
   - (Optional) Enter your SMTP email credentials.
4. Save the `.env` file.

---

### Step 4: Access Admin Dashboard
Once installed, you can immediately access your store's administration panel:

* **Admin URL:** `https://yourstore.com/admin/`
* **Default Admin Email:** `admin@example.com`
* **Default Admin Password:** `Admin@123`

> [!IMPORTANT]
> Immediately upon your first login, navigate to **Admin Settings / Profile** and change the admin password and email address to your own secure credentials!

---

### Step 5: Configure Store Details
Inside the Admin Panel:
1. Go to **Settings > General**:
   - Update your Store Name, Contact Email, Phone Number, and Physical Address.
   - Upload your custom Store Logo and Favicon.
2. Go to **Settings > Payment Gateways**:
   - Enable Cash on Delivery (COD) and/or PhonePe Online Payments.
3. Go to **Catalog > Products**:
   - Add your products, categories, images, and prices.

Your store is now ready to take orders! 🎉
