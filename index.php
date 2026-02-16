<?php


// --- Helper Functions ---

function calculateMulank($dob)
{
    // Expecting DOB in YYYY-MM-DD or DD/MM/YYYY format
    $timestamp = strtotime($dob);
    if (!$timestamp)
        return null;

    $day = date('d', $timestamp);

    // Reduce to single digit
    while ($day > 9) {
        $sum = 0;
        $digits = str_split((string) $day);
        foreach ($digits as $digit) {
            $sum += (int) $digit;
        }
        $day = $sum;
    }
    return (int) $day;
}

// --- Handle Form Submission ---

$reportData = null;
$error = null;

$reportData = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $language = $_POST['language'] ?? 'hindi'; // Default to full filename style if needed, or map 'hi' -> 'hindi'

    // Map short codes to filenames
    $langMap = [
        'hi' => 'hindi',
        'en' => 'english',
        'gu' => 'gujarati',
        'english' => 'english',
        'gujarati' => 'gujarati'
    ];

    $langFile = $langMap[$language] ?? 'hindi';
    // echo "<!-- DEBUG: Language: $language, File: $langFile -->";

    if (empty($name) || empty($dob)) {
        $error = "Please provide both Name and Date of Birth.";
    } else {
        $mulank = calculateMulank($dob);

        // Dynamically include the correct language file
        require_once 'includes/' . $langFile . '.php';

        // Call the appropriate function based on language
        if ($langFile === 'hindi') {
            $allData = getHindiData();
        } elseif ($langFile === 'english') {
            $allData = getEnglishData();
        } elseif ($langFile === 'gujarati') {
            $allData = getGujaratiData();
        } else {
            $allData = getHindiData(); // Fallback
        }

        if (isset($allData[$mulank])) {
            $reportData = $allData[$mulank];
        } else {
            $reportData = null;
            $error = "Data not found for Mulank $mulank";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nion Gem Astro - Numerology Report</title>
    <!-- Google Fonts for UI and Report -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&family=Playfair+Display:wght@700&family=Noto+Sans+Devanagari:wght@400;700&family=Noto+Sans+Gujarati:wght@400;700&display=swap"
        rel="stylesheet">

    <!-- html2pdf.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        :root {
            --gold: #d4af37;
            --gold-dark: #b88f1c;
            --blue: #0a2a86;
            --cream: #FCEAD1;
            --paper: #ffffff;
            --text-dark: #1a1400;
            --danger: #ef4444;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--blue);
            margin: 0;
            padding: 0;
            color: var(--text-dark);
        }

        /* Font classes for dynamic languages */
        .lang-hi {
            font-family: 'Noto Sans Devanagari', sans-serif;
        }

        .lang-gu {
            font-family: 'Noto Sans Gujarati', sans-serif;
        }

        .lang-en {
            font-family: 'Inter', sans-serif;
        }

        /* --- Input Form Style --- */
        .input-container {
            display:
                <?php echo $reportData ? 'none' : 'flex'; ?>
            ;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: radial-gradient(circle at center, #1C2E40, #050c2d);
            padding: 20px;
        }

        .form-box {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 500px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .form-title {
            color: var(--blue);
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 8px;
            text-align: center;
        }

        .form-subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 13px;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 15px;
            transition: all 0.2s;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: var(--blue);
            outline: none;
            box-shadow: 0 0 0 3px rgba(10, 42, 134, 0.1);
        }

        .btn-submit {
            background: linear-gradient(135deg, #f7e27a, var(--gold), var(--gold-dark));
            border: none;
            width: 100%;
            padding: 14px;
            font-weight: 700;
            font-size: 16px;
            color: #1a1400;
            border-radius: 6px;
            cursor: pointer;
            transition: transform 0.1s;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
        }

        /* --- Report Viewer Style --- */
        .report-viewer {
            display:
                <?php echo $reportData ? 'block' : 'none'; ?>
            ;
            padding: 20px;
            text-align: center;
        }

        .action-bar {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
        }

        .btn-action {
            padding: 10px 20px;
            background: var(--gold);
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            text-decoration: none;
            color: black;
        }

        /* --- PRINT / PDF TEMPLATE STYLE --- */
        /* This is the area that will be converted to PDF */
        #report-content {
            width: 794px;
            /* A4 Width at 96 DPI */
            min-height: 1123px;
            /* A4 Height */
            margin: 0 auto;
            background: white;
            text-align: left;
            box-sizing: border-box;
        }

        .page {
            width: 100%;
            height: 1123px;
            /* Fixed A4 Height for PDF generation */
            position: relative;
            background-color: var(--cream);
            overflow: hidden;
            page-break-after: always;
            padding: 0;
            /* Layouts handle padding */
        }

        /* Cover Page Styling */
        .cover-page {
            display: flex;
            height: 100%;
        }

        .cover-left {
            width: 320px;
            height: 100%;
            position: relative;
            border-right: 3px solid #1C2E40;
        }

        .cover-content {
            flex: 1;
            padding: 80px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .half-circle {
            position: absolute;
            width: 150px;
            height: 300px;
            background: var(--gold);
            border-radius: 0 150px 150px 0;
            top: 100px;
            left: 0;
            opacity: 0.8;
        }

        .logo-placeholder {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 280px;
        }

        .cover-title-small {
            font-family: 'Helvetica', sans-serif;
            font-style: italic;
            font-size: 16px;
            color: #333;
            margin-bottom: 20px;
        }

        .cover-title-main {
            font-family: 'Helvetica', sans-serif;
            font-weight: bold;
            font-size: 38px;
            color: #F25C2A;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .cover-subtitle {
            font-family: 'Helvetica', sans-serif;
            font-size: 32px;
            color: #000;
            line-height: 1.3;
            margin-bottom: 40px;
        }

        .cover-footer {
            margin-top: auto;
            font-size: 14px;
            line-height: 1.6;
            color: #000;
        }

        /* Content Page Styling */
        .content-page-inner {
            padding: 40px;
        }

        .section-box {
            margin-bottom: 25px;
            break-inside: avoid;
            /* Prevent breaking inside box */
        }

        .section-heading {
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

        .section-content {
            background-color: #FFB347;
            padding: 18px 22px;
            border-radius: 14px;
            font-size: 14px;
            line-height: 1.6;
            color: #000;
            white-space: pre-line;
            /* Respect newlines in data */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        /* Footer Note */
        .footer-note {
            position: absolute;
            bottom: 40px;
            left: 40px;
            right: 40px;
            background-color: #F25C2A;
            color: white;
            padding: 10px;
            text-align: center;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <!-- Back to Form Button (Only visible when report is generated) -->
    <?php if ($reportData): ?>
        <div class="action-bar">
            <a href="index.php" class="btn-action" style="background:white;">New Report</a>
            <button onclick="downloadPDF()" class="btn-action">Download PDF</button>
        </div>
    <?php endif; ?>

    <!-- INPUT FORM -->
    <div class="input-container">
        <div class="form-box">
            <div class="form-title">Nion Gem Astro</div>
            <div class="form-subtitle">Numerology Report Generator</div>

            <?php if ($error): ?>
                <div style="color:red; text-align:center; margin-bottom:10px; font-weight:bold;"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" id="gemForm">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Enter Name" required>
                </div>

                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="dob" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Language</label>
                    <select name="language" class="form-control">
                        <option value="english">English</option>
                        <option value="hindi">Hindi</option>
                        <option value="gujarati">Gujarati</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Email (for Report Delivery)</label>
                    <input type="email" name="email" class="form-control" placeholder="Enter Email Address" required>
                </div>

                <button type="submit" class="btn-submit">Get Instant Result & Pay ₹1</button>
            </form>
        </div>
    </div>

    <!-- REPORT TEMPLATE (Hidden from normal view, shown when data exists) -->
    <?php if ($reportData): ?>
        <div class="report-viewer">
            <!-- Status Message Area -->
            <div id="status-area" style="margin-bottom: 20px; font-weight: bold; color: var(--gold-dark);">
                Generating your report... Please wait.
            </div>

            <div id="report-content" class="lang-<?= $language ?>">

                <!-- Page 1: Cover Page -->
                <div class="page cover-page">
                    <div class="cover-left">
                        <div class="half-circle"></div>
                        <!-- Logo Placeholder -->
                        <img src="fonts/logo.jpg" class="logo-placeholder" alt="Logo" onerror="this.style.display='none'">
                    </div>
                    <div class="cover-content">
                        <div class="cover-title-small">Let’s bring you the new life</div>
                        <div class="cover-title-main">Hello <?= htmlspecialchars($name) ?>,</div>
                        <div class="cover-subtitle">Welcome To<br>Your GEM<br>ASTROLOGY<br>Report</div>

                        <div class="cover-footer">
                            <b>Prepared by</b><br>
                            www.niongemastro.com<br>
                            +91 910 430 1456<br>
                            niongemastro@gmail.com
                        </div>
                    </div>
                </div>

                <!-- Content Pages -->
                <!-- We will render content dynamically. Since HTML2PDF renders what it sees, we can just flow the content.
                 However, to force page breaks nicely, we can monitor standard A4 length or just let the tool handle it.
                 For better control, we often structure it as one long scrolling div for the viewer, but split for PDF.
                 Let's stick to a continuous flow logic for simplicity and let html2pdf handle automatic paging, 
                 or manually insert page breaks if we want strict control. 
                 
                 Strategy: Render one continuous container styled like the pages.
            -->

                <div class="page" style="height: auto; min-height: 1123px; padding-top: 40px; padding-bottom: 80px;">
                    <div class="content-page-inner">
                        <?php
                        // Seed the random number generator with Name + DOB hash for consistent but unique results per user
                        $seed = crc32($name . $dob);
                        srand($seed);

                        $counter = 0;
                        foreach ($reportData as $section):
                            $counter++;

                            $heading = $section['heading'];
                            $contentRaw = $section['content'];
                            $content = '';

                            // DYNAMIC CONTENT LOGIC:
                            // If content is an array, pick one variation based on the seeded random.
                            if (is_array($contentRaw)) {
                                // detailed_content key might exist if we structure it complexly, 
                                // but for now assume indexed array of strings or simple assoc array.
                                if (isset($contentRaw['variations'])) {
                                    $variations = $contentRaw['variations'];
                                    $content = $variations[rand(0, count($variations) - 1)];
                                } elseif (isset($contentRaw[0])) {
                                    // Simple indexed array
                                    $content = $contentRaw[rand(0, count($contentRaw) - 1)];
                                } else {
                                    // Fallback just in case
                                    $content = json_encode($contentRaw);
                                }
                            } else {
                                $content = $contentRaw;
                            }

                            // Simple logic to break page every ~4 sections to avoid bad cuts
                            if ($counter > 1 && $counter % 4 == 0) {
                                echo '</div></div><div class="page" style="height: auto; min-height: 1123px; padding-top: 40px; padding-bottom: 80px;"><div class="content-page-inner">';
                            }
                            ?>
                            <div class="section-box">
                                <div class="section-heading">
                                    <?= htmlspecialchars($heading) ?>
                                </div>
                                <div class="section-content">
                                    <?= nl2br(htmlspecialchars(str_replace('\n', "\n", $content))) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Footer Note on the last page -->
                    <div class="footer-note">
                        NOTE: You can call us round the clock for any query regarding your numerology report on +91 910 430
                        1456
                    </div>
                </div>

            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Auto Download PDF
                downloadPDF();

                // Trigger Email Sending via AJAX
                sendEmailReport();
            });

            function downloadPDF() {
                const element = document.getElementById('report-content');
                const statusArea = document.getElementById('status-area');

                statusArea.innerHTML = "Generating PDF for download...";

                const opt = {
                    margin: 0,
                    filename: 'Nion_Astro_Report_<?= htmlspecialchars($name) ?>.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2 },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                };

                // Use html2pdf to save the file
                html2pdf().set(opt).from(element).save().then(() => {
                    statusArea.innerHTML = "PDF Downloaded! Checking email status...";
                });
            }

            function sendEmailReport() {
                const formData = new FormData();
                formData.append('name', '<?= htmlspecialchars($name) ?>');
                formData.append('dob', '<?= htmlspecialchars($dob) ?>');
                formData.append('email', '<?= htmlspecialchars($_POST['email'] ?? '') ?>'); // Get email from POST

                fetch('includes/send-report.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.text())
                    .then(data => {
                        console.log('Email Response:', data);
                        const statusArea = document.getElementById('status-area');
                        statusArea.innerHTML += "<br>Email sent with reports in 3 languages!";
                    })
                    .catch(error => {
                        console.error('Error sending email:', error);
                    });
            }
        </script>
    <?php endif; ?>

</body>

</html>