# AstroReport Pro — Complete Setup Guide (Step-by-Step, Full Details)

Yeh guide beginners ke liye full detail mein likhi gayi hai, taaki aap plugin ko kisi bhi WordPress site par properly setup kar pao.

Guide ke 3 complete use-cases:
1. Payment ke saath (Razorpay)
2. Without payment (free report flow)
3. Existing form integration (Elementor/HTML/custom form)

---

## 0) Is Guide Ko Kaise Use Karein

Sabse pehle yeh decide karo aapko kaunsa mode chahiye:
- Agar aap paid consultation/report bechna chahte ho → Section 5 follow karo
- Agar free report dena chahte ho → Section 6 follow karo
- Agar aapka form pehle se bana hua hai → Section 7 follow karo

Recommended order:
1. Section 1 (overview)
2. Section 2 (requirements)
3. Section 3 (installation)
4. Section 4 (basic verification)
5. Fir apna mode (5/6/7)
6. Section 8, 9, 10 for testing + troubleshooting + production checklist

---

## 1) Plugin Overview (Aapko kya samajhna zaroori hai)

Plugin ka internal flow:
1. User form fill karta hai (name, phone, email, DOB, language, service)
2. Mulank calculate hota hai
3. Language file se content fetch hota hai (`hi/en/gu`)
4. **mPDF** se PDF generate hota hai (Unicode supported — Hindi, Gujarati fonts work perfectly)
5. Booking DB mein save hoti hai
6. Response mein `pdf_url` aur status aata hai
7. Email bheja jaata hai (admin settings se **customizable template** ke saath)

Important AJAX actions:
- `nion_get_booking_config`
- `nion_create_rzp_order`
- `nion_verify_and_save`
- `get_booked_slots`

Default shortcode:
- `[astro_report]`

---

## 2) Requirements Checklist (Install se pehle)

### Minimum system requirements
- WordPress 5+
- PHP 7.4+ (8.x preferred)
- PHP `mbstring` extension (mPDF ke liye required)
- HTTPS enabled (especially payment site ke liye)
- Writable directory: `wp-content/uploads`

### Plugin package requirements
Ensure yeh files/folders exist karte hon:
- `gem-astrology-plugin.php`
- `uninstall.php`
- `includes/class-gem-astro-pdf.php`
- `includes/class-gem-astro-admin.php`
- `includes/class-gem-astro-db.php`
- `includes/send-report.php`
- `includes/vendor/mpdf/` (mPDF library)
- `templates/booking-form.php`

### Mail delivery requirement (recommended)
- SMTP plugin install karo (jaise WP Mail SMTP)
- Agar SMTP setup nahi hoga to PDF generate hone ke baad bhi email fail ho sakti hai

---

## 3) Plugin Installation (Detailed)

## Method A: WordPress Admin se install (Recommended)

Step 1: Plugin zip banao
1. Local machine par `gem-astrology-plugin` folder locate karo
2. Us folder ka zip banao
3. Final file name example: `gem-astrology-plugin.zip`

Step 2: WordPress admin login
1. Open karo: `https://yourdomain.com/wp-admin`
2. Admin credentials se login karo

Step 3: Plugin upload aur activate
1. `Plugins` → `Add New`
2. `Upload Plugin` button click
3. Zip choose karo
4. `Install Now`
5. `Activate Plugin`

Step 4: Verify plugin active hai
1. `Plugins` list mein `AstroReport Pro` dekhna chahiye
2. Left sidebar mein **AstroReport Pro** menu visible hona chahiye

## Method B: cPanel/FTP se install

Step 1: Folder upload
1. cPanel/FTP open karo
2. Path open karo: `wp-content/plugins/`
3. `gem-astrology-plugin` folder upload karo

Step 2: Activation
1. WP Admin → `Plugins`
2. `AstroReport Pro` activate karo

---

## 4) Basic Verification (Mode choose karne se pehle)

Yeh 6 checks zaroor karo:

1. **PHP fatal to nahi aa raha**
   - `wp-content/debug.log` check karo (agar WP_DEBUG enabled hai)

2. **mPDF engine available hai**
   - Confirm karo: `includes/vendor/mpdf/` folder exists
   - PHP `mbstring` extension enabled hai

3. **Upload writable hai**
   - `wp-content/uploads/` writable hona chahiye

4. **AJAX endpoint accessible hai**
   - Browser/network console mein `admin-ajax.php` requests fail nahi honi chahiye

5. **Nonce config aa raha hai**
   - Frontend JS mein `window.NION_BOOKING` object present hona chahiye

6. **Admin Dashboard load ho raha hai**
   - Left menu mein AstroReport Pro click karo — dashboard dikhna chahiye

---

## 5) Payment Mode Setup (Razorpay) — Full Steps

Yeh production recommended flow hai.

### Step 5.1: Razorpay account setup
1. Razorpay dashboard login karo
2. `Settings` → `API Keys`
3. `Generate Key`
4. `Key ID` and `Key Secret` copy karo

Note:
- Test ke liye `rzp_test_...`
- Live ke liye `rzp_live_...`

### Step 5.2: Plugin settings mein configure karo
1. WP Admin open karo
2. **AstroReport Pro → Settings** page kholo
3. Aapko yeh sab configure karna hai:

| Setting | Kya daalein |
|---------|------------|
| **Razorpay Key ID** | `rzp_test_xxx` ya `rzp_live_xxx` |
| **Razorpay Secret** | Secret key from Razorpay |
| **PDF Report Price (₹)** | Amount (e.g., `99`, `499`) — **ab admin se configurable hai!** |
| **Brand Title** | Aapke brand ka naam |
| **Tagline** | Tagline ya subtitle |
| **Website Name** | Website display name |
| **Website URL** | Full URL |
| **Phone** | Contact phone |
| **Email** | Contact email |
| **Cover Logo** | Click "Select Logo" → media library se choose karo → **preview dikhega** |
| **Welcome Text** | Cover page par welcome message |
| **Email Subject** | Email ka subject (use `{name}` for customer name) |
| **Email Body** | HTML email template (use `{name}` for customer name) |

4. **Save Settings** click karo

### Step 5.3: Frontend page create karo
Option 1 (shortcode):
1. `Pages` → `Add New`
2. Page title: `Book Astrology Report`
3. Content mein add karo:

```text
[astro_report]
```

4. Publish

Option 2 (custom form integration):
1. Existing HTML widget/template mein JS config values available karo:
   - `window.NION_BOOKING.ajax_url`
   - `window.NION_BOOKING.nonce`
   - `window.NION_BOOKING.razorpay_key`
   - `window.NION_BOOKING.price`

### Step 5.4: End-to-end payment test
1. Test key mode on rakho
2. Frontend form open karo
3. Language select karo
4. Name, Phone, Email, DOB fill karo
5. Pay button click karo
6. Razorpay popup mein test payment complete karo
7. Payment success ke baad verify:
   - ✅ PDF auto-download hua
   - ✅ Report screen par dikhta hai
   - ✅ Booking admin dashboard mein dikhi
   - ✅ Email receive hui (PDF attached)

### Step 5.5: Live jaane se pehle final switch
1. Plugin settings mein test keys remove karo
2. Live keys save karo
3. Price set karo (actual amount)
4. Ek real transaction test run karo
5. Razorpay dashboard + site DB dono match karo

---

## 6) Without Payment Setup (Free Flow) — Full Steps

Aapke paas 2 reliable methods hain.

## Method 6A (Simple + Fast): `send-report.php` direct form submit

Best for: lead magnet style free report page.

### Required POST fields
- `name`
- `dob`
- `email`
- `language` (`hi`, `en`, `gu`)

### Step-by-step
1. Ek new page banao (WordPress page ya static HTML block)
2. Neeche wala form paste karo
3. `action` URL ko apne domain ke hisab se adjust karo
4. Submit test run karo

```html
<form action="/wp-content/plugins/gem-astrology-plugin/includes/send-report.php" method="post">
  <input name="name" placeholder="Full Name" required>
  <input name="dob" placeholder="YYYY-MM-DD" required>
  <input name="email" type="email" placeholder="Email" required>
  <select name="language" required>
   <option value="en">English</option>
   <option value="hi">Hindi</option>
   <option value="gu">Gujarati</option>
  </select>
  <button type="submit">Get Free Report</button>
</form>
```

Expected result:
- Payment skip hoga
- Selected language report generate hogi
- Email bheja jaayega admin-configured template ke saath
- **Email subject/body admin settings se aayega** (`{name}` auto-replace hoga)

## Method 6B (Advanced): Existing UI rakho, payment step skip karo

Best for: same booking UI chahiye but free mode chalana hai.

### Step-by-step
1. Frontend se `nion_get_booking_config` call karo (nonce lo)
2. `nion_create_rzp_order` call mat karo
3. Direct `nion_verify_and_save` call karo
4. Payload mein `price: 0` pass karo
5. `pdf_url` return par PDF open/download karao

Example payload:

```js
{
  action: 'nion_verify_and_save',
  nonce,
  booking_type: 'pdf',
  date: '',
  time: '',
  service: 'Kundali Report',
  duration: 0,
  price: 0,
  language: 'hi',
  name: 'User Name',
  phone: '9999999999',
  email: 'user@example.com',
  dob: '1998-05-15',
  notes: '',
  razorpay_order_id: 'FREE_ORDER',
  razorpay_payment_id: 'FREE_PAYMENT',
  razorpay_signature: 'FREE_SIG'
}
```

---

## 7) Existing Form Integration (Elementor/HTML/custom) — Full Steps

Yeh section un users ke liye hai jinka form pehle se bana hua hai.

### 7.1 Field mapping table
Ensure aapke existing form mein yeh fields available hon:
- `name`
- `phone`
- `email`
- `dob`
- `service`
- `booking_type` (`consultation` / `order` / `pdf`)
- `date`
- `time`
- `language` (order/pdf mode ke liye)
- `notes` (optional)

### 7.2 Integration architecture

Paid mode request order:
1. `nion_get_booking_config`
2. `nion_create_rzp_order`
3. Razorpay checkout
4. `nion_verify_and_save`

Free mode request order:
1. `nion_get_booking_config`
2. `nion_verify_and_save`

### 7.3 Copy-paste helper (config + save)

```js
async function getPluginConfig(ajaxUrl) {
  const body = new URLSearchParams({ action: 'nion_get_booking_config' });
  const res = await fetch(ajaxUrl, { method: 'POST', body });
  const json = await res.json();
  if (!json.success) throw new Error('Config fetch failed');
  return json.data;
}

function openPdfIfAny(pdfUrl) {
  if (!pdfUrl) return;
  const a = document.createElement('a');
  a.href = pdfUrl;
  a.target = '_blank';
  a.rel = 'noopener';
  a.download = '';
  document.body.appendChild(a);
  a.click();
  a.remove();
}

async function saveBooking(ajaxUrl, payload) {
  const body = new URLSearchParams(payload);
  const res = await fetch(ajaxUrl, { method: 'POST', body });
  return res.json();
}
```

### 7.4 Existing form integration (paid)
1. Form submit event capture karo
2. Fields read karo
3. Config fetch karo
4. Order create karo
5. Razorpay open karo
6. Success handler me `nion_verify_and_save` call karo
7. `pdf_url` open karao

### 7.5 Existing form integration (without payment)
1. Form submit event capture karo
2. Config fetch karo
3. Direct `nion_verify_and_save` call karo
4. Response success par `pdf_url` open/download

---

## 8) Validation & Testing SOP (Step-by-Step)

## 8.1 Paid mode test checklist
1. Frontend page open hota hai
2. Language choose hota hai
3. Details validation sahi chalti hai
4. Razorpay popup open hota hai
5. Payment success callback hit hota hai
6. `nion_verify_and_save` success aata hai
7. PDF URL milta hai
8. PDF open/download hoti hai
9. Booking DB entry create hoti hai
10. Email deliver hoti hai (configured template ke saath)

## 8.2 Free mode test checklist
1. Form bina payment submit hota hai
2. API response success aata hai
3. PDF generate hoti hai
4. PDF URL usable hota hai
5. Email receive hoti hai

## 8.3 Admin dashboard test checklist
1. Dashboard loads without errors
2. Stats cards show correct data
3. Bookings table shows all bookings
4. **View button** opens booking details modal
5. **Delete button** removes booking with confirmation
6. CSV export works
7. Search and date filters work

## 8.4 Settings test checklist
1. All fields save correctly
2. **Logo preview** shows after selecting an image
3. **Email template** fields accept HTML and `{name}` placeholder
4. Price change reflects in frontend payment amount

---

## 9) Common Errors + Exact Fix

### Error 1: PDF blank ya "Section" heading aa rahi hai
Cause:
- Language data structure malformed hai

Fix:
1. `includes/hindi.php`
2. `includes/english.php`
3. `includes/gujarati.php`
4. Check every section has:
  - `heading`
  - `content`

### Error 2: AJAX response `-1`
Cause:
- Nonce invalid/expired

Fix:
1. Fresh call: `nion_get_booking_config`
2. New nonce use karo
3. Retry request

### Error 3: Payment popup open nahi hota
Cause:
- Razorpay key missing
- checkout script block

Fix:
1. Key verify in settings
2. Browser console check
3. Ensure script load ho raha:
  - `https://checkout.razorpay.com/v1/checkout.js`

### Error 4: Email nahi aa rahi
Cause:
- Server mail setup issue

Fix:
1. SMTP plugin install/configure karo
2. Test mail send karo
3. Spam folder check karo
4. Admin settings mein email template check karo

### Error 5: PDF generate nahi ho rahi
Cause:
- Upload permission issue ya mPDF error

Fix:
1. `wp-content/uploads/gem-astrology-reports/` writable karo
2. PHP error logs check karo
3. PHP `mbstring` extension enabled karo
4. `includes/vendor/mpdf/` folder exists karo

### Error 6: Logo PDF mein nahi dikh raha
Cause:
- Logo URL invalid ya file accessible nahi hai

Fix:
1. Settings mein logo select karo via "Select Logo" button
2. Preview dekh kar confirm karo
3. Ensure URL publicly accessible hai

---

## 10) Production Launch Checklist (Must Do)

Go-live se pehle:
- [ ] Full site backup liya
- [ ] Test mode se live key switch verify kiya
- [ ] **Price set kiya** (`gem_astro_pdf_price` in admin settings)
- [ ] **Email template customize kiya** (subject + body)
- [ ] **Logo uploaded** and preview verified
- [ ] **Branding info filled** (name, tagline, website, phone, email)
- [ ] One live payment transaction tested
- [ ] DB entry + PDF + email all verified
- [ ] SMTP stable hai
- [ ] Error logging ON hai (`WP_DEBUG_LOG = true`)

Recommended production strategy:
- Paid consultation/order pages: Razorpay mode
- Marketing page: free mini-report (`send-report.php`)
- Existing brand form: plugin backend integration

---

## 11) Developer Quick Reference

Core files:
- `gem-astrology-plugin.php` (hooks + AJAX registration)
- `includes/class-gem-astro-pdf.php` (mPDF PDF generation)
- `includes/class-gem-astro-admin.php` (admin panel + settings + bookings)
- `includes/class-gem-astro-db.php` (bookings table CRUD)
- `includes/send-report.php` (free direct report flow)
- `templates/booking-form.php` (default shortcode UI)
- `uninstall.php` (cleanup on uninstall)

Useful AJAX actions:
- `nion_get_booking_config`
- `nion_create_rzp_order`
- `nion_verify_and_save`
- `get_booked_slots`
- `gem_astro_get_report`

Admin-only AJAX actions:
- `gem_astro_export_csv`
- `gem_astro_get_chart_data`
- `gem_astro_delete_booking`
- `gem_astro_get_booking_details`

---

## 12) Fast Decision Matrix

If you are...
- New WordPress site + no custom form → `[astro_report]` use karo (Section 5)
- Free report funnel banana hai → `send-report.php` use karo (Section 6A)
- Existing Elementor/custom form already live hai → Section 7 integration karo

---

## 13) Plugin Uninstall

Plugin delete karne par yeh sab automatically clean hota hai:
- ✅ Saare plugin options (`gem_astro_*`)
- ✅ Database table (`wp_gem_astro_bookings`)
- ✅ Uploaded PDF files
- ✅ Scheduled cron jobs

> **Note:** Yeh sirf tab hota hai jab plugin ko WordPress admin se "Delete" karo. Deactivate karne se data safe rehta hai.

---

**Built with ❤️ by Trikrypta**
