/**
 * gem-astro-script.js
 * 
 * Provides functionality to open the Booking process from existing buttons.
 */

window.openGemAstroBooking = function (type, price, title) {
    if (!type) {
        alert("Please specify a service type (e.g., 'pdf').");
        return;
    }

    // We assume there's a modal or form somewhere. 
    // If the user has their own form, they need to hook this function to their Pay button.
    // OR create a modal dynamically if one doesn't exist.

    // Since the user said "form nahi bnana" (don't make a form), it implies they HAVE a form.
    // They just need the PAYMENT processing logic.

    // Let's create a minimal hidden form or use what's available?
    // Actually, Razorpay needs Order ID first.

    // We need Name, Email, Phone, DOB to be collected.
    // If the user's form has these fields, we need to grab them.
    // Let's ask the user to provide the IDs of their input fields.
    // But for now, we'll try to guess standard IDs or prompt.

    const name = document.getElementById('name')?.value || document.getElementById('form-field-name')?.value;
    const email = document.getElementById('email')?.value || document.getElementById('form-field-email')?.value;
    const phone = document.getElementById('phone')?.value || document.getElementById('form-field-phone')?.value; // Elementor often uses form-field-ID
    const dob = document.getElementById('dob')?.value || document.getElementById('form-field-dob')?.value;
    const language = document.getElementById('language')?.value || document.getElementById('form-field-language')?.value || 'hi';

    if (!name || !email || !phone || !dob) {
        alert("Please fill in all required fields (Name, Email, Phone, DOB).");
        return;
    }

    // Call Backend to Create Order
    const data = new URLSearchParams();
    data.append('action', 'nion_create_rzp_order');
    data.append('nonce', NION_BOOKING.nonce);
    data.append('amount', price);
    data.append('service', type);

    const btn = event.target;
    const originalText = btn.innerText;
    btn.innerText = "Processing...";
    btn.disabled = true;

    fetch(NION_BOOKING.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data
    })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const options = {
                    key: NION_BOOKING.razorpay_key,
                    amount: res.data.order_id ? price * 100 : 0,
                    currency: "INR",
                    name: "Gem Astrology",
                    description: title || "Astrology Service",
                    order_id: res.data.order_id,
                    handler: function (response) {
                        verifyPayment(response, { name, email, phone, dob, language, type, price }, btn, originalText);
                    },
                    prefill: {
                        name: name,
                        email: email,
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
                rzp.open();
            } else {
                alert('Error: ' + (res.data ? res.data.message : 'Unknown error'));
                btn.disabled = false;
                btn.innerText = originalText;
            }
        })
        .catch(err => {
            console.error(err);
            alert('Connection Error');
            btn.disabled = false;
            btn.innerText = originalText;
        });
};

window.verifyNionGemPayment = function (response, userData) {
    verifyPayment(response, userData, {
        disabled: false,
        innerText: 'Pay Now' // Dummy button state object
    }, 'Pay Now');
};

function verifyPayment(response, data, btn, originalText) {
    // Ensure data has defaults if missing
    data = data || {};
    data.language = data.language || 'hi';
    data.type = data.type || 'pdf';
    data.price = data.price || 0;

    const postData = new URLSearchParams();
    postData.append('action', 'nion_verify_and_save');
    postData.append('nonce', NION_BOOKING.nonce);
    postData.append('razorpay_order_id', response.razorpay_order_id);
    postData.append('razorpay_payment_id', response.razorpay_payment_id);
    postData.append('razorpay_signature', response.razorpay_signature);

    // If userData is passed from existing form, use it
    if (data.name) postData.append('name', data.name);
    if (data.phone) postData.append('phone', data.phone);
    if (data.email) postData.append('email', data.email);
    if (data.dob) postData.append('dob', data.dob);

    postData.append('booking_type', data.type);
    postData.append('price', data.price);
    postData.append('language', data.language);

    fetch(NION_BOOKING.ajax_url, {
        method: 'POST',
        body: postData
    })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (res.data.pdf_url) {
                    const link = document.createElement('a');
                    link.href = res.data.pdf_url;
                    link.setAttribute('download', 'GemAstro-Report.pdf');
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    alert('Success! PDF is downloading.');
                } else {
                    alert('Success! Check email.');
                }
            } else {
                console.error('Verification Failed: ' + res.data.message);
                alert('Verification Failed. Please contact support.');
            }
        })
        .catch(err => {
            console.error(err);
        });
}
