<?php
/**
 * Booking Form + Full Report Display Template
 * Shortcode: [astro_report]
 * 
 * FEATURES:
 * - Language selector (Hindi/English/Gujarati)
 * - User form (Name, Phone, Email, DOB)
 * - Razorpay payment integration
 * - Full kundli report display after payment (like index.php)
 * - Auto PDF download (html2pdf.js)
 * - Auto email with selected language PDF
 * - Dynamic report — different each time based on name + DOB
 * - Works on any page, with any theme
 */
if (!defined('ABSPATH')) {
    exit;
}

// Include language data files (already loaded via plugin main file)
// getHindiData(), getEnglishData(), getGujaratiData() are available globally
?>

// Get configured services
$services_json = get_option('gem_astro_services', '[]');
$services = json_decode($services_json, true);

// Fallback if no services defined: use the price passed from shortcode/admin as a single default service
// Fallback if no services defined: use the price passed from shortcode/admin as a single default service
if (empty($services) || !is_array($services)) {
$services = [
['name' => 'Kundali Report (PDF)', 'price' => $price, 'type' => 'pdf']
];
}

// COMPATIBILITY FIX: If a custom price was passed in the shortcode (e.g. [astro_report price="99"]),
// we must respect it, effectively overriding the Admin-configured service price for the PDF report.
if (isset($custom_price) && $custom_price !== null) {
// Try to find the PDF service and update its price
$found = false;
foreach ($services as &$svc) {
if ($svc['type'] === 'pdf') {
$svc['price'] = $custom_price;
// $svc['name'] .= ' (Special Offer)'; // Optional
$found = true;
break; // Valid assumption: only one PDF service type usually, or we update the first one.
}
}
unset($svc); // Break reference

// If no PDF service found (e.g. only consultations configured), add a temporary one for this shortcode
if (!$found) {
array_unshift($services, [
'name' => 'Kundali Report (Special)',
'price' => $custom_price,
'type' => 'pdf'
]);
}
}

// Let's use the first service's price as the initial display price.
$initial_price = isset($services[0]['price']) ? $services[0]['price'] : $price;
?>

<!-- Google Fonts -->
<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&family=Playfair+Display:wght@700&family=Noto+Sans+Devanagari:wght@400;700&family=Noto+Sans+Gujarati:wght@400;700&display=swap"
    rel="stylesheet">

<!-- html2pdf.js for Client-side PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div id="gemAstroApp">
    <style>
        /* ======= GEM ASTRO PLUGIN STYLES ======= */
        #gemAstroApp {
            --gold: #d4af37;
            --gold-dark: #b88f1c;
            --blue: #0a2a86;
            --cream: #FCEAD1;
            --paper: #ffffff;
            --primary: #7c1e22;
            --primary2: #5a1417;
            --soft: #fff3f2;
            --ink: #141414;
            --muted: #6b6b6b;
            --border: #ead0ce;
            --font: 'Inter', sans-serif;
            font-family: var(--font);
            color: var(--ink);
            max-width: 100%;
        }

        #gemAstroApp * {
            box-sizing: border-box;
        }

        /* Language font classes */
        #gemAstroApp .lang-hi,
        #gemAstroApp .lang-hindi {
            font-family: 'Noto Sans Devanagari', sans-serif;
        }

        #gemAstroApp .lang-gu,
        #gemAstroApp .lang-gujarati {
            font-family: 'Noto Sans Gujarati', sans-serif;
        }

        #gemAstroApp .lang-en,
        #gemAstroApp .lang-english {
            font-family: 'Inter', sans-serif;
        }

        /* === FORM SECTION === */
        .gem-form-section {
            max-width: 560px;
            margin: 0 auto;
            padding: 20px;
        }

        .gem-form-card {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
            border: 1px solid var(--border);
        }

        .gem-form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .gem-form-header h2 {
            color: var(--primary);
            font-size: 26px;
            margin: 0 0 8px;
        }

        .gem-form-header p {
            color: var(--muted);
            font-size: 14px;
            margin: 0;
        }

        .gem-field {
            margin-bottom: 18px;
        }

        .gem-field label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 13px;
            color: #333;
        }

        .gem-field input,
        .gem-field select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s;
            font-family: var(--font);
        }

        .gem-field input:focus,
        .gem-field select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(124, 30, 34, 0.1);
        }

        .gem-pay-btn {
            background: linear-gradient(135deg, #f7e27a, var(--gold), var(--gold-dark));
            border: none;
            width: 100%;
            padding: 14px;
            font-weight: 700;
            font-size: 16px;
            color: #1a1400;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            margin-top: 10px;
        }

        .gem-pay-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
        }

        .gem-pay-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .gem-error-msg {
            color: #ef4444;
            text-align: center;
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 14px;
            display: none;
        }

        /* === REPORT SECTION === */
        .gem-report-section {
            display: none;
            padding: 20px;
            text-align: center;
        }

        .gem-report-section.active {
            display: block;
        }

        .gem-report-actions {
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            justify-content: center;
            gap: 12px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        }

        .gem-report-btn {
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.15s;
        }

        .gem-report-btn-gold {
            background: var(--gold);
            color: #1a1400;
        }

        .gem-report-btn-gold:hover {
            background: var(--gold-dark);
        }

        .gem-report-btn-white {
            background: white;
            color: var(--primary);
            border: 2px solid var(--border);
        }

        .gem-report-btn-white:hover {
            background: var(--soft);
        }

        .gem-status-msg {
            margin-bottom: 20px;
            font-weight: 700;
            color: var(--gold-dark);
            font-size: 16px;
        }

        /* === REPORT TEMPLATE (A4 styled) === */
        #gem-report-content {
            width: 794px;
            margin: 0 auto;
            background: white;
            text-align: left;
        }

        .gem-page {
            width: 100%;
            position: relative;
            background-color: var(--cream);
            overflow: hidden;
            page-break-after: always;
            padding: 0;
        }

        /* Cover Page */
        .gem-cover-page {
            display: flex;
            height: 1123px;
        }

        .gem-cover-left {
            width: 320px;
            height: 100%;
            position: relative;
            border-right: 3px solid #1C2E40;
        }

        .gem-cover-content {
            flex: 1;
            padding: 80px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .gem-half-circle {
            position: absolute;
            width: 150px;
            height: 300px;
            background: var(--gold);
            border-radius: 0 150px 150px 0;
            top: 100px;
            left: 0;
            opacity: 0.8;
        }

        .gem-logo-img {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 280px;
        }

        .gem-cover-small {
            font-style: italic;
            font-size: 16px;
            color: #333;
            margin-bottom: 20px;
        }

        .gem-cover-main {
            font-weight: bold;
            font-size: 38px;
            color: #F25C2A;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .gem-cover-sub {
            font-size: 32px;
            color: #000;
            line-height: 1.3;
            margin-bottom: 40px;
        }

        .gem-cover-footer {
            margin-top: auto;
            font-size: 14px;
            line-height: 1.6;
            color: #000;
        }

        /* Content Pages */
        .gem-content-inner {
            padding: 40px;
        }

        .gem-section-box {
            margin-bottom: 25px;
            break-inside: avoid;
        }

        .gem-section-heading {
            background-color: #F25C2A;
            color: white;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 12px;
            display: inline-block;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .gem-section-content {
            background-color: #FFB347;
            padding: 18px 22px;
            border-radius: 14px;
            font-size: 14px;
            line-height: 1.6;
            color: #000;
            white-space: pre-line;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .gem-footer-note {
            background-color: #F25C2A;
            color: white;
            padding: 10px;
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            margin-top: 30px;
        }

        @media (max-width: 820px) {
            #gem-report-content {
                width: 100%;
            }

            .gem-cover-page {
                flex-direction: column;
                height: auto;
            }

            .gem-cover-left {
                width: 100%;
                height: 200px;
                border-right: none;
                border-bottom: 3px solid #1C2E40;
            }

            .gem-cover-content {
                padding: 30px 20px;
            }

            .gem-cover-main {
                font-size: 28px;
            }

            .gem-cover-sub {
                font-size: 24px;
            }
        }
    </style>

    <!-- ========== FORM SECTION ========== -->
    <div class="gem-form-section" id="gemFormSection">
        <div class="gem-form-card">
            <div class="gem-form-header">
                <h2>🌟 AstroReport Pro</h2>
                <p>Professional Digital Numerology & Kundli Reports</p>
            </div>

            <div class="gem-error-msg" id="gemErrorMsg"></div>

            <form id="gemBookingForm" onsubmit="return false;">
                <div class="gem-field">
                    <label>🌐 Language / भाषा</label>
                    <select id="gemLanguage" name="language">
                        <option value="hi" selected>हिंदी (Hindi)</option>
                        <option value="en">English</option>
                        <option value="gu">ગુજરાતી (Gujarati)</option>
                    </select>
                </div>

                <div class="gem-field">
                    <label>📜 Select Service / सेवा चुनें</label>
                    <select id="gemService" name="service" onchange="gemUpdatePrice()">
                        <?php foreach ($services as $idx => $svc): ?>
                            <option value="<?php echo esc_attr($svc['type']); ?>"
                                data-price="<?php echo esc_attr($svc['price']); ?>"
                                data-name="<?php echo esc_attr($svc['name']); ?>" <?php echo $idx === 0 ? 'selected' : ''; ?>>
                                <?php echo esc_html($svc['name']); ?> — ₹<?php echo esc_html($svc['price']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="gem-field">
                    <label>👤 Full Name</label>
                    <input type="text" id="gemName" name="name" placeholder="Enter your full name" required>
                </div>

                <div class="gem-field">
                    <label>📱 Phone</label>
                    <input type="tel" id="gemPhone" name="phone" placeholder="+91 9XXXXXXXXX" required>
                </div>

                <div class="gem-field">
                    <label>📧 Email</label>
                    <input type="email" id="gemEmail" name="email" placeholder="your@email.com" required>
                </div>

                <div class="gem-field">
                    <label>🎂 Date of Birth</label>
                    <input type="date" id="gemDob" name="dob" required>
                </div>

                <button type="button" class="gem-pay-btn" id="gemPayBtn" onclick="gemStartPayment()">
                    Get Instant Report & Pay ₹<?php echo esc_html($initial_price); ?>
                </button>
            </form>
        </div>
    </div>

    <!-- ========== REPORT SECTION (Hidden until payment) ========== -->
    <div class="gem-report-section" id="gemReportSection">
        <!-- Action Bar -->
        <div class="gem-report-actions">
            <button class="gem-report-btn gem-report-btn-gold" onclick="gemDownloadPDF()">📥 Download PDF</button>
            <button class="gem-report-btn gem-report-btn-white" onclick="gemNewReport()">🔄 New Report</button>
        </div>

        <div class="gem-status-msg" id="gemStatusMsg">Generating your report...</div>

        <!-- Report Content (will be built by JS after payment) -->
        <div id="gem-report-content"></div>
    </div>

</div><!-- #gemAstroApp -->

<script>
    (function () {
        // ============================================================
        // GEM ASTRO PLUGIN — JAVASCRIPT
        // Handles: Payment → AJAX report generation → Display → PDF
        // ============================================================

        let GEM_AJAX_URL = typeof NION_BOOKING !== 'undefined' ? NION_BOOKING.ajax_url : '<?php echo admin_url("admin-ajax.php"); ?>';
        let GEM_NONCE = typeof NION_BOOKING !== 'undefined' ? NION_BOOKING.nonce : '<?php echo wp_create_nonce("gem_astro_nonce"); ?>';
        let GEM_RZP_KEY = typeof NION_BOOKING !== 'undefined' ? NION_BOOKING.razorpay_key : '<?php echo esc_attr(get_option("gem_astro_razorpay_key", "")); ?>';
        let GEM_PRICE = <?php echo floatval($initial_price); ?>;

        async function gemEnsureConfig(forceRefresh = false) {
            if (!forceRefresh && GEM_AJAX_URL && GEM_NONCE && GEM_RZP_KEY) return true;
            try {
                const fd = new URLSearchParams();
                fd.append('action', 'nion_get_booking_config');
                const res = await fetch(GEM_AJAX_URL || '<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: fd });
                const raw = await res.text();
                const json = JSON.parse(raw);
                if (json && json.success && json.data) {
                    GEM_AJAX_URL = json.data.ajax_url || GEM_AJAX_URL;
                    GEM_NONCE = json.data.nonce || GEM_NONCE;
                    GEM_RZP_KEY = json.data.razorpay_key || GEM_RZP_KEY;
                    return !!(GEM_AJAX_URL && GEM_NONCE && GEM_RZP_KEY);
                }
            } catch (e) { }
            return false;
        }

        async function gemPostWithNonceRetry(buildPayload) {
            const run = async () => {
                const payload = buildPayload();
                const res = await fetch(GEM_AJAX_URL, { method: 'POST', body: payload });
                return await res.text();
            };

            let raw = await run();
            if (raw.trim() === '-1') {
                const refreshed = await gemEnsureConfig(true);
                if (!refreshed) throw new Error('Security token refresh failed. Please reload page.');
                raw = await run();
            }
            if (raw.trim() === '-1') {
                throw new Error('Security nonce invalid/expired. Please refresh and retry.');
            }
            return JSON.parse(raw);
        }

        function gemAutoDownloadPdf(pdfUrl, userName) {
            if (!pdfUrl) return;
            const link = document.createElement('a');
            link.href = pdfUrl;
            link.download = 'Kundli_Report_' + String(userName || 'User').replace(/\s+/g, '_') + '.pdf';
            link.target = '_blank';
            link.rel = 'noopener';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Expose to global scope
        window.gemStartPayment = async function () {
            const name = document.getElementById('gemName').value.trim();
            const phone = document.getElementById('gemPhone').value.trim();
            const email = document.getElementById('gemEmail').value.trim();
            const dob = document.getElementById('gemDob').value;
            const language = document.getElementById('gemLanguage').value;

            const errorDiv = document.getElementById('gemErrorMsg');
            errorDiv.style.display = 'none';

            if (!name || !phone || !email || !dob) {
                errorDiv.textContent = 'Please fill all fields!';
                errorDiv.style.display = 'block';
                return;
            }

            const configOk = await gemEnsureConfig(true);
            if (!configOk || !GEM_RZP_KEY) {
                errorDiv.textContent = 'Payment not configured. Contact admin.';
                errorDiv.style.display = 'block';
                return;
            }

            const btn = document.getElementById('gemPayBtn');
            btn.disabled = true;
            btn.textContent = 'Processing...';

            // Step 1: Create Razorpay Order
            try {
                const res = await gemPostWithNonceRetry(() => {
                    const orderData = new URLSearchParams();
                    orderData.append('action', 'nion_create_rzp_order');
                    orderData.append('nonce', GEM_NONCE);
                    orderData.append('amount', GEM_PRICE);

                    // Get selected service details
                    const serviceSelect = document.getElementById('gemService');
                    const selectedOption = serviceSelect ? serviceSelect.options[serviceSelect.selectedIndex] : null;
                    const serviceName = selectedOption ? selectedOption.getAttribute('data-name') : 'Kundali report (PDF)';
                    const serviceType = selectedOption ? selectedOption.value : 'pdf';

                    orderData.append('service', serviceName);
                    orderData.append('booking_type', serviceType);
                    return orderData;
                });

                if (res && res.success && res.data && res.data.order_id) {
                    // Update openRazorpay to pass the correct service type/name if needed for verification
                    // openRazorpay stores it in global or passes it down
                    // We need to ensure verifyPayment also has access to this data.
                    // But verifyAndShowReport takes 'userData'. Let's add service details to userData.
                    openRazorpay(res.data.order_id, { name, phone, email, dob, language, service_type: serviceType ?? 'pdf', service_name: serviceName });
                } else {
                    showError('Order creation failed: ' + (res?.data?.message || 'Unknown error'));
                    resetBtn();
                }
            } catch (err) {
                showError(err && err.message ? err.message : 'Connection error. Try again.');
                resetBtn();
            }
        };

        function openRazorpay(orderId, userData) {
            const options = {
                key: GEM_RZP_KEY,
                amount: GEM_PRICE * 100, // Amount in paise
                currency: "INR",
                name: "AstroReport Pro",
                description: "Personalized Digital Kundli Report",
                order_id: orderId,
                handler: function (response) {
                    verifyAndShowReport(response, userData);
                },
                prefill: {
                    name: userData.name,
                    email: userData.email,
                    contact: userData.phone
                },
                modal: {
                    ondismiss: function () { resetBtn(); }
                }
            };
            const rzp = new Razorpay(options);
            rzp.open();
        }

        function verifyAndShowReport(rzpResponse, userData) {
            const btn = document.getElementById('gemPayBtn');
            btn.textContent = 'Verifying Payment...';

            gemPostWithNonceRetry(() => {
                const data = new URLSearchParams();
                data.append('action', 'nion_verify_and_save');
                data.append('nonce', GEM_NONCE);
                data.append('razorpay_order_id', rzpResponse.razorpay_order_id || '');
                data.append('razorpay_payment_id', rzpResponse.razorpay_payment_id || '');
                data.append('razorpay_signature', rzpResponse.razorpay_signature || '');
                data.append('name', userData.name);
                data.append('phone', userData.phone);
                data.append('email', userData.email);
                data.append('dob', userData.dob);
                data.append('booking_type', userData.service_type || 'pdf');
                data.append('price', GEM_PRICE);
                data.append('language', userData.language);
                data.append('notes', '');
                data.append('date', '');
                data.append('time', '');
                return data;
            })
                .then(res => {
                    if (res && res.success) {
                        if (res.data && res.data.pdf_url) {
                            gemAutoDownloadPdf(res.data.pdf_url, userData.name);
                        }
                        fetchAndDisplayReport(userData);
                    } else {
                        showError('Payment verification failed: ' + (res?.data?.message || ''));
                        resetBtn();
                    }
                })
                .catch(err => {
                    showError(err && err.message ? err.message : 'Verification error. Contact support.');
                    resetBtn();
                });
        }

        function fetchAndDisplayReport(userData) {
            gemPostWithNonceRetry(() => {
                const data = new URLSearchParams();
                data.append('action', 'gem_astro_get_report');
                data.append('nonce', GEM_NONCE);
                data.append('name', userData.name);
                data.append('dob', userData.dob);
                data.append('email', userData.email);
                data.append('language', userData.language);
                return data;
            })
                .then(res => {
                    if (res.success && res.data.html) {
                        showReportUI(res.data.html, userData);
                    } else {
                        showError('Report generation failed.');
                        resetBtn();
                    }
                })
                .catch(err => {
                    showError('Could not load report.');
                    resetBtn();
                });
        }

        function showReportUI(html, userData) {
            document.getElementById('gemFormSection').style.display = 'none';
            document.getElementById('gemReportSection').classList.add('active');
            document.getElementById('gem-report-content').innerHTML = html;

            const langClass = 'lang-' + userData.language;
            document.getElementById('gem-report-content').className = langClass;

            document.getElementById('gemStatusMsg').innerHTML = '✅ Report generated! Your PDF is downloading...';

            // Do NOT double download. The PDF is already downloaded via gemAutoDownloadPdf
            // setTimeout(() => gemDownloadPDF(userData.name), 500);
        }

        window.gemDownloadPDF = function (name) {
            const element = document.getElementById('gem-report-content');
            if (!element || !element.innerHTML.trim()) return;

            const userName = name || document.getElementById('gemName')?.value || 'Report';
            const statusMsg = document.getElementById('gemStatusMsg');
            if (statusMsg) statusMsg.innerHTML = '📥 Generating PDF for download...';

            const opt = {
                margin: 0,
                filename: 'Nion_Astro_Report_' + userName.replace(/\s+/g, '_') + '.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save().then(() => {
                if (statusMsg) statusMsg.innerHTML = '✅ PDF Downloaded! Report also sent to your email in selected language.';
            });
        };

        window.gemNewReport = function () {
            document.getElementById('gemFormSection').style.display = 'block';
            document.getElementById('gemReportSection').classList.remove('active');
            document.getElementById('gem-report-content').innerHTML = '';
            document.getElementById('gemBookingForm').reset();
            resetBtn();
        };

        function showError(msg) {
            const errorDiv = document.getElementById('gemErrorMsg');
            errorDiv.textContent = msg;
            errorDiv.style.display = 'block';
        }

        window.gemUpdatePrice = function () {
            const serviceSelect = document.getElementById('gemService');
            if (!serviceSelect) return;

            const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            GEM_PRICE = parseFloat(price) || 0;

            const btn = document.getElementById('gemPayBtn');
            if (btn) {
                btn.textContent = 'Get Instant Report & Pay ₹' + GEM_PRICE;
            }
        };

        function resetBtn() {
            const btn = document.getElementById('gemPayBtn');
            if (btn) {
                btn.disabled = false;
                // Update based on currently selected service
                const serviceSelect = document.getElementById('gemService');
                const price = serviceSelect ? parseFloat(serviceSelect.options[serviceSelect.selectedIndex].getAttribute('data-price')) : (GEM_PRICE || '1');
                btn.textContent = 'Get Instant Report & Pay ₹' + price;
            }
        }
    })();
</script>