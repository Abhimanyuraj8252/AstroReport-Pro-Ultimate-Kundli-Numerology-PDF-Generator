<?php

if (!defined('ABSPATH') && !defined('GEM_ASTRO_PATH')) {
    // Allow standalone if we defined GEM_ASTRO_PATH manually
    if (!defined('GEM_ASTRO_PATH'))
        exit;
}

// Try to load TCPDF
// Logic to find TCPDF. If not in includes/tcpdf, we might need to look elsewhere or use a different library.
// For now, assuming it is there as per file listing.
$tcpdf_path = GEM_ASTRO_PATH . 'includes/tcpdf/tcpdf.php';
if (file_exists($tcpdf_path)) {
    require_once $tcpdf_path;
} else {
    // If standard TCPDF file isn't found, check if it's inside another folder or check system
    // For this environment, we might need to mock or ensure it exists.
    // If we can't load TCPDF, we can't generate PDF.
    // We will assume it exists for now based on 'ls' output showing the dir.
}

class GemAstroPDF
{
    private static $fontCache = [];

    // Helper to calculate Mulank uniformly
    public static function calculateMulank($dob)
    {
        $timestamp = strtotime($dob);
        if (!$timestamp)
            return 0;
        $day = date('d', $timestamp);
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

    public static function generate_report($booking_data)
    {
        if (!class_exists('TCPDF')) {
            error_log('TCPDF class not found.');
            return false;
        }

        $language = isset($booking_data['language']) ? $booking_data['language'] : 'en';
        $langCode = self::normalizeLanguageCode($language);
        $name = isset($booking_data['name']) ? $booking_data['name'] : 'Guest';
        $dob = isset($booking_data['dob']) ? $booking_data['dob'] : '';

        $mulank = self::calculateMulank($dob);
        $allData = self::getLanguageData($langCode);
        $sections = isset($allData[$mulank]) && is_array($allData[$mulank]) ? $allData[$mulank] : [];
        $brand = self::getBrandConfig();

        if (empty($sections)) {
            error_log('No data available for Mulank ' . $mulank . ' in language: ' . $langCode);
            return false;
        }

        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('Gem Astrology');
        $pdf->SetAuthor('Gem Astrology');
        $pdf->SetTitle('Kundli Report - ' . $name);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(FALSE, 0);
        $pdf->SetFont('freesans', '', 12);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        $seed = crc32($name . $dob);
        srand($seed);

        self::renderCoverPage($pdf, $name, $brand);

        $total = count($sections);
        $chunks = array_chunk($sections, 4);
        $processed = 0;

        foreach ($chunks as $chunk) {
            $processed += count($chunk);
            $isLast = ($processed >= $total);
            self::renderContentPage($pdf, $mulank, $chunk, $isLast, $brand, $langCode);
        }

        self::renderFinalNotePage($pdf, $brand);

        $upload_dir = wp_upload_dir();
        $gem_astro_dir = $upload_dir['basedir'] . '/gem-astrology-reports/';
        if (!file_exists($gem_astro_dir)) {
            wp_mkdir_p($gem_astro_dir);
        }

        $filename = 'GemAstro-Report-' . sanitize_file_name($name) . '-' . strtoupper($langCode) . '-' . time() . '.pdf';
        $file_path = $gem_astro_dir . $filename;

        $pdf->Output($file_path, 'F');

        $file_url = str_replace(
            [$upload_dir['basedir'], '\\'],
            [$upload_dir['baseurl'], '/'],
            $file_path
        );

        return ['path' => $file_path, 'url' => $file_url];
    }

    private static function normalizeLanguageCode($lang)
    {
        $lang = strtolower(trim((string) $lang));
        if ($lang === 'hindi' || $lang === 'hi') {
            return 'hi';
        }
        if ($lang === 'gujarati' || $lang === 'gu') {
            return 'gu';
        }
        return 'en';
    }

    private static function getLanguageData($langCode)
    {
        if ($langCode === 'hi') {
            return function_exists('getHindiData') ? getHindiData() : [];
        }
        if ($langCode === 'gu') {
            return function_exists('getGujaratiData') ? getGujaratiData() : [];
        }
        return function_exists('getEnglishData') ? getEnglishData() : [];
    }

    private static function pickSectionContent($contentRaw)
    {
        if (is_array($contentRaw)) {
            if (isset($contentRaw['variations']) && is_array($contentRaw['variations']) && !empty($contentRaw['variations'])) {
                $v = $contentRaw['variations'];
                return (string) $v[rand(0, count($v) - 1)];
            }
            if (isset($contentRaw[0])) {
                return (string) $contentRaw[rand(0, count($contentRaw) - 1)];
            }
            return (string) json_encode($contentRaw, JSON_UNESCAPED_UNICODE);
        }
        return (string) $contentRaw;
    }

    private static function getBrandConfig()
    {
        $getOption = function ($key, $default) {
            if (is_callable('get_option')) {
                return call_user_func('get_option', $key, $default);
            }
            return $default;
        };

        return [
            'title' => $getOption('gem_astro_brand_title', 'Trikrypta'),
            'tagline' => $getOption('gem_astro_brand_tagline', 'Let\'s bring you the new life'),
            'website_name' => $getOption('gem_astro_website_name', 'Trikrypta'),
            'website_url' => $getOption('gem_astro_website_url', 'https://abhimanyu-raj-cse.vercel.app/'),
            'phone' => $getOption('gem_astro_contact_phone', '+91 9801834437'),
            'email' => $getOption('gem_astro_contact_email', 'novanexusltd001@gmail.com'),
        ];
    }

    private static function getLanguageFontFamily($langCode, $bold = false)
    {
        if ($langCode !== 'gu') {
            return 'freesans';
        }

        $cacheKey = $bold ? 'gu_bold' : 'gu_regular';
        if (isset(self::$fontCache[$cacheKey])) {
            return self::$fontCache[$cacheKey];
        }

        $localFile = GEM_ASTRO_PATH . 'fonts/' . ($bold ? 'NotoSansGujarati-Bold.ttf' : 'NotoSansGujarati-Regular.ttf');
        $systemFile = '/usr/share/fonts/truetype/noto/' . ($bold ? 'NotoSansGujarati-Bold.ttf' : 'NotoSansGujarati-Regular.ttf');
        $fontFile = file_exists($localFile) ? $localFile : (file_exists($systemFile) ? $systemFile : '');

        if (empty($fontFile) || !class_exists('TCPDF_FONTS')) {
            self::$fontCache[$cacheKey] = 'freesans';
            return self::$fontCache[$cacheKey];
        }

        $style = $bold ? 'B' : '';
        $fontName = TCPDF_FONTS::addTTFfont($fontFile, 'TrueTypeUnicode', $style, 32);

        self::$fontCache[$cacheKey] = $fontName ? $fontName : 'freesans';
        return self::$fontCache[$cacheKey];
    }

    private static function setLangFont($pdf, $langCode, $size, $bold = false, $italic = false)
    {
        if ($langCode === 'gu') {
            $pdf->SetFont(self::getLanguageFontFamily($langCode, $bold), '', $size);
            return;
        }

        $style = '';
        if ($bold) {
            $style .= 'B';
        }
        if ($italic) {
            $style .= 'I';
        }
        $pdf->SetFont('freesans', $style, $size);
    }

    private static function getMulankLabel($langCode)
    {
        if ($langCode === 'gu') {
            return 'મૂલાંક';
        }
        if ($langCode === 'hi') {
            return 'मूलांक';
        }
        return 'Mulank';
    }

    private static function normalizeContentForLanguage($content, $langCode)
    {
        if ($langCode === 'gu') {
            $content = str_replace(['•', '●', '▪', '◦'], '-', $content);
        }
        return $content;
    }

    private static function getWebsiteDisplay($brand)
    {
        $websiteName = (string) ($brand['website_name'] ?? 'Trikrypta');
        $websiteUrl = (string) ($brand['website_url'] ?? '');

        if (!empty($websiteUrl)) {
            $host = parse_url($websiteUrl, PHP_URL_HOST);
            if (!empty($host)) {
                return $host;
            }
            return $websiteUrl;
        }

        return $websiteName;
    }

    private static function renderCoverPage($pdf, $name, $brand)
    {
        $pdf->AddPage();

        // Base page background (premium cream)
        $pdf->SetFillColor(252, 234, 209);
        $pdf->Rect(0, 0, 210, 297, 'F');

        self::renderCoverSideDesign($pdf);

        $pdf->SetDrawColor(28, 46, 64);
        $pdf->SetLineWidth(1.1);
        $pdf->Line(106, 18, 106, 204);

        $logoPath = GEM_ASTRO_PATH . 'fonts/logo.jpg';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 112, 10, 34, 0, '', '', '', false, 300);
        }

        $pdf->SetTextColor(10, 42, 134);
        $pdf->SetFont('freesans', 'B', 13.5);
        $pdf->SetXY(112, 28);
        $pdf->Cell(82, 7, (string) ($brand['title'] ?? 'Trikrypta'), 0, 1, 'L');

        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetFont('freesans', 'I', 9.8);
        $pdf->SetXY(112, 35.5);
        $pdf->Cell(82, 6, (string) ($brand['tagline'] ?? ''), 0, 1, 'L');

        $pdf->SetTextColor(242, 92, 42);
        $pdf->SetFont('freesans', 'B', 24);
        $pdf->SetXY(110, 52);
        $pdf->MultiCell(90, 12, 'Hello ' . $name . ',', 0, 'L', false, 1);

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('freesans', '', 18.5);
        $pdf->SetXY(110, 84);
        $pdf->MultiCell(90, 10, "Welcome To\nYour GEM\nASTROLOGY\nReport", 0, 'L', false, 1);

        $websiteDisplay = self::getWebsiteDisplay($brand);

        $pdf->SetTextColor(34, 40, 55);
        $pdf->SetFont('freesans', 'B', 14);
        $pdf->SetXY(110, 162);
        $pdf->Cell(88, 8, 'Prepared by', 0, 1, 'L');

        $pdf->SetFont('freesans', '', 11.2);
        $pdf->SetXY(110, 171);
        $pdf->Cell(88, 6, 'Web: ' . $websiteDisplay, 0, 1, 'L');

        $pdf->SetXY(110, 179);
        $pdf->Cell(88, 6, 'Phone: ' . (string) ($brand['phone'] ?? ''), 0, 1, 'L');

        $pdf->SetXY(110, 187);
        $pdf->MultiCell(88, 6, 'Email: ' . (string) ($brand['email'] ?? ''), 0, 'L', false, 1);
    }

    private static function renderCoverSideDesign($pdf)
    {
        // palette
        $orange = [242, 92, 42];
        $blue = [35, 101, 165];
        $navy = [29, 53, 84];
        $gold = [245, 175, 65];

        // middle motif stack
        $pdf->SetFillColor($orange[0], $orange[1], $orange[2]);
        $pdf->Rect(5, 108, 28, 30, 'F');
        $pdf->SetFillColor($blue[0], $blue[1], $blue[2]);
        $pdf->Rect(33, 108, 28, 30, 'F');

        $pdf->SetFillColor($orange[0], $orange[1], $orange[2]);
        if (method_exists($pdf, 'Circle')) {
            $pdf->Circle(33, 138, 28, 0, 360, 'F');
        }
        $pdf->SetFillColor($navy[0], $navy[1], $navy[2]);
        if (method_exists($pdf, 'Circle')) {
            $pdf->Circle(61, 138, 28, 0, 360, 'F');
        }

        $pdf->SetFillColor($gold[0], $gold[1], $gold[2]);
        if (method_exists($pdf, 'Circle')) {
            $pdf->Circle(18, 170, 24, 0, 360, 'F');
        }

        // bottom motif cluster
        $pdf->SetFillColor($orange[0], $orange[1], $orange[2]);
        $pdf->Rect(5, 200, 28, 30, 'F');
        $pdf->SetFillColor($blue[0], $blue[1], $blue[2]);
        $pdf->Rect(33, 200, 28, 30, 'F');

        $pdf->SetFillColor($navy[0], $navy[1], $navy[2]);
        $pdf->Rect(5, 230, 28, 30, 'F');
        $pdf->SetFillColor($orange[0], $orange[1], $orange[2]);
        $pdf->Rect(33, 230, 28, 30, 'F');

        $pdf->SetFillColor($blue[0], $blue[1], $blue[2]);
        if (method_exists($pdf, 'RoundedRect')) {
            $pdf->RoundedRect(61, 230, 26, 30, 5, '1111', 'F');
        } else {
            $pdf->Rect(61, 230, 26, 30, 'F');
        }

        $pdf->SetFillColor($gold[0], $gold[1], $gold[2]);
        if (method_exists($pdf, 'Circle')) {
            $pdf->Circle(113, 260, 27, 0, 360, 'F');
        }

        $pdf->SetFillColor($orange[0], $orange[1], $orange[2]);
        if (method_exists($pdf, 'Circle')) {
            $pdf->Circle(113, 260, 18, 180, 270, 'F');
        }

        $pdf->SetFillColor($blue[0], $blue[1], $blue[2]);
        if (method_exists($pdf, 'RoundedRect')) {
            $pdf->RoundedRect(5, 260, 52, 30, 15, '1111', 'F');
        } else {
            $pdf->Rect(5, 260, 52, 30, 'F');
        }

        $pdf->SetFillColor(252, 234, 209);
        if (method_exists($pdf, 'Circle')) {
            $pdf->Circle(18.5, 275, 6, 0, 360, 'F');
        }

        $pdf->SetFillColor($navy[0], $navy[1], $navy[2]);
        if (method_exists($pdf, 'Circle')) {
            $pdf->Circle(125, 275, 8, 0, 360, 'F');
        }
    }

    private static function renderContentPage($pdf, $mulank, $sections, $isLast, $brand, $langCode)
    {
        $pdf->AddPage();
        $pdf->SetFillColor(252, 234, 209);
        $pdf->Rect(0, 0, 210, 297, 'F');

        $pdf->SetTextColor(0, 0, 0);
        self::setLangFont($pdf, $langCode, 17, true, false);
        $pdf->SetXY(14, 16);
        $pdf->Cell(182, 10, self::getMulankLabel($langCode) . ': ' . $mulank, 0, 1, 'L');

        $y = 31;

        foreach ($sections as $section) {
            $heading = isset($section['heading']) ? (string) $section['heading'] : 'Section';
            $contentRaw = $section['content'] ?? '';
            $content = self::pickSectionContent($contentRaw);
            $content = str_replace('\\n', "\n", $content);
            $content = self::normalizeContentForLanguage($content, $langCode);

            self::setLangFont($pdf, $langCode, 12, true, false);
            $headingH = $pdf->getStringHeight(176, $heading) + 3;
            if ($headingH < 9.5) {
                $headingH = 9.5;
            }

            // Heading pill (rounded premium style)
            $pdf->SetFillColor(242, 92, 42);
            $pdf->SetTextColor(255, 255, 255);
            if (method_exists($pdf, 'RoundedRect')) {
                $pdf->RoundedRect(16, $y, 176, $headingH, 2.4, '1111', 'F');
            } else {
                $pdf->Rect(16, $y, 176, $headingH, 'F');
            }
            $pdf->SetXY(19, $y + 0.8);
            $pdf->MultiCell(170, $headingH - 1, $heading, 0, 'L', false, 1, 19, $y + 0.8, true, 0, false, true, $headingH - 1, 'M');
            $y += $headingH + 2.8;

            self::setLangFont($pdf, $langCode, 10.8, false, false);
            $contentH = $pdf->getStringHeight(176, $content) + 7.5;
            if ($contentH < 13.5) {
                $contentH = 13.5;
            }

            // Ensure footer has breathing space on final page
            $maxY = $isLast ? 262 : 282;
            if ($y + $contentH > $maxY) {
                $contentH = max(13.5, $maxY - $y);
            }

            $pdf->SetFillColor(255, 179, 71);
            $pdf->SetTextColor(0, 0, 0);
            if (method_exists($pdf, 'RoundedRect')) {
                $pdf->RoundedRect(16, $y, 176, $contentH, 3, '1111', 'F');
            } else {
                $pdf->Rect(16, $y, 176, $contentH, 'F');
            }
            $pdf->SetXY(20, $y + 2.1);
            $pdf->MultiCell(168, $contentH - 3.8, $content, 0, 'L', false, 1, 20, $y + 2.1, true, 0, false, true, $contentH - 3.8, 'T');
            $y += $contentH + 5.4;

            if ($y >= 264) {
                break;
            }
        }

        if ($isLast) {
            $note = 'NOTE: You can call us round the clock for any query regarding your numerology report on ' . (string) ($brand['phone'] ?? '');
            $pdf->SetFillColor(242, 92, 42);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('freesans', 'B', 9);
            if (method_exists($pdf, 'RoundedRect')) {
                $pdf->RoundedRect(16, 276, 176, 10, 2.2, '1111', 'F');
            } else {
                $pdf->Rect(16, 276, 176, 10, 'F');
            }
            $pdf->SetXY(18, 278.2);
            $pdf->MultiCell(172, 6, $note, 0, 'C', false, 1, 18, 278.2, true, 0, false, true, 6, 'M');
        }
    }

    private static function renderFinalNotePage($pdf, $brand)
    {
        $pdf->AddPage();

        // Dark premium final page (similar to provided sample style)
        $pdf->SetFillColor(27, 51, 82);
        $pdf->Rect(0, 0, 210, 297, 'F');

        $pdf->SetDrawColor(242, 92, 42);
        $pdf->SetLineWidth(1.2);
        $pdf->Line(5, 8, 205, 8);

        $pdf->SetFillColor(32, 99, 170);
        if (method_exists($pdf, 'RoundedRect')) {
            $pdf->RoundedRect(95, 12, 20, 12, 1.8, '1111', 'F');
        } else {
            $pdf->Rect(95, 12, 20, 12, 'F');
        }

        $pdf->SetTextColor(245, 180, 54);
        $pdf->SetFont('freesans', 'B', 13);
        $pdf->SetXY(99, 15.5);
        $pdf->Cell(12, 5, 'NOTE', 0, 1, 'C');

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('freesans', '', 10);
        $bodyTop = "YOU CAN CALL US ROUND THE CLOCK FOR ANY QUERY REGARDING YOUR REPORT.\nIF YOU HAVE ANY THEN DO NOT HESITATE TO CONTACT US.";
        $pdf->SetXY(25, 30);
        $pdf->MultiCell(160, 6, $bodyTop, 0, 'C', false, 1);

        $pdf->SetTextColor(245, 180, 54);
        $pdf->SetFont('freesans', 'B', 16);
        $pdf->SetXY(25, 58);
        $pdf->Cell(160, 8, 'CALL US ON', 0, 1, 'C');

        $pdf->SetTextColor(242, 92, 42);
        $pdf->SetFont('freesans', 'B', 20);
        $pdf->SetXY(25, 68);
        $pdf->Cell(160, 10, (string) ($brand['phone'] ?? ''), 0, 1, 'C');

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('freesans', '', 10);
        $bodyMid = "FOR SOLUTIONS OF THE ABSENCE NUMBERS' ENERGIES YOU LACK IN YOUR LIFE,\nWE ADVISE YOU TO USE THE FORTUNE CARD\nFOR FURTHER INFO ON THE SAME JUST";
        $pdf->SetXY(25, 90);
        $pdf->MultiCell(160, 6, $bodyMid, 0, 'C', false, 1);

        $pdf->SetTextColor(245, 180, 54);
        $pdf->SetFont('freesans', 'B', 16);
        $pdf->SetXY(25, 120);
        $pdf->Cell(160, 8, 'CONTACT US ON', 0, 1, 'C');

        $pdf->SetTextColor(242, 92, 42);
        $pdf->SetFont('freesans', 'B', 18);
        $pdf->SetXY(25, 130);
        $pdf->Cell(160, 9, (string) ($brand['email'] ?? ''), 0, 1, 'C');

        $pdf->SetFillColor(32, 99, 170);
        if (method_exists($pdf, 'RoundedRect')) {
            $pdf->RoundedRect(82, 145, 46, 12, 1.8, '1111', 'F');
        } else {
            $pdf->Rect(82, 145, 46, 12, 'F');
        }

        $pdf->SetTextColor(245, 180, 54);
        $pdf->SetFont('freesans', 'B', 16);
        $pdf->SetXY(85, 148);
        $pdf->Cell(40, 6, 'SPECIAL NOTE', 0, 1, 'C');

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('freesans', '', 10);
        $bodyBottom = "WE ARE NOT ADVISING YOU TO TRY THE CALCULATION BY YOUR OWN SELF AS\nOUR TEAM USES UNIQUE CALCULATION METHOD.\nWE ARE GLAD TO HAVE HAPPY ASSOCIATION WITH YOU. WE WISH YOU THE BEST FOR\nYOUR FUTURE ENDEAVOURS.";
        $pdf->SetXY(25, 166);
        $pdf->MultiCell(160, 6, $bodyBottom, 0, 'C', false, 1);

        $pdf->SetTextColor(242, 92, 42);
        $pdf->SetFont('freesans', 'B', 18);
        $pdf->SetXY(25, 206);
        $pdf->Cell(160, 8, (string) ($brand['email'] ?? ''), 0, 1, 'C');

        // Geometric lower motif to match premium style
        $pdf->SetFillColor(242, 92, 42);
        $pdf->Rect(42, 230, 26, 26, 'F');
        $pdf->SetFillColor(245, 180, 54);
        $pdf->Rect(68, 230, 26, 26, 'F');
        $pdf->SetFillColor(32, 99, 170);
        $pdf->Rect(94, 230, 26, 26, 'F');
        $pdf->SetFillColor(11, 31, 57);
        $pdf->Rect(120, 230, 26, 26, 'F');

        $pdf->SetFillColor(245, 180, 54);
        if (method_exists($pdf, 'Circle')) {
            $pdf->Circle(68, 274, 22, 0, 360, 'F');
        }
        $pdf->SetFillColor(32, 99, 170);
        if (method_exists($pdf, 'Circle')) {
            $pdf->Circle(120, 274, 22, 0, 360, 'F');
        }
    }
}
