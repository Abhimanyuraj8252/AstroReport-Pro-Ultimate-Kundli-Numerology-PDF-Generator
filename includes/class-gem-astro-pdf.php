<?php

if (!defined('ABSPATH') && !defined('GEM_ASTRO_PATH')) {
    if (!defined('GEM_ASTRO_PATH'))
        exit;
}

// Load mPDF from local vendor
if (file_exists(GEM_ASTRO_PATH . 'includes/vendor/autoload.php')) {
    require_once GEM_ASTRO_PATH . 'includes/vendor/autoload.php';
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
        // Ensure mPDF class exists
        if (!class_exists('\Mpdf\Mpdf')) {
            error_log('mPDF class not found. Please run composer install in includes directory.');
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
        }

        try {
            // Initialize mPDF
            // Initialize mPDF with Font Configuration
            $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];

            $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            // Use anonymous class to override LanguageToFont mapping for Hindi and Gujarati
            $languageToFontImpl = new class extends \Mpdf\Language\LanguageToFont {
                public function getLanguageOptions($llcc, $adobeCJK)
                {
                    if ($llcc === 'hi' || $llcc === 'hin') {
                        return [true, 'notosansdevanagari'];
                    }
                    if ($llcc === 'gu' || $llcc === 'guj') {
                        return [true, 'notosansgujarati'];
                    }
                    return parent::getLanguageOptions($llcc, $adobeCJK);
                }
            };

            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 0,
                'margin_bottom' => 0,
                'margin_header' => 0,
                'margin_footer' => 0,
                'orientation' => 'P',
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
                'fontDir' => array_merge($fontDirs, [
                    GEM_ASTRO_PATH . 'fonts',
                ]),
                'fontdata' => $fontData + [
                    'notosansdevanagari' => [
                        'R' => 'NotoSansDevanagari-Regular.ttf',
                        'B' => 'NotoSansDevanagari-Bold.ttf',
                        'useOTL' => 0xFF,
                        'useKashida' => 75,
                    ],
                    'notosansgujarati' => [
                        'R' => 'NotoSansGujarati-Regular.ttf',
                        'B' => 'NotoSansGujarati-Bold.ttf',
                        'useOTL' => 0xFF,
                        'useKashida' => 75,
                    ]
                ],
                'default_font' => 'dejavusans',
                'languageToFont' => $languageToFontImpl,
            ]);

            $mpdf->SetTitle('Kundli Report - ' . $name);
            $mpdf->SetAuthor('Gem Astrology');
            $mpdf->SetCreator('Gem Astrology');

            // Render Cover Page
            self::renderCoverPage($mpdf, $name, $brand, $language);

            // Render Content Pages (Continuous Flow)
            if (count($sections) > 0) {
                self::renderContentPage($mpdf, $mulank, $sections, $brand, $langCode);
            }

            // Render Final Note Page
            self::renderFinalNotePage($mpdf, $brand);

            // Output
            $upload_dir = wp_upload_dir();
            $gem_astro_dir = $upload_dir['basedir'] . '/gem-astrology-reports/';
            if (!file_exists($gem_astro_dir)) {
                wp_mkdir_p($gem_astro_dir);
            }

            $filename = 'GemAstro-Report-' . sanitize_file_name($name) . '-' . strtoupper($langCode) . '-' . time() . '.pdf';
            $file_path = $gem_astro_dir . $filename;

            $mpdf->Output($file_path, 'F');

            $file_url = str_replace(
                [$upload_dir['basedir'], '\\'],
                [$upload_dir['baseurl'], '/'],
                $file_path
            );

            return ['path' => $file_path, 'url' => $file_url];

        } catch (\Throwable $e) {
            error_log('GemAstroPDF mPDF generation failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function renderCoverPage($mpdf, $name, $brand, $language = 'en')
    {
        $mpdf->AddPage();

        // Colors
        $bgCream = '#FEF0D5';
        $cOrange = '#F25C2A';
        $cNavy = '#1C2E40';
        $cGold = '#F5B436';
        $cBlue = '#2063AA';
        $cDkBlue = '#142845';
        $cTtBlue = '#183282';
        $cTxDark = '#333333';
        $cGrOr = '#F05A28';

        // Font Stack removed - relying on mPDF languageToFont mapping

        // Full-page cream background
        $mpdf->WriteFixedPosHTML(
            '<div style="background:' . $bgCream . ';width:100%;height:100%"></div>',
            0,
            0,
            210,
            297,
            'hidden'
        );

        // ===== GEOMETRIC SHAPES: Top-Left Group =====
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cOrange . ';width:55mm;height:55mm;"></div>', 0, 95, 55, 55, 'hidden');
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cNavy . ';width:56mm;height:56mm;border-radius:50%;"></div>', 40, 102, 56, 56, 'hidden');
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cGold . ';width:50mm;height:50mm;border-radius:50%;"></div>', -8, 147, 50, 50, 'hidden');

        // ===== GEOMETRIC SHAPES: Bottom-Left Group =====
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cOrange . ';width:26mm;height:26mm;"></div>', 8, 208, 26, 26, 'hidden');
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cBlue . ';width:26mm;height:26mm;"></div>', 34, 208, 26, 26, 'hidden');
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cDkBlue . ';width:26mm;height:26mm;"></div>', 8, 234, 26, 26, 'hidden');
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cOrange . ';width:32mm;height:26mm;"></div>', 34, 234, 32, 26, 'hidden');
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cBlue . ';width:24mm;height:26mm;border-radius:3mm;"></div>', 60, 234, 24, 26, 'hidden');

        // Blue pill
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cBlue . ';width:48mm;height:28mm;border-radius:14mm;"></div>', 8, 260, 48, 28, 'hidden');
        // Cream dot
        $mpdf->WriteFixedPosHTML('<div style="background:' . $bgCream . ';width:10mm;height:10mm;border-radius:50%;"></div>', 14, 269, 10, 10, 'hidden');

        // ===== GEOMETRIC SHAPES: Bottom-Center Group =====
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cGold . ';width:50mm;height:50mm;border-radius:50%;"></div>', 88, 245, 50, 50, 'hidden');
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cOrange . ';width:18mm;height:18mm;border-radius:50%;"></div>', 100, 265, 18, 18, 'hidden');
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cGold . ';width:22mm;height:22mm;border-radius:50%;"></div>', 103, 257, 22, 22, 'hidden');
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cNavy . ';width:16mm;height:16mm;border-radius:50%;"></div>', 122, 273, 16, 16, 'hidden');

        // ===== VERTICAL LINE =====
        $mpdf->WriteFixedPosHTML('<div style="border-left:1mm solid ' . $cNavy . ';height:220mm;"></div>', 97, 30, 2, 220, 'hidden');

        // ===== BRANDING =====
        $logoPath = self::resolveBrandLogoPath((string) ($brand['cover_logo'] ?? ''));

        // Logo on Left Column (Above Geometry which starts at y=95)
        if (!empty($logoPath) && file_exists($logoPath)) {
            $mpdf->WriteFixedPosHTML(
                '<div><img src="' . $logoPath . '" style="width:75mm;" /></div>',
                15,
                20,
                80,
                80,
                'hidden'
            );
        }

        // Title on Right Column (Always show title for consistent layout)
        $mpdf->WriteFixedPosHTML(
            '<div style="font-weight:bold;font-size:16pt;color:' . $cTtBlue . ';">'
            . (string) ($brand['title'] ?? 'NION GEM ASTRO')
            . '</div>',
            106,
            28,
            95,
            14,
            'hidden'
        );

        // Tagline
        // Use normal style for Indic scripts because we don't have Italic font variants loaded
        $taglineStyle = 'italic';
        if ($language === 'hi' || $language === 'gu') {
            $taglineStyle = 'normal';
        }

        $mpdf->WriteFixedPosHTML(
            '<div style="font-style:' . $taglineStyle . ';font-size:9pt;color:' . $cTxDark . ';">'
            . (string) ($brand['tagline'] ?? 'Let\'s bring you the new life')
            . '</div>',
            106,
            55,
            95,
            8,
            'hidden'
        );

        // ===== GREETING =====
        $mpdf->WriteFixedPosHTML(
            '<div style="font-weight:bold;font-size:26pt;color:' . $cGrOr . ';">Hello '
            . (string) $name . ',</div>',
            106,
            72,
            95,
            30,
            'hidden'
        );

        // ===== WELCOME TEXT =====
        $welcomeLines = explode("\n", (string) ($brand['cover_welcome_text'] ?? "Welcome To\nYour GEM\nASTROLOGY\nReport"));
        $wHtml = '';
        foreach ($welcomeLines as $wl) {
            $wl = trim($wl);
            $fw = (strtoupper($wl) === 'ASTROLOGY') ? 'bold' : 'normal';
            $wHtml .= '<div style="font-weight:' . $fw . ';font-size:20pt;line-height:1.3;color:#000;">' . $wl . '</div>';
        }
        $mpdf->WriteFixedPosHTML(
            '<div>' . $wHtml . '</div>',
            106,
            108,
            95,
            60,
            'hidden'
        );

        // ===== PREPARED BY =====
        $websiteDisplay = self::getWebsiteDisplay($brand);
        $phone = (string) ($brand['phone'] ?? '+91 9801834437');
        $email = (string) ($brand['email'] ?? 'novanexusltd001@gmail.com');

        $preparedHtml = '<div style="color:' . $cTxDark . ';">'
            . '<div style="font-weight:bold;font-size:12pt;margin-bottom:2mm;">Prepared by</div>'
            . '<div style="font-size:10pt;line-height:1.5;">Web: ' . $websiteDisplay . '</div>'
            . '<div style="font-size:10pt;line-height:1.5;">Phone: ' . $phone . '</div>'
            . '<div style="font-size:10pt;line-height:1.5;">Email: ' . $email . '</div>'
            . '</div>';

        $mpdf->WriteFixedPosHTML($preparedHtml, 106, 192, 95, 40, 'hidden');
    }

    private static function renderContentPage($mpdf, $mulank, $sections, $brand, $langCode)
    {
        $mpdf->AddPage();

        // Styles for continuous flow
        $html = '<style>
            @page {
                background-color: #FEF0D5;
            }
            body { font-family: sans-serif; background-color: #FEF0D5; }
            .heading-pill { background-color: #F25C2A; color: #fff; padding: 2mm 5mm; border-radius: 2mm; font-weight: bold; font-size: 14pt; margin-bottom: 0; border-bottom-left-radius: 0; border-bottom-right-radius: 0; text-transform:uppercase; display: inline-block; }
            .content-box { background-color: #FFE0B2; color: #000; padding: 4mm; border-radius: 2mm; border-top-left-radius: 0; font-size: 11pt; line-height: 1.6; margin-bottom: 8mm; }
            .mulank-title { font-size: 22pt; font-weight: bold; color: #F25C2A; margin-bottom: 8mm; text-transform: uppercase; border-bottom: 2px solid #F25C2A; display: inline-block; padding-bottom: 2mm; }
        </style>';

        $html .= '<div style="padding: 10mm 10mm 0 10mm;">';

        // Mulank Heading
        $html .= '<div class="mulank-title">' . self::getMulankLabel($langCode) . ': ' . $mulank . '</div>';

        foreach ($sections as $section) {
            $heading = isset($section['heading']) ? (string) $section['heading'] : 'Section';
            $contentRaw = $section['content'] ?? '';
            $content = self::pickSectionContent($contentRaw);
            $content = str_replace('\\n', "\n", $content);
            $content = self::normalizeContentForLanguage($content, $langCode);
            $content = nl2br($content);

            $html .= '<div>';
            $html .= '<div class="heading-pill">' . $heading . '</div>';
            $html .= '<div class="content-box">' . $content . '</div>';
            $html .= '</div>';
        }

        // Footer Note (End of content)
        if ($langCode === 'hi') {
            $note = 'नोट: अंक ज्योतिष रिपोर्ट से संबंधित किसी भी प्रश्न के लिए आप हमें 24 घंटे ' . (string) ($brand['phone'] ?? '') . ' पर संपर्क कर सकते हैं।';
        } elseif ($langCode === 'gu') {
            $note = 'નોંધ: અંક જ્યોતિષ રિપોર્ટ વિશે કોઈપણ પ્રશ્ન માટે તમે અમને 24 કલાક ' . (string) ($brand['phone'] ?? '') . ' પર સંપર્ક કરી શકો છો।';
        } else {
            $note = 'NOTE: You can call us round the clock for any query regarding your numerology report on ' . (string) ($brand['phone'] ?? '');
        }
        $html .= '<div style="page-break-before: avoid; background-color:#F25C2A; color:#fff; padding:3mm; border-radius:2mm; text-align:center; font-size:10pt; margin-top:2mm; margin-bottom: 2mm;">' . $note . '</div>';

        $html .= '</div>';

        $mpdf->WriteHTML($html);
    }

    private static function renderFinalNotePage($mpdf, $brand)
    {
        $mpdf->AddPage();

        // Colors
        $bgDark = '#1C2E40';
        $cOrange = '#F25C2A';
        $cGold = '#F5B436';
        $cBlue = '#2063AA';
        $cDkBlue = '#142845';
        $cWhite = '#FFFFFF';

        $phone = (string) ($brand['phone'] ?? '+91 9801834437');
        $email = (string) ($brand['email'] ?? 'novanexusltd001@gmail.com');

        // Full page Dark Background
        $mpdf->WriteFixedPosHTML(
            '<div style="background:' . $bgDark . ';width:100%;height:100%"></div>',
            0,
            0,
            210,
            297,
            'hidden'
        );

        // ===== TOP ORANGE LINE =====
        $mpdf->WriteFixedPosHTML(
            '<div style="border-bottom:1.5mm solid ' . $cOrange . ';width:190mm;"></div>',
            10,
            15,
            190,
            2,
            'hidden'
        );

        // ===== NOTE BUTTON =====
        $mpdf->WriteFixedPosHTML(
            '<div style="background:' . $cBlue . ';color:' . $cGold . ';font-weight:bold;font-size:14pt;text-align:center;line-height:12mm;border-radius:2mm;">NOTE</div>',
            88,
            18,
            34,
            12,
            'hidden'
        );

        // ===== SECTION 1: Contact note =====
        $mpdf->WriteFixedPosHTML(
            '<div style="color:' . $cWhite . ';font-size:10pt;text-align:center;font-family:sans-serif;">YOU CAN CALL US ROUND THE CLOCK FOR ANY QUERY REGARDING YOUR REPORT.<br>IF YOU HAVE ANY THEN DO NOT HESITATE TO CONTACT US.</div>',
            20,
            42,
            170,
            20,
            'hidden'
        );

        // ===== SECTION 2: CALL US ON =====
        $mpdf->WriteFixedPosHTML(
            '<div style="color:' . $cGold . ';font-size:16pt;font-weight:bold;text-align:center;font-family:sans-serif;">CALL US ON</div>',
            20,
            68,
            170,
            10,
            'hidden'
        );
        $mpdf->WriteFixedPosHTML(
            '<div style="color:' . $cOrange . ';font-size:22pt;font-weight:bold;text-align:center;font-family:sans-serif;">' . $phone . '</div>',
            20,
            80,
            170,
            15,
            'hidden'
        );

        // ===== SECTION 3: Fortune card advice =====
        $mpdf->WriteFixedPosHTML(
            '<div style="color:' . $cWhite . ';font-size:10pt;text-align:center;font-family:sans-serif;">FOR SOLUTIONS OF THE ABSENCE NUMBERS\' ENERGIES YOU LACK IN YOUR LIFE,<br>WE ADVISE YOU TO USE THE FORTUNE CARD<br>FOR FURTHER INFO ON THE SAME JUST</div>',
            20,
            100,
            170,
            25,
            'hidden'
        );

        // ===== SECTION 4: CONTACT US ON =====
        $mpdf->WriteFixedPosHTML(
            '<div style="color:' . $cGold . ';font-size:16pt;font-weight:bold;text-align:center;font-family:sans-serif;">CONTACT US ON</div>',
            20,
            128,
            170,
            10,
            'hidden'
        );
        $mpdf->WriteFixedPosHTML(
            '<div style="color:' . $cOrange . ';font-size:18pt;font-weight:bold;text-align:center;font-family:sans-serif;">' . $email . '</div>',
            20,
            140,
            170,
            15,
            'hidden'
        );

        // ===== SPECIAL NOTE BUTTON =====
        $mpdf->WriteFixedPosHTML(
            '<div style="background:' . $cBlue . ';color:' . $cGold . ';font-weight:bold;font-size:14pt;text-align:center;line-height:12mm;border-radius:2mm;">SPECIAL NOTE</div>',
            75,
            160,
            60,
            12,
            'hidden'
        );

        // ===== SECTION 5: Special note text =====
        $mpdf->WriteFixedPosHTML(
            '<div style="color:' . $cWhite . ';font-size:10pt;text-align:center;font-family:sans-serif;">WE ARE NOT ADVISING YOU TO TRY THE CALCULATION BY YOUR OWN SELF AS<br>OUR TEAM USES UNIQUE CALCULATION METHOD.<br>WE ARE GLAD TO HAVE HAPPY ASSOCIATION WITH YOU. WE WISH YOU THE BEST FOR<br>YOUR FUTURE ENDEAVOURS.</div>',
            20,
            180,
            170,
            30,
            'hidden'
        );

        // ===== SECTION 6: Email again =====
        $mpdf->WriteFixedPosHTML(
            '<div style="color:' . $cOrange . ';font-size:18pt;font-weight:bold;text-align:center;font-family:sans-serif;">' . $email . '</div>',
            20,
            218,
            170,
            15,
            'hidden'
        );

        // ===== FOOTER GEOMETRIC SHAPES =====
        // 4 Squares (Moved up to y=232 to be safe)
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cOrange . ';width:28mm;height:20mm;"></div>', 42, 232, 28, 20, 'hidden');
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cGold . ';width:28mm;height:20mm;"></div>', 70, 232, 28, 20, 'hidden');
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cBlue . ';width:28mm;height:20mm;"></div>', 98, 232, 28, 20, 'hidden');
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cDkBlue . ';width:28mm;height:20mm;"></div>', 126, 232, 28, 20, 'hidden');

        // 2 Circles (Moved up to y=242 for safety, bottom 288)
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cGold . ';width:46mm;height:46mm;border-radius:50%;"></div>', 46, 242, 46, 46, 'hidden');
        $mpdf->WriteFixedPosHTML('<div style="background:' . $cBlue . ';width:46mm;height:46mm;border-radius:50%;"></div>', 100, 242, 46, 46, 'hidden');
    }

    // --- Helpers ---

    private static function getMulankLabel($langCode)
    {
        return 'Mulank';
    }

    private static function normalizeContentForLanguage($content, $langCode)
    {
        if ($langCode === 'gu' || $langCode === 'hi') {
            $content = str_replace(['•', '●', '▪', '◦', '▫', '■', '□', '◆', '◇', '○', '◉', '', '', '▪️'], '- ', $content);
            $lines = preg_split('/\R/u', $content);
            if (is_array($lines)) {
                foreach ($lines as &$line) {
                    $line = preg_replace('/^\s*[\p{P}\p{S}]+\s*/u', '- ', (string) $line);
                }
                unset($line);
                $content = implode("\n", $lines);
            }
        }
        return $content;
    }

    private static function getWebsiteDisplay($brand)
    {
        $websiteName = (string) ($brand['website_name'] ?? 'Trikrypta');
        $websiteUrl = (string) ($brand['website_url'] ?? '');
        if (!empty($websiteUrl)) {
            $host = parse_url($websiteUrl, PHP_URL_HOST);
            if (!empty($host))
                return $host;
            return $websiteUrl;
        }
        return $websiteName;
    }

    private static function normalizeLanguageCode($lang)
    {
        $lang = strtolower(trim((string) $lang));
        if ($lang === 'hindi' || $lang === 'hi')
            return 'hi';
        if ($lang === 'gujarati' || $lang === 'gu')
            return 'gu';
        return 'en';
    }

    private static function getLanguageData($langCode)
    {
        if ($langCode === 'hi')
            return function_exists('getHindiData') ? getHindiData() : [];
        if ($langCode === 'gu')
            return function_exists('getGujaratiData') ? getGujaratiData() : [];
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
            'cover_logo' => $getOption('gem_astro_cover_logo', ''),
            'cover_welcome_text' => $getOption('gem_astro_cover_welcome_text', "Welcome To\nYour GEM\nASTROLOGY\nReport"),
        ];
    }

    private static function resolveBrandLogoPath($logo)
    {
        $logo = trim((string) $logo);
        if (empty($logo))
            return '';
        if (strpos($logo, 'http://') === 0 || strpos($logo, 'https://') === 0) {
            if (function_exists('wp_get_upload_dir')) {
                $upload = wp_get_upload_dir();
                $baseurl = rtrim((string) ($upload['baseurl'] ?? ''), '/');
                $basedir = rtrim((string) ($upload['basedir'] ?? ''), '/');
                if (!empty($baseurl) && !empty($basedir) && strpos($logo, $baseurl) === 0) {
                    $relative = substr($logo, strlen($baseurl));
                    $candidate = $basedir . $relative;
                    if (file_exists($candidate))
                        return $candidate;
                }
            }
            return $logo;
        }
        if (file_exists($logo))
            return $logo;
        return '';
    }
}
