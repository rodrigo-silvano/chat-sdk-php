# Chat SDK PHP — cPanel Shared Hosting Installation Guide

This guide details the step-by-step process to install Chat SDK PHP on a shared hosting server managed via cPanel. Chat SDK PHP is designed in pure PHP 8, without using frameworks, Composer, or Docker, making it easy to run in traditional hosting environments.

---

## Server Requirements
* PHP 8.0 or higher
* PDO MySQL extension enabled in PHP
* Apache Web Server with support for `.htaccess` files and `mod_rewrite` module enabled

---

## Step 1: Configure the Database in cPanel
Since shared hosting environments restrict direct database creation via PHP scripts, you must create the database beforehand through cPanel:

1. Access your cPanel panel.
2. In the **Databases** section, click on **MySQL Databases**.
3. Under **Create New Database**, enter the desired name (e.g., `mysite_chat`) and click **Create Database**.
4. Under **MySQL Users -> Add New User**, enter a username (e.g., `mysite_chatuser`), generate a secure password and save it. Click **Create User**.
5. Under **Add User to Database**, select the created user and database and click **Add**.
6. On the privileges page, select the **ALL PRIVILEGES** option and click **Make Changes**.

---

## Step 2: Upload Project Files
1. Compress all files in the `chat-sdk-php` project folder into a ZIP file (excluding local version control folders like `.git` if you wish).
2. Access cPanel and click on **File Manager**.
3. Navigate to the desired folder where you want to host the chat (e.g., the root folder `public_html`, or create a subfolder like `/public_html/chat/`).
4. Click **Upload**, select the ZIP file and wait for completion.
5. Extract the ZIP file contents in the target folder using the **Extract** tool in cPanel.

---

## Step 3: Run the Installation Wizard
1. Open your browser and navigate to the address corresponding to the folder where you extracted the project with the `/install/` suffix (e.g., `https://your-domain.com/chat/install/`).
2. **Step 1 — Database:**
   * **MySQL Server (Host):** Enter the server address (usually `localhost`).
   * **Database Name:** Enter the full name of the database created in cPanel (e.g., `username_chat`).
   * **MySQL User:** Enter the full name of the MySQL user created in cPanel (e.g., `username_chatuser`).
   * **MySQL Password:** Enter the password you set for the MySQL user.
   * Click **Connect and Configure**. The installer will create the database tables.
3. **Step 2 — Administrator:**
   * **Administrator Name:** Set the primary operator name (e.g., `Admin`).
   * **Administrator Email:** Enter the login email for the admin panel.
   * **Administrator Password:** Set a secure login password (minimum of 8 characters).
   * **Confirm Password:** Confirm the password entered.
   * **Install sample data (Seeding):** Enable this option if you want to test the application immediately with default settings and demo conversations in the database.
   * Click **Finish Installation**. The installer will generate the configuration files `config/database.php` and `config/app.php` with randomly generated secure encryption keys and JWT tokens.

---

## Step 4: Post-Installation Cleanup and Security
After seeing the installation completed successfully message, perform the following security actions:

1. Access the cPanel **File Manager**.
2. Navigate to the chat installation directory.
3. **Permanently delete the `install/` folder** to prevent third parties from restarting the wizard or accessing sensitive files.

---

## Step 5: Integrate the Widget into Your Client Site
To enable the chat widget on any page of your website:

1. Add the following line of HTML code right before the closing `</body>` tag on your pages:
   ```html
   <script src="https://your-domain.com/chat/widget/loader.js" async></script>
   ```
   *(Replace `https://your-domain.com/chat/` with the absolute URL where you installed Chat SDK PHP)*

2. The `loader.js` script handles detecting and dynamically/asynchronously loading the main widget (`widget.js`), ensuring your web page load speed is not affected.
