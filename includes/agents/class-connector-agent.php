<?php
/**
 * Project Hyperion - Agent #7: The Connector
 * Smart Internal & External Linking
 *
 * Tugas: Menambahkan internal links ke artikel lain di website,
 * external links ke sumber terpercaya, dan "Baca Juga" section.
 */

if (!defined('ABSPATH')) exit;

class TSA_Connector_Agent {

    private $site_name = '';

    public function __construct() {
        $this->site_name = get_bloginfo('name') ?: 'sekali.id';
    }

    /**
     * Add smart links ke artikel
     */
    public function connect($title, $article_html, $knowledge_graph) {
        $log = array();
        $log[] = '[Connector] Memulai smart linking untuk: "' . $title . '"';

        $type = $knowledge_graph['type'] ?? 'destinasi';

        // Step 1: Find related internal posts
        $internal_links = $this->find_internal_links($title, $type);
        $log[] = '[Connector] Ditemukan ' . count($internal_links) . ' internal links';

        // Step 2: Add inline internal links
        $html = $this->add_inline_internal_links($article_html, $internal_links);
        $log[] = '[Connector] Inline internal links added';

        // Step 3: Add "Baca Juga" section
        $html = $this->add_baca_juga_section($html, $internal_links, $title);
        $log[] = '[Connector] "Baca Juga" section added';

        // Step 4: Add external authority links
        $html = $this->add_external_links($html, $title, $type);
        $log[] = '[Connector] External authority links added';

        // Step 5: Add disclaimer
        $html = $this->add_disclaimer($html);

        // Step 6: Generate categories and tags
        $taxonomy = $this->generate_taxonomy($title, $type, $knowledge_graph);
        $log[] = '[Connector] Taxonomy: category="' . $taxonomy['category'] . '", tags=' . count($taxonomy['tags']);

        // Step 7: Generate image suggestions
        $images = $this->generate_image_suggestions($title, $article_html, $type);
        $log[] = '[Connector] ' . count($images) . ' image suggestions generated';

        $word_count = str_word_count(strip_tags($html));
        $log[] = '[Connector] ✓ Linking selesai (' . $word_count . ' kata final)';

        return array(
            'article_html'     => $html,
            'internal_links'   => $internal_links,
            'taxonomy'         => $taxonomy,
            'image_suggestions' => $images,
            'word_count'       => $word_count,
            'log'              => $log,
        );
    }

    /**
     * Find related internal posts
     */
    private function find_internal_links($title, $type) {
        $links = array();

        // Search existing posts by keyword
        $title_words = explode(' ', $title);
        $search_terms = array();

        foreach ($title_words as $word) {
            if (strlen($word) > 3) {
                $search_terms[] = $word;
            }
        }

        // Search by each keyword
        foreach ($search_terms as $term) {
            $query = new WP_Query(array(
                'post_type'      => 'post',
                'post_status'    => 'publish',
                's'              => $term,
                'posts_per_page' => 5,
                'orderby'        => 'relevance',
            ));

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $post_title = get_the_title();

                    // Skip if same title
                    if (strtolower($post_title) === strtolower($title)) continue;

                    $links[$post_id] = array(
                        'id'    => $post_id,
                        'title' => $post_title,
                        'url'   => get_permalink($post_id),
                        'relevance' => similar_text(strtolower($title), strtolower($post_title)),
                    );
                }
            }
            wp_reset_postdata();
        }

        // Also search by category
        $categories = get_categories(array('hide_empty' => false));
        foreach ($categories as $cat) {
            $cat_name_lower = strtolower($cat->name);
            foreach ($search_terms as $term) {
                if (strpos($cat_name_lower, strtolower($term)) !== false) {
                    $cat_posts = get_posts(array(
                        'category' => $cat->term_id,
                        'numberposts' => 3,
                        'post_status' => 'publish',
                    ));
                    foreach ($cat_posts as $post) {
                        if (strtolower($post->post_title) === strtolower($title)) continue;
                        $links[$post->ID] = array(
                            'id'    => $post->ID,
                            'title' => $post->post_title,
                            'url'   => get_permalink($post->ID),
                            'relevance' => 50,
                        );
                    }
                }
            }
        }

        // Sort by relevance
        usort($links, function($a, $b) {
            return $b['relevance'] - $a['relevance'];
        });

        return array_slice($links, 0, 8);
    }

    /**
     * Add inline internal links naturally in the article
     */
    private function add_inline_internal_links($html, $internal_links) {
        if (empty($internal_links)) return $html;

        $links_added = 0;
        $max_inline = 3;

        foreach ($internal_links as $link) {
            if ($links_added >= $max_inline) break;

            // Find a relevant keyword in the article to link
            $link_title = $link['title'];
            $link_url = $link['url'];

            // Extract key phrases from link title
            $phrases = $this->extract_linkable_phrases($link_title);

            foreach ($phrases as $phrase) {
                if (strlen($phrase) < 4) continue;

                // Find the phrase in article text (not in headings or existing links)
                $pattern = '/(?<!<h[2-6][^>]*>)(?<!<a[^>]*>)\b(' . preg_quote($phrase, '/') . ')\b(?![^<]*<\/a>)(?![^<]*<\/h[2-6]>)/iu';

                if (preg_match($pattern, $html)) {
                    $html = preg_replace(
                        $pattern,
                        '<a href="' . esc_url($link_url) . '" title="' . esc_attr($link_title) . '">$1</a>',
                        $html,
                        1
                    );
                    $links_added++;
                    break;
                }
            }
        }

        return $html;
    }

    /**
     * Extract linkable phrases from title
     */
    private function extract_linkable_phrases($title) {
        $phrases = array();

        // Full title (without common words)
        $clean = preg_replace('/\b(di|ke|dan|atau|yang|untuk|dari|dengan|pada|ini|itu|adalah|akan|bisa|dapat)\b/i', '', $title);
        $clean = preg_replace('/\s+/', ' ', trim($clean));
        $phrases[] = $clean;

        // Individual significant words
        $words = explode(' ', $title);
        foreach ($words as $word) {
            if (strlen($word) > 4) {
                $phrases[] = $word;
            }
        }

        // Two-word combinations
        for ($i = 0; $i < count($words) - 1; $i++) {
            if (strlen($words[$i]) > 3 && strlen($words[$i + 1]) > 3) {
                $phrases[] = $words[$i] . ' ' . $words[$i + 1];
            }
        }

        return $phrases;
    }

    /**
     * Add "Baca Juga" section sebelum kesimpulan
     */
    private function add_baca_juga_section($html, $internal_links, $title) {
        $links_to_show = array_slice($internal_links, 0, 5);

        if (empty($links_to_show)) {
            // Generate placeholder links based on topic
            $links_to_show = $this->generate_placeholder_links($title);
        }

        $baca_juga = "\n\n<div class=\"tsa-baca-juga\" style=\"background:#f8f9fa; border-left:4px solid #0073aa; padding:20px; margin:25px 0; border-radius:0 4px 4px 0;\">\n";
        $baca_juga .= "<p style=\"font-weight:bold; margin-bottom:10px; color:#0073aa; font-size:16px;\">Baca Juga:</p>\n<ul style=\"margin:0; padding-left:20px;\">\n";

        foreach ($links_to_show as $link) {
            $url = $link['url'] ?? '#';
            $link_title = $link['title'] ?? '';
            $baca_juga .= "<li style=\"margin-bottom:5px;\"><a href=\"{$url}\" style=\"color:#0073aa; text-decoration:none;\">{$link_title}</a></li>\n";
        }

        $baca_juga .= "</ul>\n</div>\n\n";

        // Insert before Kesimpulan heading or at the end
        if (preg_match('/<h2[^>]*>\s*Kesimpulan/i', $html)) {
            $html = preg_replace('/(<h2[^>]*>\s*Kesimpulan)/i', $baca_juga . '$1', $html, 1);
        } else {
            // Insert before last H2
            $last_h2_pos = strrpos($html, '<h2');
            if ($last_h2_pos !== false) {
                $html = substr($html, 0, $last_h2_pos) . $baca_juga . substr($html, $last_h2_pos);
            } else {
                $html .= $baca_juga;
            }
        }

        return $html;
    }

    /**
     * Generate placeholder links jika tidak ada internal posts
     */
    private function generate_placeholder_links($title) {
        $title_lower = strtolower($title);
        $links = array();

        // Detect location
        $locations = array('bali', 'bandung', 'jogja', 'yogyakarta', 'jakarta', 'malang', 'surabaya', 'lombok', 'semarang', 'solo');
        $detected_location = '';
        foreach ($locations as $loc) {
            if (strpos($title_lower, $loc) !== false) {
                $detected_location = ucfirst($loc);
                break;
            }
        }

        if (!empty($detected_location)) {
            $links[] = array('title' => "Wisata Terbaik di {$detected_location} yang Wajib Dikunjungi", 'url' => '#');
            $links[] = array('title' => "Kuliner Khas {$detected_location} Paling Populer", 'url' => '#');
            $links[] = array('title' => "Hotel Murah di {$detected_location} dengan Fasilitas Lengkap", 'url' => '#');
        } else {
            $links[] = array('title' => 'Destinasi Wisata Terpopuler di Indonesia', 'url' => '#');
            $links[] = array('title' => 'Tips Liburan Hemat untuk Keluarga', 'url' => '#');
            $links[] = array('title' => 'Kuliner Nusantara yang Wajib Dicoba', 'url' => '#');
        }

        return $links;
    }

    /**
     * Add external authority links
     */
    private function add_external_links($html, $title, $type) {
        // Add nofollow external links to authority sources
        $external_links = array();

        if ($type === 'destinasi' || $type === 'aktivitas') {
            $external_links[] = array(
                'anchor' => 'Google Maps',
                'url'    => 'https://maps.google.com/?q=' . urlencode($title),
            );
        }

        // Add external link naturally
        foreach ($external_links as $ext) {
            $pattern = '/\b(' . preg_quote($ext['anchor'], '/') . ')\b(?![^<]*<\/a>)/i';
            $replacement = '<a href="' . $ext['url'] . '" target="_blank" rel="noopener nofollow">' . $ext['anchor'] . '</a>';
            $html = preg_replace($pattern, $replacement, $html, 1);
        }

        return $html;
    }

    /**
     * Add disclaimer at the end
     */
    private function add_disclaimer($html) {
        $disclaimer = "\n\n<p style=\"font-style:italic; color:#666; font-size:13px; margin-top:30px; padding-top:15px; border-top:1px solid #eee;\"><em>Disclaimer: Informasi dalam artikel ini dapat berubah sewaktu-waktu. Untuk informasi terkini, silakan hubungi pihak pengelola atau kunjungi sumber resmi. Artikel ini ditulis oleh tim {$this->site_name} berdasarkan riset dari berbagai sumber terpercaya.</em></p>\n";

        $html .= $disclaimer;

        return $html;
    }

    /**
     * Generate taxonomy (category dan tags)
     */
    public function generate_taxonomy($title, $type, $kg) {
        $title_lower = strtolower($title);
        $ai_data = $kg['ai_analysis'] ?? array();

        // === CATEGORY ===
        $category = $this->determine_category($title_lower, $type);

        // Check if category exists, if not create it
        $cat_id = 0;
        $existing_cat = get_term_by('name', $category, 'category');
        if ($existing_cat) {
            $cat_id = $existing_cat->term_id;
        } else {
            $slug = sanitize_title($category);
            $existing_slug = get_term_by('slug', $slug, 'category');
            if ($existing_slug) {
                $cat_id = $existing_slug->term_id;
                $category = $existing_slug->name;
            }
        }

        // === TAGS ===
        $tags = $this->generate_tags($title, $type, $ai_data);

        return array(
            'category'    => $category,
            'category_id' => $cat_id,
            'tags'        => $tags,
        );
    }

    /**
     * Determine category
     */
    private function determine_category($title_lower, $type) {
        // Check existing categories first
        $categories = get_categories(array('hide_empty' => false));
        foreach ($categories as $cat) {
            $cat_lower = strtolower($cat->name);
            if (strpos($title_lower, $cat_lower) !== false || strpos($cat_lower, $type) !== false) {
                return $cat->name;
            }
        }

        // Default categories based on type
        $type_categories = array(
            'destinasi' => 'Wisata',
            'kuliner'   => 'Kuliner',
            'hotel'     => 'Akomodasi',
            'aktivitas' => 'Aktivitas',
        );

        // Check for location-based category
        $locations = array(
            'bali' => 'Wisata Bali', 'bandung' => 'Wisata Bandung', 'jogja' => 'Wisata Jogja',
            'yogyakarta' => 'Wisata Jogja', 'jakarta' => 'Wisata Jakarta', 'malang' => 'Wisata Malang',
            'lombok' => 'Wisata Lombok', 'surabaya' => 'Wisata Surabaya', 'semarang' => 'Wisata Semarang',
        );

        foreach ($locations as $loc => $cat_name) {
            if (strpos($title_lower, $loc) !== false) {
                return $cat_name;
            }
        }

        return $type_categories[$type] ?? 'Wisata';
    }

    /**
     * Generate tags (3-10 tags)
     */
    private function generate_tags($title, $type, $ai_data) {
        $tags = array();

        // Title words as tags
        $words = explode(' ', $title);
        foreach ($words as $word) {
            $word = trim(strtolower($word));
            if (strlen($word) > 3 && !in_array($word, array('yang', 'untuk', 'dari', 'dengan', 'pada', 'akan', 'bisa', 'dapat'))) {
                $tags[] = ucfirst($word);
            }
        }

        // Full title as tag
        $tags[] = $title;

        // Type-based tags
        $type_tags = array(
            'destinasi' => array('Wisata', 'Destinasi', 'Liburan', 'Tempat Wisata', 'Travel'),
            'kuliner'   => array('Kuliner', 'Makanan', 'Rekomendasi Kuliner', 'Food'),
            'hotel'     => array('Hotel', 'Penginapan', 'Akomodasi', 'Staycation'),
            'aktivitas' => array('Aktivitas', 'Outdoor', 'Adventure', 'Petualangan'),
        );

        if (isset($type_tags[$type])) {
            $tags = array_merge($tags, array_slice($type_tags[$type], 0, 3));
        }

        // LSI keywords from AI analysis
        if (!empty($ai_data['KEYWORD_LSI'])) {
            $lsi = array_map('trim', explode(',', $ai_data['KEYWORD_LSI']));
            $tags = array_merge($tags, array_slice($lsi, 0, 5));
        }

        // Deduplicate and limit
        $tags = array_unique(array_filter($tags));
        return array_slice($tags, 0, 10);
    }

    /**
     * Generate image suggestions
     */
    public function generate_image_suggestions($title, $article_html, $type) {
        $suggestions = array();

        // Featured image
        $suggestions[] = array(
            'position'    => 'featured',
            'keyword'     => $title,
            'description' => "Gambar utama untuk artikel {$title}",
            'alt_text'    => $title,
        );

        // Extract H2 headings for section images
        if (preg_match_all('/<h2[^>]*>(.*?)<\/h2>/i', $article_html, $matches)) {
            foreach ($matches[1] as $i => $heading) {
                $heading = strip_tags($heading);
                if ($i >= 4) break; // Max 4 section images

                $suggestions[] = array(
                    'position'    => 'section_' . ($i + 1),
                    'keyword'     => $heading . ' ' . $title,
                    'description' => "Gambar untuk section: {$heading}",
                    'alt_text'    => $heading . ' - ' . $title,
                );
            }
        }

        // Infographic suggestion
        $suggestions[] = array(
            'position'    => 'infographic',
            'keyword'     => "infografis {$title}",
            'description' => "Infografis rangkuman informasi penting",
            'alt_text'    => "Infografis {$title}",
        );

        return $suggestions;
    }
}
