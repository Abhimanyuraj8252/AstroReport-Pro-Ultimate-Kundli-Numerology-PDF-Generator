/**
 * gem-astro-script.js
 * 
 * Provides functionality to open the Booking process from existing buttons.
 */

const GEM_ASTRO_CONFIG = {
    ajaxUrl: (window.NION_BOOKING && NION_BOOKING.ajax_url) ? NION_BOOKING.ajax_url : '/wp-admin/admin-ajax.php',
    nonce: (window.NION_BOOKING && NION_BOOKING.nonce) ? NION_BOOKING.nonce : '',
    razorpayKey: (window.NION_BOOKING && NION_BOOKING.razorpay_key) ? NION_BOOKING.razorpay_key : '',
    pdfPrice: (window.NION_BOOKING && NION_BOOKING.pdf_price) ? Number(NION_BOOKING.pdf_price) : 0
};

async function ensureGemConfig(forceRefresh = false) {
    if (!forceRefresh && GEM_ASTRO_CONFIG.ajaxUrl && GEM_ASTRO_CONFIG.nonce && GEM_ASTRO_CONFIG.razorpayKey) {
        return true;
    }

    try {
        const fd = new URLSearchParams();
        fd.append('action', 'nion_get_booking_config');
        const res = await fetch(GEM_ASTRO_CONFIG.ajaxUrl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd });
        const raw = await res.text();
        const json = JSON.parse(raw);
        if (json && json.success && json.data) {
            GEM_ASTRO_CONFIG.ajaxUrl = json.data.ajax_url || GEM_ASTRO_CONFIG.ajaxUrl;
            GEM_ASTRO_CONFIG.nonce = json.data.nonce || GEM_ASTRO_CONFIG.nonce;
            GEM_ASTRO_CONFIG.razorpayKey = json.data.razorpay_key || GEM_ASTRO_CONFIG.razorpayKey;
            GEM_ASTRO_CONFIG.pdfPrice = (json.data.pdf_price) ? Number(json.data.pdf_price) : GEM_ASTRO_CONFIG.pdfPrice;
            return !!(GEM_ASTRO_CONFIG.ajaxUrl && GEM_ASTRO_CONFIG.nonce && GEM_ASTRO_CONFIG.razorpayKey);
        }
    } catch (error) {
        console.error('Config refresh failed', error);
    }
    return false;
}

async function postWithNonceRetry(buildPayload) {
    const send = async () => {
        const payload = buildPayload();
        const res = await fetch(GEM_ASTRO_CONFIG.ajaxUrl, { method: 'POST', body: payload });
        return await res.text();
    };

    let raw = await send();
    if (raw.trim() === '-1') {
        const refreshed = await ensureGemConfig(true);
        if (!refreshed) {
            throw new Error('Security token refresh failed. Please reload page.');
        }
        raw = await send();
    }

    if (raw.trim() === '-1') {
        throw new Error('Security nonce invalid/expired. Please refresh and retry.');
    }

    try {
        return JSON.parse(raw);
    } catch (error) {
        throw new Error('Server response invalid. Please try again.');
    }
}

function triggerPdfDownload(pdfUrl, fallbackName) {
    if (!pdfUrl) return;
    const a = document.createElement('a');
    a.href = pdfUrl;
    a.setAttribute('download', `GemAstro-Report-${(fallbackName || 'User').replace(/\s+/g, '_')}.pdf`);
    a.target = '_blank';
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

window.openGemAstroBooking = async function (type, price, title) {
    if (!type) {
        alert("Please specify a service type (e.g., 'pdf').");
        return;
    }


    // Price check moved lower after ensuresGemConfig

    const name = document.getElementById('name')?.value || document.getElementById('form-field-name')?.value || document.getElementById('gemName')?.value;
    const email = document.getElementById('email')?.value || document.getElementById('form-field-email')?.value || document.getElementById('gemEmail')?.value;
    const phone = document.getElementById('phone')?.value || document.getElementById('form-field-phone')?.value || document.getElementById('gemPhone')?.value;
    const dob = document.getElementById('dob')?.value || document.getElementById('form-field-dob')?.value || document.getElementById('gemDob')?.value;
    const language = document.getElementById('language')?.value || document.getElementById('form-field-language')?.value || document.getElementById('gemLanguage')?.value || 'hi';
    const time = document.getElementById('time')?.value || document.getElementById('form-field-time')?.value || document.getElementById('gemTime')?.value || '';
    const place = document.getElementById('place')?.value || document.getElementById('form-field-place')?.value || document.getElementById('gemPlace')?.value || '';

    if (!name || !email || !phone || !dob) {
        alert("Please fill in all required fields (Name, Email, Phone, DOB).");
        return;
    }

    const clickedButton = (typeof event !== 'undefined' && event && event.target) ? event.target : null;
    const btn = clickedButton || { disabled: false, innerText: 'Pay Now' };
    const originalText = btn.innerText || 'Pay Now';
    btn.innerText = "Processing...";
    btn.disabled = true;

    const cfgOk = await ensureGemConfig(true);
    if (!cfgOk || !window.Razorpay || !GEM_ASTRO_CONFIG.razorpayKey) {
        alert('Payment not configured. Please contact admin.');
        btn.disabled = false;
        btn.innerText = originalText;
        return;
    }

    // Use configured price for PDF if available (AFTER refetching config)
    // REMOVED: Forced price override to allow dynamic pricing from shortcode/form
    // if (type === 'pdf' && GEM_ASTRO_CONFIG.pdfPrice > 0) {
    //    price = GEM_ASTRO_CONFIG.pdfPrice;
    // }

    try {
        const orderRes = await postWithNonceRetry(() => {
            const data = new URLSearchParams();
            data.append('action', 'nion_create_rzp_order');
            data.append('nonce', GEM_ASTRO_CONFIG.nonce);
            data.append('amount', String(price));
            data.append('service', title || type);
            data.append('booking_type', type);
            data.append('date', '');
            data.append('time', '');
            return data;
        });

        if (!(orderRes && orderRes.success && orderRes.data && orderRes.data.order_id)) {
            throw new Error(orderRes?.data?.message || 'Order creation failed.');
        }

        const options = {
            key: GEM_ASTRO_CONFIG.razorpayKey,
            amount: Number(price) * 100,
            currency: 'INR',
            name: 'Gem Astrology',
            description: title || 'Astrology Service',
            order_id: orderRes.data.order_id,
            handler: function (response) {
                verifyPayment(response, { name, email, phone, dob, language, type, price, time, place }, btn, originalText);
            },
            prefill: {
                name,
                email,
                contact: phone
            },
            modal: {
                ondismiss: function () {
                    btn.disabled = false;
                    btn.innerText = originalText;
                }
            }
        };

        const rzp = new Razorpay(options);
        rzp.on('payment.failed', function () {
            alert('Payment failed. Please try again.');
            btn.disabled = false;
            btn.innerText = originalText;
        });
        rzp.open();
    } catch (error) {
        console.error(error);
        alert(error.message || 'Connection Error');
        btn.disabled = false;
        btn.innerText = originalText;
    }
};

window.verifyNionGemPayment = function (response, userData) {
    verifyPayment(response, userData, {
        disabled: false,
        innerText: 'Pay Now'
    }, 'Pay Now');
};

async function verifyPayment(response, data, btn, originalText) {
    data = data || {};
    data.language = data.language || 'hi';
    data.type = data.type || 'pdf';
    data.price = Number(data.price || 0);

    try {
        const cfgOk = await ensureGemConfig(true);
        if (!cfgOk) {
            throw new Error('Config load failed. Please refresh page.');
        }

        const verifyRes = await postWithNonceRetry(() => {
            const postData = new URLSearchParams();
            postData.append('action', 'nion_verify_and_save');
            postData.append('nonce', GEM_ASTRO_CONFIG.nonce);
            postData.append('razorpay_order_id', response.razorpay_order_id || '');
            postData.append('razorpay_payment_id', response.razorpay_payment_id || '');
            postData.append('razorpay_signature', response.razorpay_signature || '');
            postData.append('name', data.name || '');
            postData.append('phone', data.phone || '');
            postData.append('email', data.email || '');
            postData.append('dob', data.dob || '');
            postData.append('booking_type', data.type);
            postData.append('price', String(data.price));
            postData.append('language', data.language);
            postData.append('time', data.time || '');
            postData.append('place', data.place || '');
            postData.append('date', '');
            postData.append('notes', '');
            return postData;
        });

        if (verifyRes && verifyRes.success) {
            if (verifyRes.data && verifyRes.data.pdf_url) {
                triggerPdfDownload(verifyRes.data.pdf_url, data.name);
                alert('✅ Success! PDF is downloading and report has been sent to your email.');
            } else {
                console.warn('GemAstro: No pdf_url in response. Full response:', verifyRes);
                alert('✅ Booking confirmed! Report is being generated. Check your email shortly.');
            }
        } else {
            throw new Error(verifyRes?.data?.message || 'Verification failed.');
        }
    } catch (error) {
        console.error(error);
        alert(error.message || 'Verification failed. Please contact support.');
    } finally {
        btn.disabled = false;
        btn.innerText = originalText;
    }
}
