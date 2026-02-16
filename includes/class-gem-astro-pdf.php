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

        // Initialize TCPDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('Gem Astrology');
        $pdf->SetAuthor('Gem Astrology');
        $pdf->SetTitle('Kundli Report - ' . $booking_data['name']);

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);

        // Set Font
        // 'freesans' supports many languages including Hindi (Devanagari) better than basic fonts.
        // 'cid0cs' is for CJK. 
        // We will try 'freesans' which is usually included in TCPDF.
        $font_family = 'freesans';

        $pdf->SetFont($font_family, '', 12);

        // --- Cover Page ---
        $pdf->AddPage();
        self::drawCoverPage($pdf, $booking_data, $language);

        // --- Mulank Section ---
        $pdf->AddPage();
        self::drawMulankSection($pdf, $booking_data, $language);

        // Output PDF to file
        $upload_dir = wp_upload_dir();
        $gem_astro_dir = $upload_dir['basedir'] . '/gem-astrology-reports/';
        if (!file_exists($gem_astro_dir)) {
            wp_mkdir_p($gem_astro_dir);
        }

        // Add language code to filename to differentiate
        $filename = 'GemAstro-Report-' . sanitize_file_name($booking_data['name']) . '-' . strtoupper($language) . '-' . time() . '.pdf';
        $file_path = $gem_astro_dir . $filename;

        $pdf->Output($file_path, 'F');

        $file_url = str_replace(
            [$upload_dir['basedir'], '\\'],
            [$upload_dir['baseurl'], '/'],
            $file_path
        );

        return ['path' => $file_path, 'url' => $file_url];
    }

    private static function drawCoverPage($pdf, $data, $lang)
    {
        $pdf->SetFont('freesans', 'B', 24);
        $pdf->Cell(0, 20, 'GEM Astrology Report', 0, 1, 'C');

        $pdf->Ln(20);
        $pdf->SetFont('freesans', '', 14);

        $labels = [
            'hi' => ['name' => 'नाम', 'dob' => 'जन्म तिथि'],
            'en' => ['name' => 'Name', 'dob' => 'Date of Birth'],
            'gu' => ['name' => 'નામ', 'dob' => 'જન્મ તારીખ']
        ];

        // Map generic 'english'/'hindi' to codes if needed, or rely on codes passed
        $langCode = ($lang == 'hindi') ? 'hi' : (($lang == 'gujarati') ? 'gu' : 'en');
        // If lang is already 'hi', 'en', 'gu', use it across
        if (in_array($lang, ['hi', 'en', 'gu'])) {
            $langCode = $lang;
        }

        $l = $labels[$langCode] ?? $labels['en'];

        $pdf->Cell(0, 10, $l['name'] . ': ' . $data['name'], 0, 1, 'C');
        $pdf->Cell(0, 10, $l['dob'] . ': ' . $data['dob'], 0, 1, 'C');
    }

    private static function drawMulankSection($pdf, $data, $lang)
    {
        $mulank = self::calculateMulank($data['dob']);

        // Fetch data based on language
        // We need to call the global get functions which returned the massive arrays
        $allData = [];
        if ($lang === 'hi' || $lang === 'hindi') {
            $allData = function_exists('getHindiData') ? getHindiData() : [];
        } elseif ($lang === 'gu' || $lang === 'gujarati') {
            $allData = function_exists('getGujaratiData') ? getGujaratiData() : [];
        } else {
            $allData = function_exists('getEnglishData') ? getEnglishData() : [];
        }

        $content_data = isset($allData[$mulank]) ? $allData[$mulank] : [];

        if (empty($content_data)) {
            $pdf->Write(0, "No data available for Mulank $mulank in language: $lang");
            return;
        }

        $pdf->SetFont('freesans', 'B', 16);
        $pdf->Cell(0, 10, "Mulank: $mulank", 0, 1, 'L');
        $pdf->Ln(5);

        // Seed random for consistency in PDF too
        $seed = crc32($data['name'] . $data['dob']);
        srand($seed);

        foreach ($content_data as $section) {
            $pdf->SetFont('freesans', 'B', 14);
            // Handle potentially missing keys if structure varies
            $heading = $section['heading'] ?? 'Section';
            $pdf->Cell(0, 10, $heading, 0, 1, 'L');

            $pdf->SetFont('freesans', '', 12);

            $contentRaw = $section['content'] ?? '';
            $content = '';

            if (is_array($contentRaw)) {
                $content = $contentRaw[rand(0, count($contentRaw) - 1)];
            } else {
                $content = $contentRaw;
            }

            // Convert literal \n to actual newlines
            $content = str_replace('\n', "\n", $content);
            $pdf->MultiCell(0, 10, trim($content), 0, 'L');
            $pdf->Ln(5);
        }
    }
}
