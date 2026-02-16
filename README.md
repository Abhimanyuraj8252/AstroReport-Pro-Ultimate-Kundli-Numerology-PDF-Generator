# 🌟 AstroReport Pro — Complete Plugin Documentation

**Plugin Name:** AstroReport Pro — Ultimate Kundli & Numerology PDF Generator  
**Version:** 1.0.1  
**Developer:** Trikrypta  
**Requires WordPress:** 5.0+  
**Requires PHP:** 7.4+  
**License:** Proprietary

---

## 📖 WHAT IS THIS PLUGIN?

AstroReport Pro is a WordPress plugin that generates **personalized Kundli (Numerology) PDF reports** based on a user's **Date of Birth**. The plugin:

1. Calculates the user's **Mulank** (root number 1-9) from their Date of Birth
2. Generates a **detailed Numerology report** with 20+ sections (career, love, health, money, etc.)
3. Creates a **premium PDF** with cover page and branded design
4. Sends **3 PDF reports** (Hindi, English, Gujarati) to the user's email
5. Accepts **Razorpay payment** before generating the report
6. Stores all bookings in the **WordPress database** with admin dashboard

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
Convert to PDF using TCPDF (server-side)
or html2pdf.js (client-side browser download)
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
├── gem-astrology-plugin.php          ← MAIN PLUGIN FILE (core logic)
├── index.php                         ← Standalone report viewer (works without WordPress)
├── README.md                         ← This documentation file
├── SETUP-GUIDE.md                    ← Step-by-step setup instructions
│
├── includes/
│   ├── class-gem-astro-admin.php     ← Admin Dashboard + Settings page
│   ├── class-gem-astro-db.php        ← Database operations (create table, insert, query)
│   ├── class-gem-astro-pdf.php       ← TCPDF PDF generator (server-side)
│   ├── send-report.php               ← Email sender (sends 3-language PDFs)
│   ├── data-mulank.php               ← Combined Mulank data (Hindi JSON)
│   ├── hindi.php                     ← Hindi report content (Mulank 1-9)
│   ├── english.php                   ← English report content (Mulank 1-9)
│   ├── gujarati.php                  ← Gujarati report content (Mulank 1-9)
│   └── tcpdf/                        ← TCPDF library for PDF generation
│       ├── tcpdf.php
│       └── ...
│
├── templates/
│   ├── booking-form.php              ← Shortcode form (standalone page form)
│   └── elementor-block.html          ← Existing Nion Booking form (Elementor HTML block)
│
├── assets/
│   └── js/
│       └── gem-astro-script.js       ← Bridge script for existing forms
│
└── fonts/
    ├── logo.jpg                      ← Cover page logo
    └── NotoSansDevanagari-*.ttf      ← Hindi/Gujarati font
```

---

## 🔧 DETAILED FILE DESCRIPTIONS

### 1. `gem-astrology-plugin.php` — Main Plugin File
**This is the brain of the plugin.** It handles:

| Function | What It Does |
|----------|-------------|
| `__construct()` | Registers all WordPress hooks, shortcodes, and AJAX handlers |
| `enqueue_assets()` | Loads Razorpay checkout JS and html2pdf.js |
| `render_booking_form()` | Renders the `[astro_report]` shortcode form |
| `get_booking_config()` | Returns AJAX URL, nonce, and Razorpay key to JavaScript |
| `create_razorpay_order()` | Creates a Razorpay order via API (amount in paise) |
| `verify_and_save_booking()` | Verifies Razorpay payment signature → saves booking → generates PDF → sends email |
| `get_booked_slots()` | Returns already-booked time slots for consultation |
| `get_report_html()` | Generates the full report HTML (cover page + all sections) |

**Constants defined:**
- `GEM_ASTRO_VERSION` = `1.0.1`
- `GEM_ASTRO_PATH` = Plugin directory path
- `GEM_ASTRO_URL` = Plugin directory URL

**AJAX Endpoints (for JavaScript calls):**
| Action Name | Purpose | Auth Required |
|------------|---------|---------------|
| `nion_get_booking_config` | Get config (AJAX URL, nonce, Razorpay key) | No |
| `nion_create_rzp_order` | Create Razorpay payment order | No |
| `nion_verify_and_save` | Verify payment + save + PDF + email | No |
| `get_booked_slots` | Get booked consultation slots | No |
| `gem_astro_get_report` | Generate report HTML for display | No |

---

### 2. `includes/class-gem-astro-admin.php` — Admin Dashboard
**Creates the WordPress admin panel** with:

| Feature | Description |
|---------|-------------|
| Dashboard page | Shows bookings today, total bookings, revenue, PDF count |
| Settings page | Razorpay Key ID and Secret Key input |
| Bookings table | All bookings with search & date filter |
| Engine info | Plugin version, shortcode, developer info |
| How to Use | Step-by-step guide in admin panel |
| Admin CSS | Premium dark-theme styling for admin pages |

**WordPress Admin Menu:**
- **AstroReport Pro** (main menu) → Dashboard
- **AstroReport Pro → Dashboard** (submenu) → Bookings overview
- **AstroReport Pro → Settings** (submenu) → Razorpay keys + info

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

---

### 4. `includes/class-gem-astro-pdf.php` — PDF Generator (Server-Side)
**Generates actual PDF files** using TCPDF library. Used for email attachments.

| Method | What It Does |
|--------|-------------|
| `calculateMulank($dob)` | Calculates Mulank from date of birth |
| `generate_report($booking_data)` | Full PDF generation → saves to uploads folder → returns file path & URL |
| `drawCoverPage($pdf, $data, $lang)` | Draws the PDF cover page with name and DOB |
| `drawMulankSection($pdf, $data, $lang)` | Draws all Mulank sections with headings and content |

**PDF Output:** Saved to `wp-content/uploads/gem-astrology-reports/`  
**Filename Format:** `GemAstro-Report-{Name}-{LANG}-{timestamp}.pdf`

---

### 5. `includes/send-report.php` — Email Report Sender
**Standalone PHP script** that generates PDFs in all 3 languages and emails them.

**What it does:**
1. Receives POST data (name, dob, email)
2. Generates PDF for English, Hindi, and Gujarati
3. Sends ONE email with all 3 PDFs as attachments
4. Uses `wp_mail()` (WordPress) or mock mail (standalone)

---

### 6. `templates/booking-form.php` — Shortcode Form
**The standalone booking form** rendered by `[astro_report]` shortcode.

**Contains:**
- Language selector (Hindi / English / Gujarati)
- Input fields: Name, Phone, Email, DOB
- Pay button (₹1)
- Report display area (shows after payment)
- PDF download button (uses html2pdf.js)
- All JavaScript for: payment → verify → report → PDF download

**JavaScript Functions:**
| Function | What It Does |
|----------|-------------|
| `gemStartPayment()` | Validates form → creates Razorpay order → opens payment |
| `openRazorpay()` | Opens Razorpay checkout popup |
| `verifyAndShowReport()` | Verifies payment → saves booking |
| `fetchAndDisplayReport()` | Calls AJAX to get report HTML |
| `showReportUI()` | Shows report on page + auto-downloads PDF |
| `gemDownloadPDF()` | Downloads PDF using html2pdf.js (client-side) |
| `gemNewReport()` | Resets form for new report |

---

### 7. `templates/elementor-block.html` — Existing Nion Booking Form
**This is the existing multi-service booking form** used on niongemastro.com.

**Services included:**
- Consultation Services (₹499, requires slot booking)
- Kundali Report PDF (₹1, instant delivery)

**Multi-step flow:**
1. Service selection → 2. Date → 3. Time → 4. Details → 5. Preview → 6. Pay

**Form field IDs used:**
| Field | Element ID |
|-------|-----------|
| Name | `name` |
| Phone | `phone` |
| Email | `email` |
| DOB | `dob` |
| Notes | `notes` |

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

## 💰 PAYMENT FLOW (Razorpay)

```
Step 1: JavaScript calls → nion_create_rzp_order (AJAX)
           ↓
Step 2: Plugin creates order via Razorpay API
        POST https://api.razorpay.com/v1/orders
        Amount: ₹1 (100 paise)
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
        → Generate PDF (TCPDF)
        → Send email with 3-language PDFs
        → Return PDF URL for download
           ↓
Step 9: JavaScript auto-downloads the PDF
```

---

## 🛡️ SECURITY FEATURES

| Feature | How |
|---------|-----|
| AJAX Nonce | `wp_create_nonce('gem_astro_nonce')` on every request |
| Input Sanitization | `sanitize_text_field()`, `sanitize_email()` on all inputs |
| Razorpay Signature | `hash_hmac('sha256')` verification |
| Admin Access | `current_user_can('manage_options')` check |
| Direct Access Block | `if (!defined('ABSPATH')) exit;` in all PHP files |

---

## 💲 HOW TO CHANGE THE PRICE

The price `₹1` is set in **3 places**:

| File | Line | What to Change |
|------|------|---------------|
| `templates/booking-form.php` | Button text | `'Get Instant Report & Pay ₹1'` |
| `templates/booking-form.php` | JS amount | `data.append('amount', 1);` and `amount: 100` (paise) |
| `templates/elementor-block.html` | Card price | `data-price="1"` and display `₹1` |

**Example:** To change to ₹99:
- Button text → `'Get Instant Report & Pay ₹99'`
- JS amount → `data.append('amount', 99);` and `amount: 9900`
- Elementor → `data-price="99"` and display `₹99`

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
| PDF not downloading | Install TCPDF: `composer require tecnickcom/tcpdf` |
| Email not received | Install WP Mail SMTP plugin, check spam folder |
| Report shows blank | Verify language files exist in `includes/` folder |
| "Nonce verification failed" | Clear browser cache, reload page |
| Database table not created | Deactivate → Reactivate plugin |

---

## 📞 SUPPORT

- **Website:** [https://abhimanyu-raj-cse.vercel.app/](https://abhimanyu-raj-cse.vercel.app/)
- **Phone:** +91 9801834437
- **Email:** novanexusltd001@gmail.com
- **Developer:** Trikrypta

---

**Built with ❤️ by Trikrypta**
