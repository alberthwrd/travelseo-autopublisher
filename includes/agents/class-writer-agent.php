<?php
/**
 * Writer Agent V2 - 5-AI Orchestrated Content Writer
 *
 * This agent coordinates the 5 AI Writers through Content Orchestrator
 * to produce comprehensive, high-quality travel articles (700-2000+ words).
 *
 * Features:
 * - 5-AI parallel writing workflow
 * - Dynamic article structure based on content type
 * - Professional Indonesian text spinning
 * - Auto category and tag generation
 * - SEO metadata optimization
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/agents
 */

namespace TravelSEO_Autopublisher\Agents;

use TravelSEO_Autopublisher\Agents\Content_Orchestrator;
use TravelSEO_Autopublisher\Modules\Title_Suggester;
use TravelSEO_Autopublisher\Modules\Article_Structure;

use function TravelSEO_Autopublisher\tsa_get_option;
use function TravelSEO_Autopublisher\tsa_update_job;
use function TravelSEO_Autopublisher\tsa_log_job;
use function TravelSEO_Autopublisher\tsa_generate_meta_description;

/**
 * Writer Agent V2 Class
 */
class Writer_Agent {

    /**
     * Job ID
     *
     * @var int
     */
    private $job_id;

    /**
     * Research pack from Agent 1
     *
     * @var array
     */
    private $research_pack;

    /**
     * Job settings
     *
     * @var array
     */
    private $settings;

    /**
     * Draft pack result
     *
     * @var array
     */
    private $draft_pack;

    /**
     * Content Orchestrator instance
     *
     * @var Content_Orchestrator
     */
    private $orchestrator;

    /**
     * Constructor
     *
     * @param int   $job_id       Job ID
     * @param array $research_pack Research pack from Agent 1
     * @param array $settings     Job settings
     */
    public function __construct( $job_id, $research_pack, $settings = array() ) {
        $this->job_id = $job_id;
        $this->research_pack = $research_pack;
        $this->settings = wp_parse_args( $settings, array(
            'target_words_min' => 700,
            'target_words_max' => 2000,
            'language' => 'id',
            'tone' => 'informative',
            'content_type' => 'destinasi',
            'spin_content' => true,
            'spin_intensity' => 50,
        ) );

        // Initialize draft pack
        $this->draft_pack = array(
            'title' => '',
            'slug' => '',
            'meta_title' => '',
            'meta_description' => '',
            'content' => '',
            'content_html' => '',
            'excerpt' => '',
            'outline' => array(),
            'sections' => array(),
            'category_id' => 0,
            'category_name' => '',
            'tag_ids' => array(),
            'tag_names' => array(),
            'internal_links' => array(),
            'faq' => array(),
            'schema_data' => array(),
            'image_suggestions' => array(),
            'word_count' => 0,
            'reading_time' => '',
            'ai_writers_log' => array(),
            'created_at' => current_time( 'mysql' ),
        );

        // Load Content Orchestrator
        require_once TSA_PLUGIN_DIR . 'includes/agents/class-content-orchestrator.php';
        $this->orchestrator = new Content_Orchestrator();
    }

    /**
     * Run the writer process
     *
     * @return array Draft pack
     */
    public function run() {
        tsa_log_job( $this->job_id, 'Writer Agent V2: Starting 5-AI article creation...' );

        // Update job status
        tsa_update_job( $this->job_id, array( 'status' => 'drafting' ) );

        $title = $this->research_pack['title'];
        $content_type = $this->detect_content_type( $title );

        tsa_log_job( $this->job_id, "Writer Agent V2: Detected content type: {$content_type}" );

        // Step 1: Generate article using 5-AI Orchestrator
        $this->generate_article_with_orchestrator( $title, $content_type );

        // Step 2: Generate enhanced meta data
        $this->generate_enhanced_meta_data();

        // Step 3: Handle categories (auto-create if needed)
        $this->handle_categories();

        // Step 4: Generate tags (3-10 tags)
        $this->generate_tags();

        // Step 5: Find internal links
        $this->find_internal_links();

        // Step 6: Generate FAQ section (if not already in content)
        $this->ensure_faq_section();

        // Step 7: Generate schema data
        $this->generate_schema();

        // Step 8: Generate image suggestions
        $this->generate_image_suggestions();

        // Step 9: Convert Markdown to HTML
        $this->convert_to_html();

        tsa_log_job( $this->job_id, 'Writer Agent V2: Completed. Word count: ' . $this->draft_pack['word_count'] );

        return $this->draft_pack;
    }

    /**
     * Detect content type from title
     *
     * @param string $title Article title
     * @return string Content type
     */
    private function detect_content_type( $title ) {
        $title_lower = strtolower( $title );

        // Kuliner keywords
        $kuliner_keywords = array( 'makanan', 'kuliner', 'restoran', 'cafe', 'kafe', 'warung', 'rumah makan', 'nasi', 'mie', 'sate', 'bakso', 'soto', 'rendang', 'gudeg', 'seafood', 'minuman', 'kopi', 'es', 'jajanan' );
        foreach ( $kuliner_keywords as $kw ) {
            if ( strpos( $title_lower, $kw ) !== false ) {
                return 'kuliner';
            }
        }

        // Hotel keywords
        $hotel_keywords = array( 'hotel', 'resort', 'villa', 'penginapan', 'homestay', 'hostel', 'guest house', 'cottage', 'glamping', 'menginap' );
        foreach ( $hotel_keywords as $kw ) {
            if ( strpos( $title_lower, $kw ) !== false ) {
                return 'hotel';
            }
        }

        // Default to destinasi
        return 'destinasi';
    }

    /**
     * Generate article using 5-AI Content Orchestrator
     *
     * @param string $title        Article title
     * @param string $content_type Content type
     */
    private function generate_article_with_orchestrator( $title, $content_type ) {
        tsa_log_job( $this->job_id, 'Writer Agent V2: Initiating 5-AI Orchestrator...' );

        // Prepare research data for orchestrator
        $research_data = array(
            'facts' => $this->research_pack['key_points'] ?? array(),
            'prices' => $this->extract_prices_from_research(),
            'hours' => $this->extract_hours_from_research(),
            'facilities' => $this->extract_facilities_from_research(),
            'location' => $this->research_pack['location_info'] ?? '',
        );

        // Extract location from title or research
        $location = $this->extract_location( $title );

        // Generate article
        $result = $this->orchestrator->generate_article(
            $title,
            $content_type,
            $research_data,
            array(
                'spin_content' => $this->settings['spin_content'],
                'spin_intensity' => $this->settings['spin_intensity'],
                'preserve_keywords' => array( $title ),
                'location' => $location,
            )
        );

        if ( $result['success'] ) {
            $this->draft_pack['title'] = $title;
            $this->draft_pack['content'] = $result['article'];
            $this->draft_pack['word_count'] = $result['word_count'];
            $this->draft_pack['sections'] = $result['sections'];
            $this->draft_pack['ai_writers_log'] = $result['log'];
            $this->draft_pack['reading_time'] = $result['metadata']['reading_time'];

            // Use orchestrator's metadata
            $this->draft_pack['meta_title'] = $result['metadata']['meta_title'];
            $this->draft_pack['meta_description'] = $result['metadata']['meta_description'];
            $this->draft_pack['tag_names'] = $result['metadata']['tags'];
            $this->draft_pack['category_name'] = $result['metadata']['categories'][0] ?? 'Destinasi Wisata';

            tsa_log_job( $this->job_id, "Writer Agent V2: 5-AI Orchestrator completed. {$result['word_count']} words generated." );

            // Log each AI writer's contribution
            foreach ( $result['sections'] as $section_id => $section_data ) {
                $ai_num = $section_data['ai_writer'];
                $words = $section_data['word_count'];
                tsa_log_job( $this->job_id, "  - AI Writer #{$ai_num}: Section '{$section_id}' ({$words} words)" );
            }
        } else {
            tsa_log_job( $this->job_id, 'Writer Agent V2: Orchestrator failed, using fallback...', 'warning' );
            $this->generate_fallback_content( $title );
        }
    }

    /**
     * Extract prices from research data
     *
     * @return array
     */
    private function extract_prices_from_research() {
        $prices = array();

        if ( ! empty( $this->research_pack['pricing_info'] ) ) {
            $prices[] = $this->research_pack['pricing_info'];
        }

        // Extract from scraped content
        if ( ! empty( $this->research_pack['scraped_content'] ) ) {
            foreach ( $this->research_pack['scraped_content'] as $content ) {
                // Look for price patterns
                if ( preg_match_all( '/(?:Rp|IDR)\s*[\d.,]+/i', $content, $matches ) ) {
                    $prices = array_merge( $prices, $matches[0] );
                }
            }
        }

        return array_unique( array_slice( $prices, 0, 5 ) );
    }

    /**
     * Extract hours from research data
     *
     * @return array
     */
    private function extract_hours_from_research() {
        $hours = array();

        if ( ! empty( $this->research_pack['hours_info'] ) ) {
            $hours[] = $this->research_pack['hours_info'];
        }

        // Extract from scraped content
        if ( ! empty( $this->research_pack['scraped_content'] ) ) {
            foreach ( $this->research_pack['scraped_content'] as $content ) {
                // Look for time patterns
                if ( preg_match_all( '/\d{1,2}[:.]\d{2}\s*[-–]\s*\d{1,2}[:.]\d{2}/i', $content, $matches ) ) {
                    $hours = array_merge( $hours, $matches[0] );
                }
            }
        }

        return array_unique( array_slice( $hours, 0, 5 ) );
    }

    /**
     * Extract facilities from research data
     *
     * @return array
     */
    private function extract_facilities_from_research() {
        $facilities = array();

        // Common facility keywords
        $facility_keywords = array( 'toilet', 'mushola', 'parkir', 'wifi', 'restoran', 'warung', 'gazebo', 'kolam renang', 'playground', 'taman' );

        if ( ! empty( $this->research_pack['scraped_content'] ) ) {
            foreach ( $this->research_pack['scraped_content'] as $content ) {
                $content_lower = strtolower( $content );
                foreach ( $facility_keywords as $facility ) {
                    if ( strpos( $content_lower, $facility ) !== false ) {
                        $facilities[] = ucfirst( $facility );
                    }
                }
            }
        }

        return array_unique( $facilities );
    }

    /**
     * Extract location from title
     *
     * @param string $title Article title
     * @return string Location
     */
    private function extract_location( $title ) {
        // Common Indonesian location patterns
        $locations = array(
            'Bandung', 'Jakarta', 'Surabaya', 'Yogyakarta', 'Bali', 'Malang', 'Semarang',
            'Bogor', 'Bekasi', 'Tangerang', 'Depok', 'Medan', 'Makassar', 'Palembang',
            'Lombok', 'Labuan Bajo', 'Raja Ampat', 'Bromo', 'Dieng', 'Pangandaran',
        );

        foreach ( $locations as $location ) {
            if ( stripos( $title, $location ) !== false ) {
                return $location;
            }
        }

        return '';
    }

    /**
     * Generate fallback content (template-based)
     *
     * @param string $title Article title
     */
    private function generate_fallback_content( $title ) {
        $year = date( 'Y' );

        $content = "# {$title}\n\n";
        $content .= "*Panduan lengkap {$title} {$year}. Info jam buka, fasilitas, harga tiket, dan tips berkunjung.*\n\n";

        $content .= "## Sekilas Tentang {$title}\n\n";
        $content .= "{$title} merupakan salah satu destinasi wisata yang menarik perhatian banyak pengunjung. Tempat ini menawarkan pengalaman wisata yang unik dan berbeda dari destinasi lainnya.\n\n";

        $content .= "## Lokasi dan Cara Menuju {$title}\n\n";
        $content .= "Untuk menuju lokasi, Anda dapat menggunakan kendaraan pribadi atau transportasi umum. Gunakan aplikasi navigasi untuk panduan rute terbaik.\n\n";

        $content .= "## Harga Tiket Masuk\n\n";
        $content .= "| Kategori | Harga |\n|----------|-------|\n| Dewasa | Hubungi pengelola |\n| Anak-anak | Hubungi pengelola |\n\n";

        $content .= "## Jam Operasional\n\n";
        $content .= "Untuk informasi jam operasional terkini, silakan hubungi pihak pengelola.\n\n";

        $content .= "## Tips Berkunjung\n\n";
        $content .= "- Datanglah di pagi hari untuk menghindari keramaian\n";
        $content .= "- Bawa perlengkapan yang diperlukan\n";
        $content .= "- Patuhi peraturan yang berlaku\n\n";

        $content .= "## Kesimpulan\n\n";
        $content .= "{$title} adalah destinasi yang layak untuk dikunjungi. Segera rencanakan kunjungan Anda!\n";

        $this->draft_pack['title'] = $title;
        $this->draft_pack['content'] = $content;
        $this->draft_pack['word_count'] = str_word_count( strip_tags( $content ) );
    }

    /**
     * Generate enhanced meta data
     */
    private function generate_enhanced_meta_data() {
        $title = $this->draft_pack['title'];
        $year = date( 'Y' );

        // Generate slug
        $this->draft_pack['slug'] = sanitize_title( $title );

        // Enhance meta title if needed
        if ( empty( $this->draft_pack['meta_title'] ) ) {
            $this->draft_pack['meta_title'] = "{$title} {$year}: Panduan Lengkap & Tips";
            if ( strlen( $this->draft_pack['meta_title'] ) > 60 ) {
                $this->draft_pack['meta_title'] = substr( $this->draft_pack['meta_title'], 0, 57 ) . '...';
            }
        }

        // Enhance meta description if needed
        if ( empty( $this->draft_pack['meta_description'] ) ) {
            $this->draft_pack['meta_description'] = "Panduan lengkap {$title} {$year}. Info harga tiket, jam buka, fasilitas, dan tips berkunjung.";
            if ( strlen( $this->draft_pack['meta_description'] ) > 160 ) {
                $this->draft_pack['meta_description'] = substr( $this->draft_pack['meta_description'], 0, 157 ) . '...';
            }
        }

        // Generate excerpt
        $this->draft_pack['excerpt'] = $this->draft_pack['meta_description'];

        tsa_log_job( $this->job_id, 'Writer Agent V2: Enhanced meta data generated.' );
    }

    /**
     * Handle categories (auto-create if needed)
     */
    private function handle_categories() {
        $category_name = $this->draft_pack['category_name'] ?: 'Destinasi Wisata';

        // Check if category exists
        $term = term_exists( $category_name, 'category' );

        if ( ! $term ) {
            // Create new category
            $result = wp_insert_term( $category_name, 'category', array(
                'description' => "Artikel tentang {$category_name}",
            ) );

            if ( ! is_wp_error( $result ) ) {
                $this->draft_pack['category_id'] = $result['term_id'];
                tsa_log_job( $this->job_id, "Writer Agent V2: Created new category: {$category_name}" );
            }
        } else {
            $this->draft_pack['category_id'] = is_array( $term ) ? $term['term_id'] : $term;
        }

        $this->draft_pack['category_name'] = $category_name;
    }

    /**
     * Generate tags (3-10 tags)
     */
    private function generate_tags() {
        $title = $this->draft_pack['title'];
        $tags = $this->draft_pack['tag_names'] ?: array();

        // Ensure we have at least 3 tags
        if ( count( $tags ) < 3 ) {
            // Add from title words
            $title_words = explode( ' ', $title );
            foreach ( $title_words as $word ) {
                if ( strlen( $word ) > 3 && count( $tags ) < 10 ) {
                    $tags[] = $word;
                }
            }

            // Add common travel tags
            $common_tags = array( 'wisata', 'liburan', 'traveling', 'jalan-jalan', 'rekreasi' );
            foreach ( $common_tags as $tag ) {
                if ( count( $tags ) < 10 ) {
                    $tags[] = $tag;
                }
            }
        }

        // Limit to 10 and make unique
        $tags = array_unique( $tags );
        $tags = array_slice( $tags, 0, 10 );

        // Create tags in WordPress
        $tag_ids = array();
        foreach ( $tags as $tag_name ) {
            $term = term_exists( $tag_name, 'post_tag' );
            if ( ! $term ) {
                $result = wp_insert_term( $tag_name, 'post_tag' );
                if ( ! is_wp_error( $result ) ) {
                    $tag_ids[] = $result['term_id'];
                }
            } else {
                $tag_ids[] = is_array( $term ) ? $term['term_id'] : $term;
            }
        }

        $this->draft_pack['tag_names'] = $tags;
        $this->draft_pack['tag_ids'] = $tag_ids;

        tsa_log_job( $this->job_id, 'Writer Agent V2: Generated ' . count( $tags ) . ' tags.' );
    }

    /**
     * Find internal links
     */
    private function find_internal_links() {
        $title = $this->draft_pack['title'];
        $internal_links = array();

        // Get related posts
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            's' => $title,
            'orderby' => 'relevance',
        );

        $query = new \WP_Query( $args );

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $internal_links[] = array(
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'url' => get_permalink(),
                    'excerpt' => get_the_excerpt(),
                );
            }
            wp_reset_postdata();
        }

        $this->draft_pack['internal_links'] = $internal_links;

        tsa_log_job( $this->job_id, 'Writer Agent V2: Found ' . count( $internal_links ) . ' internal links.' );
    }

    /**
     * Ensure FAQ section exists
     */
    private function ensure_faq_section() {
        // Check if FAQ already in content
        if ( stripos( $this->draft_pack['content'], 'FAQ' ) !== false ||
             stripos( $this->draft_pack['content'], 'Pertanyaan' ) !== false ) {
            return;
        }

        // Add FAQ section
        $title = $this->draft_pack['title'];
        $faq_section = "\n\n## Pertanyaan yang Sering Diajukan (FAQ)\n\n";
        $faq_section .= "### Apakah {$title} cocok untuk anak-anak?\n";
        $faq_section .= "Ya, destinasi ini ramah anak dan cocok untuk liburan keluarga.\n\n";
        $faq_section .= "### Kapan waktu terbaik untuk berkunjung?\n";
        $faq_section .= "Waktu terbaik adalah di pagi hari atau hari kerja untuk menghindari keramaian.\n\n";
        $faq_section .= "### Apakah ada penginapan di dekat lokasi?\n";
        $faq_section .= "Ya, tersedia berbagai pilihan penginapan dengan berbagai range harga di sekitar lokasi.\n";

        $this->draft_pack['content'] .= $faq_section;

        // Store FAQ for schema
        $this->draft_pack['faq'] = array(
            array(
                'question' => "Apakah {$title} cocok untuk anak-anak?",
                'answer' => 'Ya, destinasi ini ramah anak dan cocok untuk liburan keluarga.',
            ),
            array(
                'question' => 'Kapan waktu terbaik untuk berkunjung?',
                'answer' => 'Waktu terbaik adalah di pagi hari atau hari kerja untuk menghindari keramaian.',
            ),
            array(
                'question' => 'Apakah ada penginapan di dekat lokasi?',
                'answer' => 'Ya, tersedia berbagai pilihan penginapan dengan berbagai range harga di sekitar lokasi.',
            ),
        );
    }

    /**
     * Generate schema data
     */
    private function generate_schema() {
        $title = $this->draft_pack['title'];
        $year = date( 'Y' );

        // Article schema
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $this->draft_pack['meta_title'],
            'description' => $this->draft_pack['meta_description'],
            'datePublished' => current_time( 'c' ),
            'dateModified' => current_time( 'c' ),
            'author' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo( 'name' ),
            ),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo( 'name' ),
            ),
        );

        // Add FAQ schema if available
        if ( ! empty( $this->draft_pack['faq'] ) ) {
            $faq_schema = array(
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array(),
            );

            foreach ( $this->draft_pack['faq'] as $faq ) {
                $faq_schema['mainEntity'][] = array(
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => $faq['answer'],
                    ),
                );
            }

            $this->draft_pack['schema_data']['faq'] = $faq_schema;
        }

        $this->draft_pack['schema_data']['article'] = $schema;

        tsa_log_job( $this->job_id, 'Writer Agent V2: Generated schema data.' );
    }

    /**
     * Generate image suggestions
     */
    private function generate_image_suggestions() {
        $title = $this->draft_pack['title'];
        $suggestions = array();

        // Suggest images for each major section
        $sections = array(
            'header' => "Foto utama {$title} - pemandangan terbaik",
            'overview' => "Panorama {$title} dari kejauhan",
            'location' => "Peta lokasi atau foto pintu masuk {$title}",
            'activities' => "Aktivitas wisatawan di {$title}",
            'facilities' => "Fasilitas yang tersedia di {$title}",
            'tips' => "Infografis tips berkunjung ke {$title}",
        );

        foreach ( $sections as $section => $suggestion ) {
            $suggestions[] = array(
                'section' => $section,
                'suggestion' => $suggestion,
                'search_query' => "{$title} {$section}",
            );
        }

        $this->draft_pack['image_suggestions'] = $suggestions;

        tsa_log_job( $this->job_id, 'Writer Agent V2: Generated ' . count( $suggestions ) . ' image suggestions.' );
    }

    /**
     * Convert Markdown content to HTML
     */
    private function convert_to_html() {
        $content = $this->draft_pack['content'];

        // Simple Markdown to HTML conversion
        // Headers
        $content = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $content );
        $content = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $content );
        $content = preg_replace( '/^# (.+)$/m', '<h1>$1</h1>', $content );

        // Bold and italic
        $content = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content );
        $content = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $content );

        // Lists
        $content = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $content );
        $content = preg_replace( '/(<li>.*<\/li>\n)+/', '<ul>$0</ul>', $content );

        // Horizontal rules
        $content = preg_replace( '/^---$/m', '<hr>', $content );

        // Tables (basic support)
        $content = $this->convert_markdown_tables( $content );

        // Paragraphs
        $content = preg_replace( '/^([^<\n].+)$/m', '<p>$1</p>', $content );

        // Clean up
        $content = preg_replace( '/<p><\/p>/', '', $content );
        $content = preg_replace( '/\n{3,}/', "\n\n", $content );

        $this->draft_pack['content_html'] = $content;
    }

    /**
     * Convert Markdown tables to HTML
     *
     * @param string $content Content with Markdown tables
     * @return string Content with HTML tables
     */
    private function convert_markdown_tables( $content ) {
        // Match Markdown tables
        $pattern = '/\|(.+)\|\n\|[-:| ]+\|\n((?:\|.+\|\n?)+)/';

        return preg_replace_callback( $pattern, function( $matches ) {
            $header = trim( $matches[1] );
            $rows = trim( $matches[2] );

            // Parse header
            $header_cells = array_map( 'trim', explode( '|', $header ) );
            $header_html = '<tr>';
            foreach ( $header_cells as $cell ) {
                if ( ! empty( $cell ) ) {
                    $header_html .= '<th>' . esc_html( $cell ) . '</th>';
                }
            }
            $header_html .= '</tr>';

            // Parse rows
            $rows_html = '';
            $row_lines = explode( "\n", $rows );
            foreach ( $row_lines as $row ) {
                $row = trim( $row, '|' );
                $cells = array_map( 'trim', explode( '|', $row ) );
                $rows_html .= '<tr>';
                foreach ( $cells as $cell ) {
                    $rows_html .= '<td>' . esc_html( $cell ) . '</td>';
                }
                $rows_html .= '</tr>';
            }

            return '<table class="tsa-article-table"><thead>' . $header_html . '</thead><tbody>' . $rows_html . '</tbody></table>';
        }, $content );
    }

    /**
     * Check if AI API is available
     *
     * @return bool
     */
    private function has_ai_api() {
        $settings = get_option( 'tsa_settings', array() );
        return ! empty( $settings['openai_api_key'] ) || ! empty( $settings['deepseek_api_key'] );
    }
}
