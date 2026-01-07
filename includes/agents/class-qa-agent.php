<?php
/**
 * QA Agent V3 - Quality Assurance and Content Polish
 *
 * Features:
 * - Professional Indonesian text spinning
 * - Human-like writing check
 * - Plagiarism avoidance
 * - SEO optimization check
 * - Grammar and readability improvement
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/agents
 * @version    3.0.0
 */

namespace TravelSEO_Autopublisher\Agents;

use TravelSEO_Autopublisher\Spinner\Spinner;

use function TravelSEO_Autopublisher\tsa_get_option;
use function TravelSEO_Autopublisher\tsa_update_job;
use function TravelSEO_Autopublisher\tsa_log_job;

/**
 * QA Agent V3 Class
 */
class QA_Agent {

    /**
     * Job ID
     */
    private $job_id;

    /**
     * Draft pack from Writer Agent
     */
    private $draft_pack;

    /**
     * Research pack from Research Agent
     */
    private $research_pack;

    /**
     * Job settings
     */
    private $settings;

    /**
     * QA results
     */
    private $qa_results;

    /**
     * Spinner instance
     */
    private $spinner;

    /**
     * Constructor
     */
    public function __construct( $job_id, $draft_pack, $research_pack = array() ) {
        $this->job_id = $job_id;
        $this->draft_pack = $draft_pack;
        $this->research_pack = $research_pack;
        $this->settings = array(
            'spin_content' => tsa_get_option( 'spin_content', true ),
            'spin_intensity' => intval( tsa_get_option( 'spin_intensity', 40 ) ),
            'check_readability' => true,
            'optimize_seo' => true,
        );

        // Initialize QA results
        $this->qa_results = array(
            'passed' => true,
            'score' => 0,
            'readability_score' => 0,
            'seo_score' => 0,
            'spin_applied' => false,
            'spin_percentage' => 0,
            'checks' => array(),
            'improvements' => array(),
            'warnings' => array(),
            'issues_fixed' => array(),
        );

        // Load Spinner
        if ( file_exists( TSA_PLUGIN_DIR . 'includes/spinner/class-spinner.php' ) ) {
            require_once TSA_PLUGIN_DIR . 'includes/spinner/class-spinner.php';
            $this->spinner = new Spinner();
        }
    }

    /**
     * Run the QA process
     */
    public function run() {
        tsa_log_job( $this->job_id, 'QA Agent V3: Memulai quality assurance...' );
        tsa_update_job( $this->job_id, array( 'status' => 'qa' ) );

        $content = $this->draft_pack['content'] ?? '';

        // Step 1: Fix common issues
        tsa_log_job( $this->job_id, 'QA Agent V3: Memperbaiki masalah umum...' );
        $content = $this->fix_common_issues( $content );

        // Step 2: Apply spinning if enabled
        if ( $this->settings['spin_content'] && $this->spinner ) {
            tsa_log_job( $this->job_id, 'QA Agent V3: Menerapkan spinning untuk naturalness...' );
            $content = $this->apply_spinning( $content );
        }

        // Step 3: Check and improve readability
        tsa_log_job( $this->job_id, 'QA Agent V3: Memeriksa readability...' );
        $content = $this->improve_readability( $content );

        // Step 4: Optimize SEO
        tsa_log_job( $this->job_id, 'QA Agent V3: Optimasi SEO...' );
        $content = $this->optimize_seo( $content );

        // Step 5: Final polish
        tsa_log_job( $this->job_id, 'QA Agent V3: Final polish...' );
        $content = $this->final_polish( $content );

        // Step 6: Calculate scores
        $this->calculate_scores( $content );

        // Update draft pack
        $this->draft_pack['content'] = $content;
        $this->draft_pack['word_count'] = str_word_count( strip_tags( $content ) );
        $this->draft_pack['qa_results'] = $this->qa_results;

        // Convert to HTML
        $this->draft_pack['content_html'] = $this->convert_to_html( $content );

        tsa_log_job( $this->job_id, 'QA Agent V3: Selesai. Score: ' . $this->qa_results['score'] . '/100' );

        return $this->draft_pack;
    }

    /**
     * Fix common issues in content
     */
    private function fix_common_issues( $content ) {
        $issues_fixed = array();

        // Fix double spaces
        $before = $content;
        $content = preg_replace( '/[ \t]+/', ' ', $content );
        $content = preg_replace( '/\n\s*\n\s*\n+/', "\n\n", $content );
        if ( $before !== $content ) {
            $issues_fixed[] = 'Memperbaiki spasi berlebihan';
        }

        // Fix punctuation spacing
        $before = $content;
        $content = preg_replace( '/\s+([.,!?;:])/', '$1', $content );
        $content = preg_replace( '/([.,!?;:])([A-Za-z])/', '$1 $2', $content );
        if ( $before !== $content ) {
            $issues_fixed[] = 'Memperbaiki spasi tanda baca';
        }

        // Fix capitalization after periods
        $before = $content;
        $content = preg_replace_callback( '/\.\s+([a-z])/', function( $matches ) {
            return '. ' . strtoupper( $matches[1] );
        }, $content );
        if ( $before !== $content ) {
            $issues_fixed[] = 'Memperbaiki kapitalisasi';
        }

        // Remove AI-like phrases
        $ai_phrases = array(
            'Tentu saja,',
            'Tentu,',
            'Baiklah,',
            'Sebagai AI,',
            'Sebagai asisten,',
            'Saya akan membantu',
            'Mari kita bahas',
            'Tidak diragukan lagi,',
            'Dengan senang hati,',
            'Berikut adalah',
        );
        
        foreach ( $ai_phrases as $phrase ) {
            if ( stripos( $content, $phrase ) !== false ) {
                $content = str_ireplace( $phrase, '', $content );
                $issues_fixed[] = 'Menghapus frasa AI: ' . $phrase;
            }
        }

        // Fix repeated words
        $repeated_patterns = array(
            '/(\b\w+\b)\s+\1\b/i' => '$1',
            '/yang yang/i' => 'yang',
            '/dan dan/i' => 'dan',
            '/di di/i' => 'di',
            '/untuk untuk/i' => 'untuk',
            '/dengan dengan/i' => 'dengan',
        );
        
        foreach ( $repeated_patterns as $pattern => $replacement ) {
            $before = $content;
            $content = preg_replace( $pattern, $replacement, $content );
            if ( $before !== $content ) {
                $issues_fixed[] = 'Memperbaiki kata berulang';
            }
        }

        // Ensure brand mention
        if ( stripos( $content, 'sekali.id' ) === false ) {
            $content = preg_replace(
                '/^([^.]+\.)/',
                'sekali.id menyajikan informasi lengkap tentang $1',
                $content,
                1
            );
            $issues_fixed[] = 'Menambahkan brand mention sekali.id';
        }

        $this->qa_results['issues_fixed'] = array_merge( $this->qa_results['issues_fixed'], $issues_fixed );

        return $content;
    }

    /**
     * Apply text spinning for naturalness
     */
    private function apply_spinning( $content ) {
        $title = $this->draft_pack['title'] ?? '';
        
        // Preserve keywords
        $preserve = array( $title, 'sekali.id', 'Indonesia' );
        
        // Extract important keywords from title
        $title_words = explode( ' ', $title );
        foreach ( $title_words as $word ) {
            if ( strlen( $word ) > 4 ) {
                $preserve[] = $word;
            }
        }

        // Apply spinning
        $spun_content = $this->spinner->spin(
            $content,
            $this->settings['spin_intensity'],
            $preserve
        );

        // Calculate spin percentage
        $original_words = str_word_count( $content );
        $changed_words = 0;
        
        $original_arr = explode( ' ', strtolower( $content ) );
        $spun_arr = explode( ' ', strtolower( $spun_content ) );
        
        for ( $i = 0; $i < min( count( $original_arr ), count( $spun_arr ) ); $i++ ) {
            if ( isset( $original_arr[ $i ] ) && isset( $spun_arr[ $i ] ) ) {
                if ( $original_arr[ $i ] !== $spun_arr[ $i ] ) {
                    $changed_words++;
                }
            }
        }

        $spin_percentage = $original_words > 0 ? round( ( $changed_words / $original_words ) * 100, 1 ) : 0;

        $this->qa_results['spin_applied'] = true;
        $this->qa_results['spin_percentage'] = $spin_percentage;

        tsa_log_job( $this->job_id, "QA Agent V3: Spinning diterapkan ({$spin_percentage}% kata diubah)" );

        return $spun_content;
    }

    /**
     * Improve readability
     */
    private function improve_readability( $content ) {
        // Break very long sentences (over 200 chars)
        $content = preg_replace_callback( '/([^.!?]{200,})[.!?]/', function( $matches ) {
            $sentence = $matches[1];
            $break_points = array( ', dan ', ', serta ', ', namun ', ', tetapi ', ', karena ', ', sehingga ', ', yang ' );
            
            foreach ( $break_points as $bp ) {
                $pos = strpos( $sentence, $bp );
                if ( $pos !== false && $pos > 50 ) {
                    $sentence = substr( $sentence, 0, $pos + strlen( $bp ) - 1 ) . '. ' . ucfirst( substr( $sentence, $pos + strlen( $bp ) ) );
                    break;
                }
            }
            
            return $sentence . '.';
        }, $content );

        // Add transition words to some paragraphs
        $paragraphs = explode( "\n\n", $content );
        $transitions = array(
            'Selain itu, ',
            'Di samping itu, ',
            'Lebih lanjut, ',
            'Perlu diketahui bahwa ',
            'Menariknya, ',
            'Yang tidak kalah penting, ',
            'Tak hanya itu, ',
        );

        for ( $i = 2; $i < count( $paragraphs ); $i++ ) {
            if ( $i % 4 === 0 && ! empty( $paragraphs[ $i ] ) ) {
                $first_char = substr( trim( $paragraphs[ $i ] ), 0, 1 );
                if ( ctype_upper( $first_char ) && strpos( $paragraphs[ $i ], '##' ) === false ) {
                    $transition = $transitions[ array_rand( $transitions ) ];
                    $paragraphs[ $i ] = $transition . lcfirst( trim( $paragraphs[ $i ] ) );
                }
            }
        }

        return implode( "\n\n", $paragraphs );
    }

    /**
     * Optimize SEO
     */
    private function optimize_seo( $content ) {
        $title = $this->draft_pack['title'] ?? '';
        $keyword = strtolower( $title );

        // Check keyword density
        $content_lower = strtolower( $content );
        $word_count = str_word_count( $content );
        $keyword_count = substr_count( $content_lower, $keyword );
        $keyword_density = $word_count > 0 ? ( $keyword_count / $word_count ) * 100 : 0;

        // If keyword density is too low, add keyword naturally
        if ( $keyword_density < 0.5 && $keyword_count < 3 ) {
            $additions = array(
                "\n\nBagi Anda yang tertarik mengunjungi {$title}, informasi di atas semoga dapat membantu perencanaan perjalanan Anda.",
                "\n\n{$title} memang layak untuk dikunjungi dan menjadi salah satu destinasi favorit wisatawan.",
            );
            
            $content .= $additions[ array_rand( $additions ) ];
            $this->qa_results['improvements'][] = 'Menambahkan keyword untuk SEO';
        }

        return $content;
    }

    /**
     * Final polish
     */
    private function final_polish( $content ) {
        // Ensure proper paragraph breaks
        $content = preg_replace( '/([.!?])\s*\n\s*([A-Z])/', "$1\n\n$2", $content );

        // Remove weird characters
        $content = preg_replace( '/[^\x20-\x7E\x{00A0}-\x{FFFF}\n#*|\-_\[\]()]/u', '', $content );

        // Ensure content ends properly
        $content = trim( $content );
        if ( ! preg_match( '/[.!?*]$/', $content ) ) {
            $content .= '.';
        }

        // Add disclaimer if not present
        if ( stripos( $content, 'Disclaimer' ) === false && stripos( $content, 'dapat berubah' ) === false ) {
            $content .= "\n\n*Disclaimer: Informasi dalam artikel ini dapat berubah sewaktu-waktu. Untuk informasi terkini, silakan hubungi pihak pengelola atau kunjungi sumber resmi.*";
        }

        return $content;
    }

    /**
     * Calculate quality scores
     */
    private function calculate_scores( $content ) {
        // Readability score
        $readability = 50;

        $word_count = str_word_count( strip_tags( $content ) );
        if ( $word_count >= 800 && $word_count <= 2000 ) {
            $readability += 15;
        } elseif ( $word_count >= 500 ) {
            $readability += 10;
        }

        $paragraphs = explode( "\n\n", $content );
        $avg_para_length = array_sum( array_map( 'str_word_count', $paragraphs ) ) / max( count( $paragraphs ), 1 );
        if ( $avg_para_length >= 30 && $avg_para_length <= 100 ) {
            $readability += 15;
        }

        if ( preg_match_all( '/^##\s/m', $content ) >= 2 ) {
            $readability += 10;
        }

        if ( strpos( $content, '- ' ) !== false ) {
            $readability += 5;
        }

        if ( strpos( $content, '|' ) !== false ) {
            $readability += 5;
        }

        $this->qa_results['readability_score'] = min( $readability, 100 );

        // SEO score
        $seo = 50;
        $title = strtolower( $this->draft_pack['title'] ?? '' );

        if ( stripos( $content, $title ) !== false ) {
            $seo += 20;
        }

        $keyword_count = substr_count( strtolower( $content ), $title );
        $keyword_density = $word_count > 0 ? ( $keyword_count / $word_count ) * 100 : 0;
        if ( $keyword_density >= 0.5 && $keyword_density <= 2.5 ) {
            $seo += 15;
        }

        if ( preg_match( '/\[.+\]\(.+\)/', $content ) ) {
            $seo += 10;
        }

        if ( ! empty( $this->draft_pack['meta_description'] ) ) {
            $seo += 5;
        }

        $this->qa_results['seo_score'] = min( $seo, 100 );

        // Overall score
        $this->qa_results['score'] = round( ( $this->qa_results['readability_score'] + $this->qa_results['seo_score'] ) / 2 );
        $this->qa_results['passed'] = $this->qa_results['score'] >= 60;

        // Add checks
        $this->qa_results['checks'] = array(
            'readability' => array(
                'passed' => $this->qa_results['readability_score'] >= 60,
                'score' => $this->qa_results['readability_score'],
                'message' => $this->qa_results['readability_score'] >= 60 ? 'Keterbacaan baik' : 'Perlu perbaikan',
            ),
            'seo' => array(
                'passed' => $this->qa_results['seo_score'] >= 60,
                'score' => $this->qa_results['seo_score'],
                'message' => $this->qa_results['seo_score'] >= 60 ? 'SEO optimal' : 'Perlu optimasi SEO',
            ),
            'word_count' => array(
                'passed' => $word_count >= 700,
                'count' => $word_count,
                'message' => $word_count >= 700 ? 'Panjang artikel cukup' : 'Artikel terlalu pendek',
            ),
        );
    }

    /**
     * Convert Markdown to HTML
     */
    private function convert_to_html( $content ) {
        // Headers
        $content = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $content );
        $content = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $content );

        // Bold and italic
        $content = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content );
        $content = preg_replace( '/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $content );

        // Links
        $content = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $content );

        // Lists
        $content = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $content );
        $content = preg_replace( '/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $content );

        // Tables
        $content = $this->convert_markdown_tables( $content );

        // Paragraphs
        $content = preg_replace( '/\n\n+/', '</p><p>', $content );
        $content = '<p>' . $content . '</p>';

        // Cleanup
        $content = str_replace( '<p></p>', '', $content );
        $content = str_replace( '<p><h', '<h', $content );
        $content = str_replace( '</h2></p>', '</h2>', $content );
        $content = str_replace( '</h3></p>', '</h3>', $content );
        $content = str_replace( '<p><ul>', '<ul>', $content );
        $content = str_replace( '</ul></p>', '</ul>', $content );
        $content = str_replace( '<p><table', '<table', $content );
        $content = str_replace( '</table></p>', '</table>', $content );
        $content = str_replace( '<p>---</p>', '<hr>', $content );
        $content = str_replace( '<p><hr></p>', '<hr>', $content );

        return $content;
    }

    /**
     * Convert Markdown tables to HTML
     */
    private function convert_markdown_tables( $content ) {
        $pattern = '/\|(.+)\|\n\|[-| ]+\|\n((?:\|.+\|\n?)+)/';
        
        return preg_replace_callback( $pattern, function( $matches ) {
            $header_cells = array_map( 'trim', explode( '|', trim( $matches[1], '|' ) ) );
            $rows = explode( "\n", trim( $matches[2] ) );
            
            $html = '<table class="tsa-table"><thead><tr>';
            foreach ( $header_cells as $cell ) {
                $html .= '<th>' . trim( $cell ) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            
            foreach ( $rows as $row ) {
                if ( empty( trim( $row ) ) ) continue;
                $cells = array_map( 'trim', explode( '|', trim( $row, '|' ) ) );
                $html .= '<tr>';
                foreach ( $cells as $cell ) {
                    $html .= '<td>' . trim( $cell ) . '</td>';
                }
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table>';
            return $html;
        }, $content );
    }
}
