# 🌟 AstroReport Pro — Complete Plugin Documentation

**Plugin Name:** AstroReport Pro — Ultimate Kundli & Numerology PDF Generator  
**Version:** 2.0.0  
**Developer:** Trikrypta  
**Requires WordPress:** 5.0+  
**Requires PHP:** 7.4+  
**License:** Proprietary

---

## 📖 WHAT IS THIS PLUGIN?

AstroReport Pro is a WordPress plugin that generates **personalized Kundli (Numerology) PDF reports** based on a user's **Date of Birth**. The plugin:

1. Calculates the user's **Mulank** (root number 1-9) from their Date of Birth
2. Generates a **detailed Numerology report** with 20+ sections (career, love, health, money, etc.)
3. Creates a **premium PDF** using **mPDF** with cover page, branded design, and continuous content flow
4. Sends the PDF via email in the user's **selected language** (Hindi / English / Gujarati)
5. Accepts **Razorpay payment** before generating the report (configurable price from admin)
6. Stores all bookings in the **WordPress database** with a full-featured admin dashboard

---

## 🧠 HOW THE REPORT GENERATION WORKS

```
User's Date of Birth (e.g., 1998-05-15)
         ↓
Extract Day = 15
         ↓
Reduce: 1 + 5 = 6
         ↓
Mulank = 6
         ↓
Load report data for Mulank 6 from language file
         ↓
Each section has multiple "variations" of content
         ↓
Pick variations using seed: crc32(name + dob)
(Same person always gets same content)
         ↓
Build HTML report with cover page + sections
         ↓
Convert to PDF using mPDF (server-side)
```

### Report Sections (20+ sections per Mulank):
- परिचय / Introduction
- सकारात्मक गुण / Positive Traits
- नकारात्मक गुण / Negative Traits
- करियर की ताकत / Career Strengths
- उपयुक्त करियर क्षेत्र / Suitable Career Fields
- करियर ग्रोथ पैटर्न / Career Growth Pattern
- आर्थिक ताकत / Financial Strengths
- आर्थिक चुनौतियाँ / Financial Challenges
- आर्थिक सलाह / Financial Advice
- प्रेम स्वभाव / Love Nature
- अनुकूलता / Compatibility
- परिवार जीवन / Family Life
- स्वास्थ्य / Health
- यात्रा और विदेश / Travel & Foreign
- आध्यात्मिक विकास / Spiritual Growth
- जीवन की सीख / Life Lessons
- अंतिम निष्कर्ष / Final Conclusion

---

## 📁 FILE STRUCTURE — WHAT EACH FILE DOES

```
gem-astrology-plugin/
│
├── gem-astrology-plugin.php          ← MAIN PLUGIN FILE (core logic + AJAX handlers)
├── uninstall.php                     ← Cleanup on plugin uninstall (options, DB, PDFs, cron)
├── index.php                         ← Standalone report viewer (works without WordPress)
├── README.md                         ← This documentation file
├── SETUP-GUIDE.md                    ← Step-by-step setup instructions
├── LICENSE                           ← License file
│
├── includes/
│   ├── class-gem-astro-admin.php     ← Admin Dashboard + Settings + Bookings Management
│   ├── class-gem-astro-db.php        ← Database operations (CRUD + stats)
│   ├── class-gem-astro-pdf.php       ← mPDF-based PDF generator (server-side)
│   ├── send-report.php               ← Direct email sender (free report flow)
│   ├── data-mulank.php               ← Combined Mulank data (Hindi JSON)
│   ├── hindi.php                     ← Hindi report content (Mulank 1-9)
│   ├── english.php                   ← English report content (Mulank 1-9)
│   ├── gujarati.php                  ← Gujarati report content (Mulank 1-9)
│   ├── compat-curl.php               ← cURL constant fallback (legacy)
│   └── vendor/                       ← Composer dependencies
│       └── mpdf/                     ← mPDF library for PDF generation
│
├── templates/
│   └── booking-form.php              ← Shortcode form ([astro_report])
│
├── assets/
│   └── js/
│       └── gem-astro-script.js       ← Bridge script for existing forms
│
└── fonts/
    ├── logo.jpg                      ← Cover page logo
    └── NotoSansDevanagari-*.ttf      ← Hindi/Gujarati font files
```

---

## 🔧 DETAILED FILE DESCRIPTIONS

### 1. `gem-astrology-plugin.php` — Main Plugin File
**This is the brain of the plugin.** It handles:

| Function | What It Does |
|----------|-------------|
| `__construct()` | Registers all WordPress hooks, shortcodes, and AJAX handlers |
| `enqueue_assets()` | Loads Razorpay checkout JS and plugin script |
| `render_booking_form()` | Renders the `[astro_report]` shortcode form |
| `get_booking_config()` | Returns AJAX URL, nonce, Razorpay key, and **configurable price** to JavaScript |
| `create_razorpay_order()` | Creates a Razorpay order via `wp_remote_post()` (WordPress HTTP API) |
| `verify_and_save_booking()` | Verifies Razorpay payment signature → saves booking → generates PDF → sends **configurable email** |
| `get_booked_slots()` | Returns already-booked time slots for consultation |
| `get_report_html()` | Generates the full report HTML (cover page + all sections) |

**Constants defined:**
- `GEM_ASTRO_VERSION` = `2.0.0`
- `GEM_ASTRO_PATH` = Plugin directory path
- `GEM_ASTRO_URL` = Plugin directory URL

**AJAX Endpoints:**
| Action Name | Purpose | Auth Required |
|------------|---------|---------------|
| `nion_get_booking_config` | Get config (AJAX URL, nonce, Razorpay key, price) | No |
| `nion_create_rzp_order` | Create Razorpay payment order | No |
| `nion_verify_and_save` | Verify payment + save + PDF + email | No |
| `get_booked_slots` | Get booked consultation slots | No |
| `gem_astro_get_report` | Generate report HTML for display | No |

---

### 2. `includes/class-gem-astro-admin.php` — Admin Dashboard & Settings
**Creates the WordPress admin panel** with:

| Feature | Description |
|---------|-------------|
| **Dashboard** | Today's bookings, total bookings, revenue, PDF count, language distribution |
| **Bookings Table** | All bookings with search, date filter, and **Actions column** |
| **View Booking** | Modal to view full booking details (name, email, DOB, payment ID, etc.) |
| **Delete Booking** | Delete individual bookings with confirmation + AJAX |
| **Settings Page** | All configurable options (see below) |
| **PDF Cleanup Cron** | Automatic daily cleanup of PDFs older than 24 hours |
| **Logo Preview** | Thumbnail preview when logo is selected via media uploader |
| **Email Template** | Customizable email subject and body with `{name}` placeholder |
| **CSV Export** | Export all bookings as CSV |

**Admin AJAX Endpoints:**
| Action | Purpose | Auth Required |
|--------|---------|---------------|
| `gem_astro_export_csv` | Export bookings CSV | Yes (Admin) |
| `gem_astro_get_chart_data` | Dashboard chart data | Yes (Admin) |
| `gem_astro_delete_booking` | Delete a booking | Yes (Admin) |
| `gem_astro_get_booking_details` | View booking details | Yes (Admin) |

**WordPress Admin Menu:**
- **AstroReport Pro** (main menu) → Dashboard
- **AstroReport Pro → Dashboard** (submenu) → Bookings overview
- **AstroReport Pro → Settings** (submenu) → All plugin settings

---

### 3. `includes/class-gem-astro-db.php` — Database Operations
**Handles all database interactions.** Creates table `wp_gem_astro_bookings`.

| Method | What It Does |
|--------|-------------|
| `create_table()` | Creates the bookings table on plugin activation |
| `insert_booking($data)` | Inserts a new booking record |
| `get_booked_slots($date)` | Gets booked time slots for a specific date |
| `get_all_bookings($filters)` | Gets all bookings with search/date filters |
| `get_stats()` | Returns dashboard statistics |
| `get_booking_by_id($id)` | Gets a single booking by ID |
| `delete_booking($id)` | Deletes a booking by ID |

**Database Table Columns:**
| Column | Type | Description |
|--------|------|------------|
| id | INT | Auto-increment primary key |
| created_at | DATETIME | Booking timestamp |
| name | TINYTEXT | Customer name |
| phone | TINYTEXT | Phone number |
| email | TINYTEXT | Email address |
| dob | TINYTEXT | Date of birth |
| notes | TEXT | Optional notes |
| service_type | TINYTEXT | "pdf" or "consultation" |
| date | TINYTEXT | Booking date (consultation) |
| time | TINYTEXT | Booking time (consultation) |
| payment_id | TINYTEXT | Razorpay payment ID |
| payment_status | TINYTEXT | "paid" or "failed" |
| amount | FLOAT | Payment amount |
| language | TINYTEXT | "hi", "en", or "gu" |
| pdf_generated | BOOLEAN | Whether PDF was generated |
| place | TINYTEXT | Place of birth |

---

### 4. `includes/class-gem-astro-pdf.php` — PDF Generator (mPDF)
**Generates PDF files** using the **mPDF** library. Supports Unicode (Hindi/Gujarati).

| Method | What It Does |
|--------|-------------|
| `calculateMulank($dob)` | Calculates Mulank from date of birth |
| `generate_report($booking_data)` | Full PDF generation → saves to uploads folder → returns file path & URL |

**PDF Features:**
- Cover page with configurable logo and branding
- Orange/Peach themed content pages
- Continuous content flow (no unnecessary blank pages)
- Contact page at the end
- Unicode support for Hindi and Gujarati

**PDF Output:** Saved to `wp-content/uploads/gem-astrology-reports/`  
**Filename Format:** `GemAstro-Report-{Name}-{LANG}-{timestamp}.pdf`  
**Auto-cleanup:** PDFs older than 24 hours are automatically deleted via daily cron job.

---

### 5. `includes/send-report.php` — Direct Email Sender
**Standalone PHP script** for free report flow (no payment required).

**What it does:**
1. Receives POST data (name, dob, email, language)
2. Loads WordPress environment for `wp_mail()` and settings
3. Generates PDF in the **selected language only**
4. Sends email with PDF attachment using **admin-configured email template**
5. Falls back to default template if WordPress not available

---

### 6. `uninstall.php` — Plugin Cleanup
**Runs automatically when the plugin is uninstalled** via WordPress admin.

Cleans up:
- All `gem_astro_*` options from `wp_options` table
- `wp_gem_astro_bookings` database table
- All uploaded PDF files in `gem-astrology-reports/`
- Scheduled cron events (`gem_astro_daily_cleanup`)

---

### 7. `templates/booking-form.php` — Shortcode Form
**The booking form** rendered by `[astro_report]` shortcode.

**Contains:**
- Language selector (Hindi / English / Gujarati)
- Input fields: Name, Phone, Email, DOB
- Pay button (configurable price from admin settings)
- Report display area (shows after payment)
- Server-side PDF auto-download
- All JavaScript for: payment → verify → report → PDF download

**JavaScript Functions:**
| Function | What It Does |
|----------|-------------|
| `gemStartPayment()` | Validates form → creates Razorpay order → opens payment |
| `openRazorpay()` | Opens Razorpay checkout popup |
| `verifyAndShowReport()` | Verifies payment → saves booking → auto-downloads PDF |
| `fetchAndDisplayReport()` | Calls AJAX to get report HTML |
| `showReportUI()` | Shows report on page |
| `gemDownloadPDF()` | Downloads PDF using html2pdf.js (client-side fallback) |
| `gemNewReport()` | Resets form for new report |

---

### 8. `assets/js/gem-astro-script.js` — Bridge Script
**Connects existing forms to AstroReport Pro.** This script:

- Reads form field values from standard IDs (`name`, `email`, `phone`, `dob`)
- Also tries Elementor IDs (`form-field-name`, `form-field-email`, etc.)
- Creates Razorpay order
- After payment: verifies → saves → auto-downloads PDF

**Global Functions:**
| Function | What It Does |
|----------|-------------|
| `openGemAstroBooking(type, price, title)` | Start booking from any button |
| `verifyNionGemPayment(response, userData)` | Verify payment from external handler |

---

### 9. Language Data Files

| File | Language | Content |
|------|----------|---------|
| `includes/hindi.php` | Hindi | `getHindiData()` → returns Mulank 1-9 data |
| `includes/english.php` | English | `getEnglishData()` → returns Mulank 1-9 data |
| `includes/gujarati.php` | Gujarati | `getGujaratiData()` → returns Mulank 1-9 data |
| `includes/data-mulank.php` | Hindi (JSON) | `get_mulank_data()` → alternative data source |

Each language file returns an array of Mulank 1-9, each containing 20+ sections with heading and content (some with variations for randomization).

---

## ⚙️ ADMIN SETTINGS (All Configurable)

All settings are configurable from **AstroReport Pro → Settings** in WordPress admin.

### Payment Settings
| Setting | Option Key | Default |
|---------|-----------|---------|
| Razorpay Key ID | `gem_astro_razorpay_key` | — |
| Razorpay Secret Key | `gem_astro_razorpay_secret` | — |
| PDF Report Price (₹) | `gem_astro_pdf_price` | `1` |

### Branding & Contact
| Setting | Option Key | Default |
|---------|-----------|---------|
| Brand Title | `gem_astro_brand_title` | — |
| Tagline | `gem_astro_brand_tagline` | — |
| Website Name | `gem_astro_website_name` | — |
| Website URL | `gem_astro_website_url` | — |
| Phone | `gem_astro_contact_phone` | — |
| Email | `gem_astro_contact_email` | — |

### PDF Cover
| Setting | Option Key | Description |
|---------|-----------|-------------|
| Cover Logo | `gem_astro_cover_logo` | URL to logo image (with live preview) |
| Welcome Text | `gem_astro_cover_welcome_text` | Cover page welcome message |

### Email Template
| Setting | Option Key | Default |
|---------|-----------|---------|
| Email Subject | `gem_astro_email_subject` | `🌟 Your Personalized GEM Astrology Report` |
| Email Body (HTML) | `gem_astro_email_body` | Default greeting with instructions |

> **Note:** Use `{name}` placeholder in subject and body — it gets replaced with the customer's actual name.

---

## 💰 PAYMENT FLOW (Razorpay)

```
Step 1: JavaScript calls → nion_create_rzp_order (AJAX)
           ↓
Step 2: Plugin creates order via wp_remote_post() to Razorpay API
        POST https://api.razorpay.com/v1/orders
        Amount: Configurable from admin (default ₹1)
           ↓
Step 3: Razorpay returns order_id
           ↓
Step 4: JavaScript opens Razorpay Checkout popup
        User enters card/UPI details and pays
           ↓
Step 5: On success → Razorpay returns:
        - razorpay_order_id
        - razorpay_payment_id
        - razorpay_signature
           ↓
Step 6: JavaScript calls → nion_verify_and_save (AJAX)
           ↓
Step 7: Plugin verifies signature using:
        hash_hmac('sha256', order_id + '|' + payment_id, secret_key)
           ↓
Step 8: If verified:
        → Save booking to database
        → Generate PDF (mPDF)
        → Send email with configured template
        → Return PDF URL for download
           ↓
Step 9: JavaScript auto-downloads the PDF
```

---

## � HOW TO CHANGE THE PRICE

The price is now **configurable from admin settings**:

1. Go to **AstroReport Pro → Settings** in WordPress admin
2. Set the **PDF Report Price** field to your desired amount (e.g., `99`)
3. Click **Save Settings**
4. The price automatically updates in:
   - Razorpay order creation
   - Frontend button text
   - Payment verification

> **Note:** If using the Elementor block or custom forms, update the `data-price` attribute manually.

---

## �🛡️ SECURITY FEATURES

| Feature | How |
|---------|-----|
| AJAX Nonce | `wp_create_nonce('gem_astro_nonce')` on every request |
| Input Sanitization | `sanitize_text_field()`, `sanitize_email()` on all inputs |
| Razorpay Signature | `hash_hmac('sha256')` verification |
| Admin Access | `current_user_can('manage_options')` check on all admin AJAX |
| WordPress HTTP API | `wp_remote_post()` for Razorpay API (no raw cURL dependency) |
| XSS Protection | All outputs escaped via `esc_html()`, `esc_attr()`, `htmlspecialchars()` |
| Clean Uninstall | `uninstall.php` removes all data when plugin is deleted |

---

## 🔧 RAZORPAY TEST vs LIVE MODE

| Setting | Test Mode | Live Mode |
|---------|-----------|-----------|
| Key ID | `rzp_test_xxxxx` | `rzp_live_xxxxx` |
| Secret | Test secret from dashboard | Live secret from dashboard |
| Real money? | No (dummy payment) | Yes (real charges) |
| Where to get? | razorpay.com → Dashboard → Settings → API Keys |

---

## 🛠 TROUBLESHOOTING

| Problem | Solution |
|---------|----------|
| Plugin not showing in admin | Deactivate → Reactivate |
| Razorpay popup not opening | Check Key ID in Settings page |
| Payment fails | Verify both Key ID AND Secret Key are correct |
| PDF not downloading | Ensure `wp-content/uploads/` is writable |
| Email not received | Install WP Mail SMTP plugin, check spam folder |
| Report shows blank | Verify language files exist in `includes/` folder |
| "Nonce verification failed" | Clear browser cache, reload page |
| Database table not created | Deactivate → Reactivate plugin |
| mPDF errors | Ensure `includes/vendor/mpdf/` exists and PHP has `mbstring` extension |
| Logo not showing in PDF | Upload logo via Settings and ensure URL is accessible |

---

## 📞 SUPPORT

- **Website:** [https://abhimanyu-raj-cse.vercel.app/](https://abhimanyu-raj-cse.vercel.app/)
- **Phone:** +91 9801834437
- **Email:** novanexusltd001@gmail.com
- **Developer:** Trikrypta

---

**Built with ❤️ by Trikrypta**
