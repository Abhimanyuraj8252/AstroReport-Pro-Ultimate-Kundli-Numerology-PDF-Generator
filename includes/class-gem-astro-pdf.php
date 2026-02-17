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
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->SetFont('freesans', '', 12);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        $seed = crc32($name . $dob);
        srand($seed);

        self::renderCoverPage($pdf, $name);

        $total = count($sections);
        $chunks = array_chunk($sections, 4);
        $processed = 0;

        foreach ($chunks as $chunk) {
            $processed += count($chunk);
            $isLast = ($processed >= $total);
            self::renderContentPage($pdf, $mulank, $chunk, $isLast);
        }

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

    private static function renderCoverPage($pdf, $name)
    {
        $pdf->AddPage();

        // Base page background (index-like cream)
        $pdf->SetFillColor(252, 234, 209);
        $pdf->Rect(0, 0, 210, 297, 'F');

        // Left white panel: 320px of 794px A4 canvas ~= 84.6mm
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(0, 0, 84.6, 297, 'F');

        $pdf->SetDrawColor(28, 46, 64);
        $pdf->SetLineWidth(1);
        $pdf->Line(84.6, 0, 84.6, 297);

        // Half circle accent (150x300px ~= 39.7x79.3mm)
        $pdf->SetFillColor(212, 175, 55);
        if (method_exists($pdf, 'RoundedRect')) {
            $pdf->RoundedRect(-0.1, 26.5, 39.7, 79.3, 19.8, '1111', 'F');
        } else {
            $pdf->Rect(0, 26.5, 39.7, 79.3, 'F');
        }

        $logoPath = GEM_ASTRO_PATH . 'fonts/logo.jpg';
        if (file_exists($logoPath)) {
            // Center inside left panel
            $pdf->Image($logoPath, 5.3, 122, 74, 0, '', '', '', false, 300);
        }

        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetFont('freesans', 'I', 11);
        $pdf->SetXY(95.2, 21.2);
        $pdf->Cell(120, 8, 'Let\'s bring you the new life', 0, 1, 'L');

        $pdf->SetTextColor(242, 92, 42);
        $pdf->SetFont('freesans', 'B', 26);
        $pdf->SetX(95.2);
        $pdf->MultiCell(105, 14, 'Hello ' . $name . ',', 0, 'L', false, 1);

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('freesans', '', 23);
        $pdf->SetX(95.2);
        $pdf->MultiCell(105, 12, "Welcome To\nYour GEM\nASTROLOGY\nReport", 0, 'L', false, 1);

        $pdf->SetFont('freesans', '', 11);
        $pdf->SetXY(95.2, 245);
        $pdf->MultiCell(105, 6, "Prepared by\nwww.niongemastro.com\n+91 910 430 1456\nniongemastro@gmail.com", 0, 'L', false, 1);
    }

    private static function renderContentPage($pdf, $mulank, $sections, $isLast)
    {
        $pdf->AddPage();
        $pdf->SetFillColor(252, 234, 209);
        $pdf->Rect(0, 0, 210, 297, 'F');

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('freesans', 'B', 17);
        $pdf->SetXY(14, 16);
        $pdf->Cell(182, 10, 'Mulank: ' . $mulank, 0, 1, 'L');

        $y = 31;

        foreach ($sections as $section) {
            $heading = isset($section['heading']) ? (string) $section['heading'] : 'Section';
            $contentRaw = $section['content'] ?? '';
            $content = self::pickSectionContent($contentRaw);
            $content = str_replace('\\n', "\n", $content);

            $pdf->SetFont('freesans', 'B', 12);
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

            $pdf->SetFont('freesans', '', 10.8);
            $contentH = $pdf->getStringHeight(176, $content) + 7.5;
            if ($contentH < 13.5) {
                $contentH = 13.5;
            }

            // Ensure footer has breathing space on final page
            $maxY = $isLast ? 264 : 282;
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
        }

        if ($isLast) {
            $note = 'NOTE: You can call us round the clock for any query regarding your numerology report on +91 910 430 1456';
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
}
