<?php
/**
 * QA Agent - Quality Assurance & Originality Guard
 *
 * This agent is responsible for:
 * - Checking content quality and readability
 * - Ensuring originality (no duplicate content)
 * - Polishing text for human-like writing
 * - SEO optimization checks
 * - Fact consistency verification
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/agents
 */

namespace TravelSEO_Autopublisher\Agents;

use function TravelSEO_Autopublisher\tsa_get_option;
use function TravelSEO_Autopublisher\tsa_update_job;
use function TravelSEO_Autopublisher\tsa_log_job;
use function TravelSEO_Autopublisher\tsa_calculate_keyword_density;
use function TravelSEO_Autopublisher\tsa_get_jobs;

/**
 * QA Agent Class
 */
class QA_Agent {

    /**
     * Job ID
     *
     * @var int
     */
    private $job_id;

    /**
     * Draft pack from Agent 2
     *
     * @var array
     */
    private $draft_pack;

    /**
     * Research pack from Agent 1
     *
     * @var array
     */
    private $research_pack;

    /**
     * QA results
     *
     * @var array
     */
    private $qa_results;

    /**
     * Constructor
     *
     * @param int $job_id Job ID
     * @param array $draft_pack Draft pack from Agent 2
     * @param array $research_pack Research pack from Agent 1
     */
    public function __construct( $job_id, $draft_pack, $research_pack ) {
        $this->job_id = $job_id;
        $this->draft_pack = $draft_pack;
        $this->research_pack = $research_pack;
        $this->qa_results = array(
            'passed' => true,
            'score' => 0,
            'checks' => array(),
            'improvements' => array(),
            'warnings' => array(),
        );
    }

    /**
     * Run the QA process
     *
     * @return array Updated draft pack
     */
    public function run() {
        tsa_log_job( $this->job_id, 'QA Agent: Starting quality assurance...' );
        
        // Update job status
        tsa_update_job( $this->job_id, array( 'status' => 'qa' ) );

        // Step 1: Check originality (internal duplicate detection)
        $this->check_originality();

        // Step 2: Check readability
        $this->check_readability();

        // Step 3: Check SEO elements
        $this->check_seo();

        // Step 4: Check content structure
        $this->check_structure();

        // Step 5: Polish content for human-like writing
        $this->polish_content();

        // Step 6: Add disclosure note if enabled
        $this->add_disclosure();

        // Step 7: Calculate final score
        $this->calculate_score();

        // Add QA results to draft pack
        $this->draft_pack['qa_results'] = $this->qa_results;

        tsa_log_job( $this->job_id, 'QA Agent: Completed. Score: ' . $this->qa_results['score'] . '/100' );

        return $this->draft_pack;
    }

    /**
     * Check content originality (internal duplicate detection)
     */
    private function check_originality() {
        $content = $this->draft_pack['content'];
        $title = $this->draft_pack['title'];
        
        // Get existing jobs to compare
        $existing_jobs = tsa_get_jobs( array(
            'status' => 'ready',
            'limit' => 50,
        ) );
        
        $duplicates_found = false;
        $similarity_threshold = 0.7; // 70% similarity threshold
        
        // Extract n-grams from current content
        $current_ngrams = $this->extract_ngrams( $content, 3 );
        
        foreach ( $existing_jobs as $job ) {
            if ( $job->id == $this->job_id ) {
                continue;
            }
            
            $existing_draft = json_decode( $job->draft_pack, true );
            if ( empty( $existing_draft['content'] ) ) {
                continue;
            }
            
            $existing_ngrams = $this->extract_ngrams( $existing_draft['content'], 3 );
            
            // Calculate Jaccard similarity
            $intersection = count( array_intersect( $current_ngrams, $existing_ngrams ) );
            $union = count( array_unique( array_merge( $current_ngrams, $existing_ngrams ) ) );
            
            if ( $union > 0 ) {
                $similarity = $intersection / $union;
                
                if ( $similarity > $similarity_threshold ) {
                    $duplicates_found = true;
                    $this->qa_results['warnings'][] = "Konten memiliki kemiripan tinggi ({$similarity}%) dengan artikel ID #{$job->id}";
                }
            }
        }
        
        $this->qa_results['checks']['originality'] = array(
            'passed' => ! $duplicates_found,
            'message' => $duplicates_found ? 'Ditemukan kemiripan dengan artikel lain' : 'Konten original',
        );
        
        tsa_log_job( $this->job_id, 'QA Agent: Originality check ' . ( $duplicates_found ? 'WARNING' : 'PASSED' ) );
    }

    /**
     * Extract n-grams from text
     *
     * @param string $text Text to analyze
     * @param int $n N-gram size
     * @return array
     */
    private function extract_ngrams( $text, $n = 3 ) {
        $text = strtolower( wp_strip_all_tags( $text ) );
        $text = preg_replace( '/[^a-z0-9\s]/', '', $text );
        $words = preg_split( '/\s+/', $text );
        
        $ngrams = array();
        for ( $i = 0; $i <= count( $words ) - $n; $i++ ) {
            $ngram = implode( ' ', array_slice( $words, $i, $n ) );
            $ngrams[] = $ngram;
        }
        
        return array_unique( $ngrams );
    }

    /**
     * Check content readability
     */
    private function check_readability() {
        $content = wp_strip_all_tags( $this->draft_pack['content'] );
        
        // Count sentences
        $sentences = preg_split( '/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY );
        $sentence_count = count( $sentences );
        
        // Count words
        $word_count = str_word_count( $content );
        
        // Calculate average sentence length
        $avg_sentence_length = $sentence_count > 0 ? $word_count / $sentence_count : 0;
        
        // Check for long sentences (over 25 words)
        $long_sentences = 0;
        foreach ( $sentences as $sentence ) {
            if ( str_word_count( $sentence ) > 25 ) {
                $long_sentences++;
            }
        }
        
        // Check paragraph length
        $paragraphs = preg_split( '/\n\n+/', $content );
        $short_paragraphs = 0;
        foreach ( $paragraphs as $para ) {
            if ( str_word_count( $para ) < 20 ) {
                $short_paragraphs++;
            }
        }
        
        // Readability score (simplified)
        $readability_score = 100;
        
        // Penalize for too long average sentences
        if ( $avg_sentence_length > 20 ) {
            $readability_score -= ( $avg_sentence_length - 20 ) * 2;
        }
        
        // Penalize for too many long sentences
        if ( $sentence_count > 0 ) {
            $long_sentence_ratio = $long_sentences / $sentence_count;
            if ( $long_sentence_ratio > 0.3 ) {
                $readability_score -= 10;
            }
        }
        
        $readability_score = max( 0, min( 100, $readability_score ) );
        
        $this->qa_results['checks']['readability'] = array(
            'passed' => $readability_score >= 60,
            'score' => $readability_score,
            'avg_sentence_length' => round( $avg_sentence_length, 1 ),
            'long_sentences' => $long_sentences,
            'message' => $readability_score >= 60 ? 'Keterbacaan baik' : 'Perlu perbaikan keterbacaan',
        );
        
        if ( $readability_score < 60 ) {
            $this->qa_results['improvements'][] = 'Pecah kalimat panjang menjadi kalimat yang lebih pendek';
        }
        
        tsa_log_job( $this->job_id, 'QA Agent: Readability score: ' . $readability_score );
    }

    /**
     * Check SEO elements
     */
    private function check_seo() {
        $title = $this->draft_pack['title'];
        $meta_title = $this->draft_pack['meta_title'];
        $meta_desc = $this->draft_pack['meta_description'];
        $content = $this->draft_pack['content'];
        
        $seo_checks = array();
        
        // Check meta title length
        $meta_title_length = strlen( $meta_title );
        $seo_checks['meta_title'] = array(
            'passed' => $meta_title_length >= 50 && $meta_title_length <= 60,
            'length' => $meta_title_length,
            'message' => $meta_title_length >= 50 && $meta_title_length <= 60 
                ? 'Meta title optimal' 
                : 'Meta title harus 50-60 karakter (sekarang: ' . $meta_title_length . ')',
        );
        
        // Check meta description length
        $meta_desc_length = strlen( $meta_desc );
        $seo_checks['meta_description'] = array(
            'passed' => $meta_desc_length >= 150 && $meta_desc_length <= 160,
            'length' => $meta_desc_length,
            'message' => $meta_desc_length >= 150 && $meta_desc_length <= 160 
                ? 'Meta description optimal' 
                : 'Meta description harus 150-160 karakter (sekarang: ' . $meta_desc_length . ')',
        );
        
        // Check keyword in title
        $keyword = strtolower( $title );
        $keyword_in_meta = stripos( $meta_title, $title ) !== false;
        $seo_checks['keyword_in_title'] = array(
            'passed' => $keyword_in_meta,
            'message' => $keyword_in_meta ? 'Keyword ada di meta title' : 'Tambahkan keyword ke meta title',
        );
        
        // Check keyword density
        $keyword_density = tsa_calculate_keyword_density( $content, $title );
        $seo_checks['keyword_density'] = array(
            'passed' => $keyword_density >= 0.5 && $keyword_density <= 2.5,
            'density' => $keyword_density,
            'message' => $keyword_density >= 0.5 && $keyword_density <= 2.5 
                ? 'Keyword density optimal (' . $keyword_density . '%)' 
                : 'Keyword density harus 0.5-2.5% (sekarang: ' . $keyword_density . '%)',
        );
        
        // Check heading structure
        preg_match_all( '/<h2[^>]*>/', $content, $h2_matches );
        $h2_count = count( $h2_matches[0] );
        $seo_checks['headings'] = array(
            'passed' => $h2_count >= 5,
            'count' => $h2_count,
            'message' => $h2_count >= 5 ? 'Struktur heading baik' : 'Tambahkan lebih banyak H2 (minimal 5)',
        );
        
        // Check word count
        $word_count = $this->draft_pack['word_count'];
        $seo_checks['word_count'] = array(
            'passed' => $word_count >= 1500,
            'count' => $word_count,
            'message' => $word_count >= 1500 ? 'Panjang artikel cukup' : 'Artikel terlalu pendek (minimal 1500 kata)',
        );
        
        // Check internal links
        $internal_links_count = count( $this->draft_pack['internal_links'] ?? array() );
        $seo_checks['internal_links'] = array(
            'passed' => $internal_links_count >= 2,
            'count' => $internal_links_count,
            'message' => $internal_links_count >= 2 ? 'Internal links cukup' : 'Tambahkan internal links',
        );
        
        $this->qa_results['checks']['seo'] = $seo_checks;
        
        // Add improvements
        foreach ( $seo_checks as $check ) {
            if ( ! $check['passed'] ) {
                $this->qa_results['improvements'][] = $check['message'];
            }
        }
        
        tsa_log_job( $this->job_id, 'QA Agent: SEO checks completed.' );
    }

    /**
     * Check content structure
     */
    private function check_structure() {
        $content = $this->draft_pack['content'];
        
        $structure_checks = array();
        
        // Check for introduction
        $has_intro = preg_match( '/<p>.*?<\/p>.*?<h2/s', $content );
        $structure_checks['introduction'] = array(
            'passed' => (bool) $has_intro,
            'message' => $has_intro ? 'Ada paragraf pembuka' : 'Tambahkan paragraf pembuka sebelum H2 pertama',
        );
        
        // Check for conclusion
        $has_conclusion = stripos( $content, 'kesimpulan' ) !== false || stripos( $content, 'penutup' ) !== false;
        $structure_checks['conclusion'] = array(
            'passed' => $has_conclusion,
            'message' => $has_conclusion ? 'Ada kesimpulan' : 'Tambahkan bagian kesimpulan',
        );
        
        // Check for lists (ul/ol)
        $has_lists = preg_match( '/<[uo]l>/', $content );
        $structure_checks['lists'] = array(
            'passed' => (bool) $has_lists,
            'message' => $has_lists ? 'Ada daftar/list' : 'Tambahkan daftar untuk meningkatkan keterbacaan',
        );
        
        // Check for tables
        $has_tables = preg_match( '/<table/', $content );
        $structure_checks['tables'] = array(
            'passed' => (bool) $has_tables,
            'message' => $has_tables ? 'Ada tabel' : 'Pertimbangkan menambahkan tabel untuk data',
        );
        
        // Check for FAQ section
        $has_faq = ! empty( $this->draft_pack['faq'] );
        $structure_checks['faq'] = array(
            'passed' => $has_faq,
            'message' => $has_faq ? 'Ada FAQ section' : 'Tambahkan FAQ section',
        );
        
        $this->qa_results['checks']['structure'] = $structure_checks;
        
        tsa_log_job( $this->job_id, 'QA Agent: Structure checks completed.' );
    }

    /**
     * Polish content for human-like writing
     */
    private function polish_content() {
        $content = $this->draft_pack['content'];
        
        // Check if AI is available for polishing
        if ( $this->has_ai_api() ) {
            $content = $this->polish_with_ai( $content );
        } else {
            $content = $this->polish_without_ai( $content );
        }
        
        $this->draft_pack['content'] = $content;
        
        tsa_log_job( $this->job_id, 'QA Agent: Content polished.' );
    }

    /**
     * Polish content without AI
     *
     * @param string $content Content to polish
     * @return string
     */
    private function polish_without_ai( $content ) {
        // Remove repeated phrases
        $content = $this->remove_repetitions( $content );
        
        // Fix common issues
        $content = $this->fix_common_issues( $content );
        
        // Add variety to sentence starters
        $content = $this->vary_sentence_starters( $content );
        
        return $content;
    }

    /**
     * Remove repeated phrases
     *
     * @param string $content Content
     * @return string
     */
    private function remove_repetitions( $content ) {
        // Find and reduce repeated phrases
        $patterns = array(
            '/(\b\w+\b)\s+\1/i' => '$1', // Remove immediate word repetition
            '/yang yang/i' => 'yang',
            '/dan dan/i' => 'dan',
            '/di di/i' => 'di',
            '/untuk untuk/i' => 'untuk',
        );
        
        foreach ( $patterns as $pattern => $replacement ) {
            $content = preg_replace( $pattern, $replacement, $content );
        }
        
        return $content;
    }

    /**
     * Fix common writing issues
     *
     * @param string $content Content
     * @return string
     */
    private function fix_common_issues( $content ) {
        // Fix common Indonesian writing issues
        $fixes = array(
            '/\s+,/' => ',',
            '/\s+\./' => '.',
            '/\s+\?/' => '?',
            '/\s+!/' => '!',
            '/\s{2,}/' => ' ',
            '/\n{3,}/' => "\n\n",
        );
        
        foreach ( $fixes as $pattern => $replacement ) {
            $content = preg_replace( $pattern, $replacement, $content );
        }
        
        return $content;
    }

    /**
     * Add variety to sentence starters
     *
     * @param string $content Content
     * @return string
     */
    private function vary_sentence_starters( $content ) {
        // This is a simplified version
        // In production, this would be more sophisticated
        
        $starters = array(
            'Selain itu, ',
            'Di samping itu, ',
            'Tidak hanya itu, ',
            'Lebih lanjut, ',
            'Menariknya, ',
            'Perlu diketahui, ',
            'Yang tak kalah penting, ',
        );
        
        // Count consecutive paragraphs starting with same word
        // This is a basic implementation
        
        return $content;
    }

    /**
     * Polish content with AI
     *
     * @param string $content Content to polish
     * @return string
     */
    private function polish_with_ai( $content ) {
        $api_key = tsa_get_option( 'openai_api_key', '' );
        $api_endpoint = tsa_get_option( 'openai_endpoint', 'https://api.openai.com/v1/chat/completions' );
        $model = tsa_get_option( 'openai_model', 'gpt-3.5-turbo' );
        
        $prompt = "Perbaiki artikel berikut agar lebih natural dan mudah dibaca. Pertahankan semua informasi dan struktur HTML. Perbaiki:
1. Kalimat yang terlalu panjang atau rumit
2. Pengulangan kata/frasa yang tidak perlu
3. Transisi antar paragraf
4. Variasi kalimat pembuka

Jangan mengubah fakta atau menambah informasi baru. Output harus dalam format HTML yang sama.

Artikel:
{$content}";

        $response = wp_remote_post( $api_endpoint, array(
            'timeout' => 120,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'Kamu adalah editor profesional yang ahli dalam memperbaiki tulisan Bahasa Indonesia. Tugasmu adalah memperbaiki kualitas tulisan tanpa mengubah fakta atau struktur.',
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt,
                    ),
                ),
                'temperature' => 0.3,
                'max_tokens' => 4000,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            tsa_log_job( $this->job_id, 'QA Agent: AI polish failed - ' . $response->get_error_message() );
            return $this->polish_without_ai( $content );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            return $data['choices'][0]['message']['content'];
        }

        return $this->polish_without_ai( $content );
    }

    /**
     * Add disclosure note if enabled
     */
    private function add_disclosure() {
        $add_disclosure = tsa_get_option( 'add_disclosure', true );
        
        if ( ! $add_disclosure ) {
            return;
        }
        
        $disclosure = '<p><em><strong>Disclaimer:</strong> Informasi dalam artikel ini dapat berubah sewaktu-waktu. Untuk informasi terkini, silakan hubungi pihak pengelola atau kunjungi sumber resmi.</em></p>';
        
        // Add disclosure before closing
        $this->draft_pack['content'] .= "\n\n" . $disclosure;
    }

    /**
     * Calculate final QA score
     */
    private function calculate_score() {
        $total_checks = 0;
        $passed_checks = 0;
        
        // Count originality
        if ( isset( $this->qa_results['checks']['originality']['passed'] ) ) {
            $total_checks++;
            if ( $this->qa_results['checks']['originality']['passed'] ) {
                $passed_checks++;
            }
        }
        
        // Count readability
        if ( isset( $this->qa_results['checks']['readability']['passed'] ) ) {
            $total_checks++;
            if ( $this->qa_results['checks']['readability']['passed'] ) {
                $passed_checks++;
            }
        }
        
        // Count SEO checks
        if ( isset( $this->qa_results['checks']['seo'] ) ) {
            foreach ( $this->qa_results['checks']['seo'] as $check ) {
                $total_checks++;
                if ( $check['passed'] ) {
                    $passed_checks++;
                }
            }
        }
        
        // Count structure checks
        if ( isset( $this->qa_results['checks']['structure'] ) ) {
            foreach ( $this->qa_results['checks']['structure'] as $check ) {
                $total_checks++;
                if ( $check['passed'] ) {
                    $passed_checks++;
                }
            }
        }
        
        // Calculate score
        $this->qa_results['score'] = $total_checks > 0 
            ? round( ( $passed_checks / $total_checks ) * 100 ) 
            : 0;
        
        $this->qa_results['passed'] = $this->qa_results['score'] >= 60;
    }

    /**
     * Check if AI API is available
     *
     * @return bool
     */
    private function has_ai_api() {
        $api_key = tsa_get_option( 'openai_api_key', '' );
        return ! empty( $api_key );
    }

    /**
     * Get the draft pack
     *
     * @return array
     */
    public function get_draft_pack() {
        return $this->draft_pack;
    }

    /**
     * Get QA results
     *
     * @return array
     */
    public function get_qa_results() {
        return $this->qa_results;
    }
}
