<?php
/**
 * Project Hyperion - Agent #5: The Stylist
 * Semantic HTML & Rich Formatting
 *
 * Tugas: Memastikan artikel memiliki formatting HTML yang kaya dan profesional
 * termasuk bold, italic, tabel, list, blockquote, dan semantic markup.
 */

if (!defined('ABSPATH')) exit;

class TSA_Stylist_Agent {

    private $site_name = '';

    public function __construct() {
        $this->site_name = get_bloginfo('name') ?: 'sekali.id';
    }

    /**
     * Apply rich formatting ke artikel
     */
    public function style($title, $article_html, $blueprint) {
        $log = array();
        $log[] = '[Stylist] Memulai rich formatting untuk: "' . $title . '"';

        $type = $blueprint['type'] ?? 'destinasi';

        // Step 1: Clean dan normalize HTML
        $html = $this->normalize_html($article_html);
        $log[] = '[Stylist] HTML normalized';

        // Step 2: Apply bold formatting untuk keyword penting
        $keywords = $this->extract_keywords($title, $type);
        $html = $this->apply_bold_formatting($html, $keywords);
        $log[] = '[Stylist] Bold formatting applied (' . count($keywords) . ' keywords)';

        // Step 3: Apply italic untuk istilah asing/lokal
        $html = $this->apply_italic_formatting($html);
        $log[] = '[Stylist] Italic formatting applied';

        // Step 4: Ensure tables are properly formatted
        $html = $this->format_tables($html);
        $log[] = '[Stylist] Tables formatted';

        // Step 5: Ensure lists are properly formatted
        $html = $this->format_lists($html);
        $log[] = '[Stylist] Lists formatted';

        // Step 6: Add blockquotes for important notes
        $html = $this->add_blockquotes($html);
        $log[] = '[Stylist] Blockquotes added';

        // Step 7: Add horizontal rules between major sections
        $html = $this->add_section_dividers($html);

        // Step 8: Clean up excessive formatting
        $html = $this->cleanup_formatting($html);

        // Step 9: Validate HTML structure
        $html = $this->validate_html($html);

        $word_count = str_word_count(strip_tags($html));
        $log[] = '[Stylist] ✓ Styling selesai (' . $word_count . ' kata)';

        // Count formatting elements
        $stats = $this->count_formatting($html);
        $log[] = '[Stylist] Stats: ' . $stats['bold'] . ' bold, ' . $stats['italic'] . ' italic, ' . $stats['tables'] . ' tables, ' . $stats['lists'] . ' lists';

        return array(
            'article_html'    => $html,
            'formatting_stats' => $stats,
            'log'             => $log,
        );
    }

    /**
     * Normalize HTML
     */
    private function normalize_html($html) {
        // Convert markdown remnants to HTML
        $html = preg_replace('/\*\*\*(.*?)\*\*\*/', '<strong><em>$1</em></strong>', $html);
        $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/(?<![*])\*([^*]+)\*(?![*])/', '<em>$1</em>', $html);

        // Convert markdown headers to HTML
        $html = preg_replace('/^######\s+(.+)$/m', '<h6>$1</h6>', $html);
        $html = preg_replace('/^#####\s+(.+)$/m', '<h5>$1</h5>', $html);
        $html = preg_replace('/^####\s+(.+)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $html);

        // Convert markdown tables to HTML tables
        $html = $this->convert_markdown_tables($html);

        // Convert markdown lists
        $html = $this->convert_markdown_lists($html);

        // Ensure paragraphs are wrapped in <p> tags
        $lines = explode("\n", $html);
        $result = '';
        $in_block = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                $result .= "\n";
                continue;
            }

            // Skip lines that are already HTML block elements
            if (preg_match('/^<(h[1-6]|p|table|thead|tbody|tr|th|td|ul|ol|li|blockquote|div|hr|!--)/', $trimmed)) {
                $result .= $line . "\n";
                continue;
            }

            // Skip closing tags
            if (preg_match('/^<\/(table|thead|tbody|tr|ul|ol|blockquote|div)>/', $trimmed)) {
                $result .= $line . "\n";
                continue;
            }

            // Wrap plain text in <p> tags
            if (!preg_match('/^</', $trimmed) && strlen($trimmed) > 10) {
                $result .= '<p>' . $trimmed . "</p>\n";
            } else {
                $result .= $line . "\n";
            }
        }

        return trim($result);
    }

    /**
     * Convert markdown tables to HTML
     */
    private function convert_markdown_tables($html) {
        // Match markdown table pattern
        $pattern = '/\|(.+)\|\n\|[-| :]+\|\n((?:\|.+\|\n?)+)/';

        return preg_replace_callback($pattern, function($matches) {
            $header_cells = array_map('trim', explode('|', trim($matches[1], '| ')));
            $rows = array_filter(explode("\n", trim($matches[2])));

            $table = "<table>\n<thead>\n<tr>\n";
            foreach ($header_cells as $cell) {
                if (!empty($cell)) {
                    $table .= "<th>{$cell}</th>\n";
                }
            }
            $table .= "</tr>\n</thead>\n<tbody>\n";

            foreach ($rows as $row) {
                $cells = array_map('trim', explode('|', trim($row, '| ')));
                $table .= "<tr>\n";
                foreach ($cells as $cell) {
                    if ($cell !== '') {
                        $table .= "<td>{$cell}</td>\n";
                    }
                }
                $table .= "</tr>\n";
            }

            $table .= "</tbody>\n</table>";
            return $table;
        }, $html);
    }

    /**
     * Convert markdown lists to HTML
     */
    private function convert_markdown_lists($html) {
        // Ordered lists (1. 2. 3.)
        $html = preg_replace_callback('/(?:^|\n)((?:\d+\.\s+.+\n?)+)/m', function($matches) {
            $items = preg_split('/\n(?=\d+\.)/', trim($matches[1]));
            $list = "<ol>\n";
            foreach ($items as $item) {
                $item = preg_replace('/^\d+\.\s+/', '', trim($item));
                if (!empty($item)) {
                    $list .= "<li>{$item}</li>\n";
                }
            }
            $list .= "</ol>";
            return "\n" . $list . "\n";
        }, $html);

        // Unordered lists (- or *)
        $html = preg_replace_callback('/(?:^|\n)((?:[\-\*]\s+.+\n?)+)/m', function($matches) {
            $items = preg_split('/\n(?=[\-\*]\s)/', trim($matches[1]));
            $list = "<ul>\n";
            foreach ($items as $item) {
                $item = preg_replace('/^[\-\*]\s+/', '', trim($item));
                if (!empty($item)) {
                    $list .= "<li>{$item}</li>\n";
                }
            }
            $list .= "</ul>";
            return "\n" . $list . "\n";
        }, $html);

        return $html;
    }

    /**
     * Extract keywords untuk bold formatting
     */
    private function extract_keywords($title, $type) {
        $keywords = array();

        // Title words (2+ chars)
        $title_words = explode(' ', $title);
        foreach ($title_words as $word) {
            $word = trim($word);
            if (strlen($word) > 2) {
                $keywords[] = $word;
            }
        }

        // Full title
        $keywords[] = $title;

        // Type-specific keywords
        $type_keywords = array(
            'destinasi' => array('tiket masuk', 'jam buka', 'jam operasional', 'harga tiket', 'lokasi', 'fasilitas', 'daya tarik', 'spot foto', 'area parkir'),
            'kuliner'   => array('harga', 'menu', 'jam buka', 'lokasi', 'rasa', 'porsi', 'rekomendasi'),
            'hotel'     => array('harga kamar', 'fasilitas', 'check-in', 'check-out', 'lokasi', 'booking', 'review'),
            'aktivitas' => array('harga', 'durasi', 'lokasi', 'peralatan', 'tips', 'keamanan'),
        );

        if (isset($type_keywords[$type])) {
            $keywords = array_merge($keywords, $type_keywords[$type]);
        }

        // Common important keywords
        $keywords = array_merge($keywords, array(
            'gratis', 'murah', 'terbaik', 'terbaru', 'populer', 'wajib', 'rekomendasi',
        ));

        return array_unique($keywords);
    }

    /**
     * Apply bold formatting untuk keyword penting
     */
    private function apply_bold_formatting($html, $keywords) {
        // Track how many bolds we've added per section
        $sections = preg_split('/(<h[2-6][^>]*>.*?<\/h[2-6]>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $result = '';
        $bold_count = 0;

        foreach ($sections as $section) {
            // Skip headings
            if (preg_match('/^<h[2-6]/i', trim($section))) {
                $result .= $section;
                $bold_count = 0; // Reset per section
                continue;
            }

            // Apply bold to keywords (max 4 per section)
            $section_bold = 0;
            foreach ($keywords as $keyword) {
                if ($section_bold >= 4) break;

                // Skip if already bold
                $keyword_escaped = preg_quote($keyword, '/');
                $pattern = '/(?<!<strong>)(?<!<\/strong>)\b(' . $keyword_escaped . ')\b(?![^<]*<\/strong>)/iu';

                $section = preg_replace_callback($pattern, function($m) use (&$section_bold) {
                    if ($section_bold >= 4) return $m[0];
                    $section_bold++;
                    return '<strong>' . $m[1] . '</strong>';
                }, $section, 1);
            }

            $result .= $section;
        }

        return $result;
    }

    /**
     * Apply italic formatting untuk istilah asing/lokal
     */
    private function apply_italic_formatting($html) {
        $foreign_terms = array(
            'hidden gem', 'spot foto', 'instagramable', 'sunset', 'sunrise',
            'snorkeling', 'diving', 'surfing', 'rafting', 'hiking', 'trekking',
            'camping', 'glamping', 'resort', 'villa', 'cottage', 'homestay',
            'weekend', 'weekday', 'peak season', 'low season', 'high season',
            'rooftop', 'infinity pool', 'waterpark', 'playground',
            'selfie', 'drone', 'vlog', 'review', 'rating',
            'check-in', 'check-out', 'booking', 'online',
        );

        foreach ($foreign_terms as $term) {
            $pattern = '/(?<!<em>)(?<!<\/em>)\b(' . preg_quote($term, '/') . ')\b(?![^<]*<\/em>)/i';
            $html = preg_replace($pattern, '<em>$1</em>', $html, 1);
        }

        return $html;
    }

    /**
     * Format tables properly
     */
    private function format_tables($html) {
        // Add WordPress-compatible table classes
        $html = preg_replace('/<table>/', '<table class="wp-block-table" style="width:100%; border-collapse:collapse; margin:20px 0;">', $html);
        $html = preg_replace('/<table\s+(?!class)/', '<table class="wp-block-table" style="width:100%; border-collapse:collapse; margin:20px 0;" ', $html);

        // Style th elements
        $html = preg_replace('/<th>/', '<th style="background-color:#f0f0f0; padding:10px; border:1px solid #ddd; text-align:left;">', $html);
        $html = preg_replace('/<th\s+(?!style)/', '<th style="background-color:#f0f0f0; padding:10px; border:1px solid #ddd; text-align:left;" ', $html);

        // Style td elements
        $html = preg_replace('/<td>/', '<td style="padding:10px; border:1px solid #ddd;">', $html);
        $html = preg_replace('/<td\s+(?!style)/', '<td style="padding:10px; border:1px solid #ddd;" ', $html);

        return $html;
    }

    /**
     * Format lists properly
     */
    private function format_lists($html) {
        // Add spacing to list items
        $html = preg_replace('/<ul>/', '<ul style="margin:15px 0; padding-left:25px;">', $html);
        $html = preg_replace('/<ol>/', '<ol style="margin:15px 0; padding-left:25px;">', $html);
        $html = preg_replace('/<li>/', '<li style="margin-bottom:8px; line-height:1.7;">', $html);

        return $html;
    }

    /**
     * Add blockquotes for important notes
     */
    private function add_blockquotes($html) {
        // Convert "Catatan:" or "Tips:" paragraphs to blockquotes
        $html = preg_replace(
            '/<p[^>]*>\s*<em>\s*(Catatan|Tips|Penting|Perhatian|Info|Disclaimer)[:\s]*(.*?)<\/em>\s*<\/p>/i',
            '<blockquote style="border-left:4px solid #0073aa; padding:15px 20px; margin:20px 0; background:#f7f7f7; font-style:italic;"><strong>$1:</strong> $2</blockquote>',
            $html
        );

        // Also match non-italic versions
        $html = preg_replace(
            '/<p[^>]*>\s*(Catatan|Tips|Penting|Perhatian|Info|Disclaimer)[:\s]+(.*?)<\/p>/i',
            '<blockquote style="border-left:4px solid #0073aa; padding:15px 20px; margin:20px 0; background:#f7f7f7; font-style:italic;"><strong>$1:</strong> $2</blockquote>',
            $html
        );

        return $html;
    }

    /**
     * Add section dividers
     */
    private function add_section_dividers($html) {
        // Add subtle divider before each H2 (except the first one)
        $count = 0;
        $html = preg_replace_callback('/<h2/i', function($m) use (&$count) {
            $count++;
            if ($count > 1) {
                return "\n<hr style=\"border:none; border-top:1px solid #eee; margin:30px 0;\">\n\n<h2";
            }
            return '<h2';
        }, $html);

        return $html;
    }

    /**
     * Cleanup excessive formatting
     */
    private function cleanup_formatting($html) {
        // Remove nested bold
        $html = preg_replace('/<strong>\s*<strong>(.*?)<\/strong>\s*<\/strong>/i', '<strong>$1</strong>', $html);

        // Remove nested italic
        $html = preg_replace('/<em>\s*<em>(.*?)<\/em>\s*<\/em>/i', '<em>$1</em>', $html);

        // Remove empty tags
        $html = preg_replace('/<(strong|em|p|span)>\s*<\/\1>/i', '', $html);

        // Remove bold/italic from headings (headings are already emphasized)
        $html = preg_replace_callback('/<h([2-6])[^>]*>(.*?)<\/h\1>/i', function($m) {
            $content = strip_tags($m[2]);
            return '<h' . $m[1] . '>' . $content . '</h' . $m[1] . '>';
        }, $html);

        // Clean excessive whitespace
        $html = preg_replace('/\n{4,}/', "\n\n", $html);

        return trim($html);
    }

    /**
     * Validate HTML structure
     */
    private function validate_html($html) {
        // Ensure all paragraphs are properly closed
        $html = preg_replace('/<p([^>]*)>([^<]*?)(?=<h[2-6]|<table|<[uo]l|<blockquote|<hr)/i', '<p$1>$2</p>', $html);

        // Remove orphan closing tags
        $html = preg_replace('/^<\/p>\s*/m', '', $html);

        return $html;
    }

    /**
     * Count formatting elements
     */
    private function count_formatting($html) {
        return array(
            'bold'       => preg_match_all('/<strong>/i', $html),
            'italic'     => preg_match_all('/<em>/i', $html),
            'tables'     => preg_match_all('/<table/i', $html),
            'lists'      => preg_match_all('/<[uo]l/i', $html),
            'blockquotes' => preg_match_all('/<blockquote/i', $html),
            'headings'   => preg_match_all('/<h[2-6]/i', $html),
            'paragraphs' => preg_match_all('/<p/i', $html),
        );
    }
}
