# 🛠️ AstroReport Pro — COMPLETE SETUP GUIDE

**Yeh guide un logo ke liye hai jinko WordPress ka zyada pata nahi hai.**  
**Har step copy-paste ready hai. Bas follow karo — sab set ho jayega.**

---

## 📋 TABLE OF CONTENTS

1. [Plugin Install Karna](#-step-1-plugin-install-karna)
2. [Plugin Activate Karna](#-step-2-plugin-activate-karna)
3. [Razorpay Keys Setup](#-step-3-razorpay-keys-setup)
4. [TCPDF Install Karna (PDF ke liye)](#-step-4-tcpdf-install-karna)
5. [Option A: Fresh Page Banana (Naya Form)](#-option-a-fresh-page-banana-naya-form)
6. [Option B: Existing Form Mein Integrate Karna](#-option-b-existing-form-mein-integrate-karna)
7. [Testing Karna](#-step-6-testing)
8. [Price Change Karna](#-step-7-price-change-karna)
9. [Problems & Solutions](#-problems--solutions)

---

## 🔵 STEP 1: Plugin Install Karna

### Method 1: WordPress Admin Se (Sabse Easy)

1. Apne computer mein `gem-astrology-plugin` folder ko **ZIP** karo
   - Windows: Right-click → Send to → Compressed (zipped) folder
   - Mac: Right-click → Compress

2. WordPress admin panel kholo:
   ```
   https://yourwebsite.com/wp-admin/
   ```

3. Left sidebar mein **Plugins** pe click karo

4. Upar **"Add New"** button click karo

5. Upar **"Upload Plugin"** button click karo

6. **"Choose File"** click karo → apna ZIP file select karo

7. **"Install Now"** click karo

8. Wait karo... install hone do

9. **"Activate Plugin"** click karo

✅ **Done! Plugin install ho gaya.**

---

### Method 2: cPanel / FTP Se

1. cPanel kholo:
   ```
   https://yourwebsite.com/cpanel
   ```

2. **File Manager** kholo

3. Yeh folder dhundho:
   ```
   public_html/wp-content/plugins/
   ```

4. **Upload** button click karo

5. Apna `gem-astrology-plugin` folder upload karo

6. WordPress admin mein jao → **Plugins** → **"AstroReport Pro"** ko **Activate** karo

✅ **Done!**

---

## 🔵 STEP 2: Plugin Activate Karna

1. WordPress admin panel kholo:
   ```
   https://yourwebsite.com/wp-admin/
   ```

2. Left sidebar mein **Plugins** click karo

3. List mein **"AstroReport Pro"** dhundho

4. **"Activate"** click karo

5. Ab left sidebar mein **"AstroReport Pro"** menu dikhega 🌟

✅ **Plugin active!**

---

## 🔵 STEP 3: Razorpay Keys Setup

### Step 3.1: Razorpay Se Keys Lena

1. Yeh link kholo:
   ```
   https://dashboard.razorpay.com/
   ```

2. Login karo (ya account banao)

3. Left sidebar mein **Settings** click karo

4. **"API Keys"** tab click karo

5. **"Generate Key"** button click karo

6. **2 cheezein copy karo:**
   - **Key ID** = `rzp_live_xxxxxxxxxxxx` (ya `rzp_test_xxxxxxxxxxxx`)
   - **Key Secret** = `xxxxxxxxxxxxxxxxxxxxxxx`

   > ⚠️ **Secret sirf ek baar dikhta hai!** Save kar lo notepad mein.

### Step 3.2: Plugin Mein Keys Dalna

1. WordPress admin mein jao

2. Left sidebar → **AstroReport Pro → Settings**

3. **"Razorpay Key ID"** field mein paste karo:
   ```
   rzp_live_xxxxxxxxxxxx
   ```

4. **"Razorpay Secret Key"** field mein paste karo:
   ```
   xxxxxxxxxxxxxxxxxxxxxxx
   ```

5. **"💾 Save Settings"** button click karo

✅ **Razorpay connected!**

---

## 🔵 STEP 4: TCPDF Install Karna

TCPDF zaruri hai — iske bina PDF email mein nahi jayega.

### Method 1: SSH / Terminal Se (Recommended)

1. Server pe SSH connect karo

2. Plugin folder mein jao:
   ```bash
   cd /var/www/html/wp-content/plugins/gem-astrology-plugin/
   ```
   (ya apna exact path dalo)

3. Yeh command run karo:
   ```bash
   composer require tecnickcom/tcpdf
   ```

4. Wait karo... install ho jayega

✅ **TCPDF installed!**

### Method 2: Agar Composer Nahi Hai

1. Yeh link kholo:
   ```
   https://github.com/tecnickcom/TCPDF/releases
   ```

2. Latest version download karo (ZIP)

3. Unzip karo

4. Folder rename karo → `tcpdf`

5. Upload karo yahan:
   ```
   wp-content/plugins/gem-astrology-plugin/includes/tcpdf/
   ```

✅ **TCPDF manually installed!**

---

## 🟢 OPTION A: FRESH PAGE BANANA (NAYA FORM)

Agar aap ek **bilkul nayi page** banana chahte ho jisme AstroReport form ho:

### Step A.1: Naya Page Banao

1. WordPress admin → Left sidebar → **Pages → Add New**

2. Page ka title likho:
   ```
   Kundli Report
   ```

3. Page editor mein (content area mein) **yeh likho:**
   ```
   [astro_report]
   ```
   Bas itna hi. Aur kuch nahi.

4. **"Publish"** button click karo

✅ **Page bana! Ab `https://yourwebsite.com/kundli-report/` pe form dikhega.**

---

### Step A.2: Agar Elementor Use Karte Ho

1. Page kholo → **"Edit with Elementor"** click karo

2. Left panel se **"Shortcode"** widget drag karo page pe

3. Shortcode field mein likho:
   ```
   [astro_report]
   ```

4. **"Update"** click karo

✅ **Elementor page ready!**

---

### Step A.3: Agar Divi Use Karte Ho

1. Page kholo Divi builder mein

2. **"Code"** module add karo

3. Code field mein paste karo:
   ```
   [astro_report]
   ```

4. Save karo

✅ **Divi page ready!**

---

### Step A.4: PHP Template Se (Developer Method)

Agar kisi `.php` template file mein form add karna ho:

```php
<?php echo do_shortcode('[astro_report]'); ?>
```

Yeh code apne theme ke kisi bhi template file mein paste karo.

---

## 🟠 OPTION B: EXISTING FORM MEIN INTEGRATE KARNA

**Yeh section sabse important hai agar aapka form pehle se bana hua hai** (jaise niongemastro.com pe Elementor se bana booking form).

### Step B.1: Samjho Kya Hoga

```
Existing form                        →  AstroReport Pro Plugin
────────────                              ──────────────────────
User form bharta hai                      
User "Pay Now" click karta hai            
Razorpay payment hota hai                 
Payment success                    ─────→ Plugin ko bolo:
                                          "Yeh user ne pay kar diya,
                                           ab PDF banao aur email karo"
                                              ↓
                                          PDF auto-download
                                          3 language PDF email
```

### Step B.2: Apne Existing Form Ka JavaScript Dhundho

1. WordPress admin mein jao

2. Apna page kholo jisme form hai

3. **"Edit with Elementor"** click karo (ya jo builder use karte ho)

4. Form ke andar wala **HTML/Code widget** dhundho

5. Usme JavaScript code hoga — Razorpay wala section dhundho

6. `handler: function(response)` dhundho — yeh Razorpay payment success wala function hai

### Step B.3: Integration Code Add Karo

Apne existing JavaScript mein, **Razorpay payment success handler** ke andar, yeh code add karo:

```javascript
handler: function(response) {
    // ══════════════════════════════════════════
    // ASTROREPORT PRO INTEGRATION — START
    // ══════════════════════════════════════════
    
    // Step 1: User ke form se data lo
    var userName  = document.getElementById('name').value;
    var userPhone = document.getElementById('phone').value;
    var userEmail = document.getElementById('email').value;
    var userDob   = document.getElementById('dob').value;

    // Step 2: Plugin ko bolo — "payment verify karo + PDF banao + email bhejo"
    var formData = new URLSearchParams();
    formData.append('action', 'nion_verify_and_save');
    formData.append('nonce', NION_BOOKING.nonce);
    
    // Razorpay ka data
    formData.append('razorpay_order_id', response.razorpay_order_id);
    formData.append('razorpay_payment_id', response.razorpay_payment_id);
    formData.append('razorpay_signature', response.razorpay_signature);
    
    // User ka data
    formData.append('name', userName);
    formData.append('phone', userPhone);
    formData.append('email', userEmail);
    formData.append('dob', userDob);
    formData.append('booking_type', 'pdf');
    formData.append('price', 1);
    formData.append('language', 'hi');
    formData.append('notes', '');
    formData.append('date', '');
    formData.append('time', '');

    fetch(NION_BOOKING.ajax_url, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success && res.data.pdf_url) {
                // PDF auto-download
                var link = document.createElement('a');
                link.href = res.data.pdf_url;
                link.download = 'Kundli_Report_' + userName + '.pdf';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                alert('✅ Report generated! PDF downloading. Check email for all 3 language reports.');
            } else {
                alert('✅ Payment successful! Report email sent.');
            }
        })
        .catch(function(err) {
            console.error('AstroReport Error:', err);
            alert('Payment done! Report will be emailed.');
        });
    
    // ══════════════════════════════════════════
    // ASTROREPORT PRO INTEGRATION — END
    // ══════════════════════════════════════════
}
```

### Step B.4: NION_BOOKING Variable Ensure Karo

Plugin automatically yeh variable create karta hai jab plugin active hota hai. Agar nahi bana ho to apne **theme ke `functions.php`** mein yeh add karo:

1. WordPress admin → **Appearance → Theme File Editor**

2. Right side mein **`functions.php`** file select karo

3. File ke end mein (last `?>` se pehle) yeh paste karo:

```php
// AstroReport Pro - Ensure NION_BOOKING is available
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script('razorpay-checkout', 'https://checkout.razorpay.com/v1/checkout.js', [], null, true);
    wp_localize_script('razorpay-checkout', 'NION_BOOKING', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('gem_astro_nonce'),
        'razorpay_key' => get_option('gem_astro_razorpay_key', ''),
    ]);
});
```

4. **"Update File"** click karo

✅ **Ab NION_BOOKING har page pe available hoga!**

### Step B.5: Form Field IDs Check Karo

Plugin ko yeh field IDs chahiye. Check karo apke form mein yeh IDs hain:

| Field | Expected ID | Kaise Check Karo |
|-------|------------|-----------------|
| Name | `name` | Form pe right-click → Inspect → input ka `id` dekho |
| Phone | `phone` | Same |
| Email | `email` | Same |
| DOB | `dob` | Same |

**Agar aapke form mein alag IDs hain** (jaise Elementor = `form-field-name`), to Step B.3 ke code mein IDs change karo:

```javascript
// CHANGE THESE to match your form's actual field IDs:
var userName  = document.getElementById('YOUR-NAME-FIELD-ID').value;
var userPhone = document.getElementById('YOUR-PHONE-FIELD-ID').value;
var userEmail = document.getElementById('YOUR-EMAIL-FIELD-ID').value;
var userDob   = document.getElementById('YOUR-DOB-FIELD-ID').value;
```

**Apna form field ID kaise dhundho:**

1. Chrome browser mein form page kholo
2. Name input field pe **right-click** karo
3. **"Inspect"** click karo
4. HTML dikhega: `<input id="YAHI_HAI_ID" ...>`
5. `id="..."` ke andar jo likha hai wahi ID hai

---

## 🔵 STEP 6: TESTING

### Test 1: Razorpay Test Mode Se

1. Razorpay dashboard mein **Test Mode** ON karo
2. Test keys use karo (`rzp_test_...`)
3. Form bharo → Pay → Test card use karo:
   ```
   Card Number: 4111 1111 1111 1111
   Expiry: 12/25
   CVV: 123
   ```
4. Payment hona chahiye
5. PDF download hona chahiye
6. Email aana chahiye (3 PDFs attached)

### Test 2: Admin Dashboard Check

1. WordPress admin → **AstroReport Pro → Dashboard**
2. Booking dikhna chahiye table mein
3. Stats update hone chahiye

### Test 3: Live Mode

1. Razorpay keys change karo → Live keys (`rzp_live_...`)
2. Real payment se test karo (₹1 hi hai)
3. Refund kar dena baad mein Razorpay dashboard se

---

## 🔵 STEP 7: PRICE CHANGE KARNA

### Shortcode Form Ka Price Change:

1. File kholo: `templates/booking-form.php`

2. **3 jagah** change karo:

   **Jagah 1** — Button text:
   ```
   PURANA: Get Instant Report & Pay ₹1
   NAYA:   Get Instant Report & Pay ₹99
   ```

   **Jagah 2** — Amount in JavaScript:
   ```javascript
   // PURANA:
   data.append('amount', 1);
   // NAYA:
   data.append('amount', 99);
   ```

   **Jagah 3** — Paise (1 rupee = 100 paise):
   ```javascript
   // PURANA:
   amount: 100, // 1 rupee = 100 paise
   // NAYA:
   amount: 9900, // 99 rupees = 9900 paise
   ```

### Existing Elementor Form Ka Price Change:

1. File kholo: `templates/elementor-block.html`

2. **2 jagah** change karo:

   **Jagah 1** — Button data:
   ```html
   PURANA: data-price="1"
   NAYA:   data-price="99"
   ```

   **Jagah 2** — Display price:
   ```html
   PURANA: <div class="price">₹1</div>
   NAYA:   <div class="price">₹99</div>
   ```

---

## ❌ PROBLEMS & SOLUTIONS

### Problem 1: "Plugin not showing in admin menu"
**Solution:**
1. Plugins page jao
2. AstroReport Pro ko **Deactivate** karo
3. Dobara **Activate** karo
4. Page refresh karo

### Problem 2: "Razorpay popup nahi khul raha"
**Solution:**
1. AstroReport Pro → Settings jao
2. Check karo Key ID sahi hai
3. Key `rzp_test_` ya `rzp_live_` se start hona chahiye
4. Save karo
5. Browser cache clear karo (Ctrl+Shift+R)

### Problem 3: "Payment ho gaya par PDF download nahi hua"
**Solution:**
1. Check karo TCPDF installed hai:
   ```
   wp-content/plugins/gem-astrology-plugin/includes/tcpdf/tcpdf.php
   ```
   Yeh file exist karni chahiye.

2. Agar nahi hai to Step 4 follow karo.

### Problem 4: "Email nahi aa raha"
**Solution:**
1. Spam folder check karo
2. **WP Mail SMTP** plugin install karo:
   - WordPress admin → Plugins → Add New
   - Search: "WP Mail SMTP"
   - Install → Activate
   - Setup karo (Gmail ya SMTP credentials se)

### Problem 5: "Report blank aa raha hai"
**Solution:**
- Check karo yeh files exist karte hain:
  ```
  includes/hindi.php
  includes/english.php
  includes/gujarati.php
  ```

### Problem 6: "Database error / Table not found"
**Solution:**
1. Plugin **Deactivate** karo
2. Plugin **Activate** karo
3. Activation pe table automatically create ho jayega

### Problem 7: "NION_BOOKING is not defined"
**Solution:**
- Step B.4 follow karo (functions.php mein code add karo)

### Problem 8: "Form field IDs match nahi kar rahe"
**Solution:**
- Step B.5 follow karo (Inspect Element se IDs dhundho aur code mein change karo)

---

## 📞 SUPPORT

Koi issue ho to contact karo:

- **Website:** [https://abhimanyu-raj-cse.vercel.app/](https://abhimanyu-raj-cse.vercel.app/)
- **Phone:** +91 9801834437
- **Email:** novanexusltd001@gmail.com
- **Developer:** Trikrypta

---

**Built with ❤️ by Trikrypta**
