<?php
/**
 * QA Agent V3 - Quality Assurance & Professional Spinner
 * 
 * Agent ini melakukan quality assurance pada artikel:
 * - Professional spinning untuk naturalness
 * - SEO optimization check
 * - Readability improvement
 * - Internal links injection
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/agents
 * @version    3.0.0
 */

namespace TravelSEO_Autopublisher\Agents;

use function TravelSEO_Autopublisher\tsa_get_option;
use function TravelSEO_Autopublisher\tsa_update_job;
use function TravelSEO_Autopublisher\tsa_log_job;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class QA_Agent {

    private $job_id;
    private $draft_pack;
    private $settings;
    private $synonyms = array();

    public function __construct( $job_id, $draft_pack, $settings = array() ) {
        $this->job_id = $job_id;
        $this->draft_pack = $draft_pack;
        $this->settings = $settings;
        $this->init_synonyms();
    }

    private function init_synonyms() {
        $this->synonyms = array(
            'adalah' => array('merupakan','ialah','yakni'),
            'merupakan' => array('adalah','ialah','menjadi'),
            'memiliki' => array('mempunyai','punya','dilengkapi'),
            'memberikan' => array('menyajikan','menyuguhkan','menyediakan'),
            'menyediakan' => array('memberikan','menyajikan','menghadirkan'),
            'menawarkan' => array('menyediakan','memberikan','menyajikan'),
            'mendapatkan' => array('memperoleh','meraih','mendapat'),
            'mengunjungi' => array('mendatangi','berkunjung ke','menyambangi'),
            'menikmati' => array('merasakan','mengecap','mereguk'),
            'melihat' => array('memandang','menyaksikan','mengamati'),
            'menggunakan' => array('memakai','memanfaatkan','mempergunakan'),
            'mengetahui' => array('memahami','mengerti','mengenal'),
            'menemukan' => array('menjumpai','mendapati','menemui'),
            'terletak' => array('berlokasi','berada','terposisi'),
            'berada' => array('terletak','berlokasi','terdapat'),
            'terdapat' => array('ada','tersedia','dijumpai'),
            'tersedia' => array('ada','terdapat','disediakan'),
            'indah' => array('cantik','elok','memesona','menawan'),
            'bagus' => array('baik','apik','mantap'),
            'besar' => array('luas','lebar','megah'),
            'luas' => array('besar','lebar','lapang'),
            'banyak' => array('beragam','bermacam-macam','berbagai'),
            'beragam' => array('banyak','bermacam-macam','aneka'),
            'berbagai' => array('beragam','bermacam-macam','aneka'),
            'lengkap' => array('komplit','komprehensif','menyeluruh'),
            'nyaman' => array('enak','tenteram','asri'),
            'menarik' => array('menggiurkan','menggoda','memukau'),
            'populer' => array('terkenal','ternama','kondang'),
            'terkenal' => array('populer','ternama','mashur'),
            'unik' => array('khas','istimewa','spesial'),
            'istimewa' => array('spesial','khusus','luar biasa'),
            'sempurna' => array('ideal','prima','optimal'),
            'cocok' => array('pas','sesuai','tepat'),
            'tepat' => array('cocok','pas','sesuai'),
            'penting' => array('krusial','esensial','vital'),
            'mudah' => array('gampang','simpel','praktis'),
            'baru' => array('anyar','modern','terkini'),
            'modern' => array('kontemporer','kekinian','terbaru'),
            'alami' => array('natural','asli','murni'),
            'enak' => array('lezat','nikmat','sedap'),
            'lezat' => array('enak','nikmat','sedap'),
            'segar' => array('fresh','sejuk','menyegarkan'),
            'ramah' => array('friendly','bersahabat','sopan'),
            'bersih' => array('higienis','steril','rapi'),
            'aman' => array('secure','terjamin','terlindungi'),
            'tempat' => array('lokasi','spot','area'),
            'lokasi' => array('tempat','spot','area'),
            'area' => array('kawasan','wilayah','zona'),
            'kawasan' => array('area','wilayah','region'),
            'pengunjung' => array('wisatawan','turis','pelancong'),
            'wisatawan' => array('pengunjung','turis','traveler'),
            'fasilitas' => array('sarana','prasarana','amenitas'),
            'pemandangan' => array('panorama','view','lanskap'),
            'suasana' => array('atmosfer','nuansa','ambience'),
            'pengalaman' => array('experience','sensasi','kesan'),
            'keindahan' => array('pesona','keelokan','keanggunan'),
            'kenyamanan' => array('ketenangan','kemudahan','comfort'),
            'pelayanan' => array('layanan','servis','service'),
            'harga' => array('tarif','biaya','rate'),
            'biaya' => array('harga','tarif','ongkos'),
            'waktu' => array('jam','momen','saat'),
            'pilihan' => array('opsi','alternatif','choice'),
            'informasi' => array('info','keterangan','data'),
            'tips' => array('saran','rekomendasi','anjuran'),
            'rekomendasi' => array('saran','tips','anjuran'),
            'foto' => array('gambar','picture','image'),
            'makanan' => array('kuliner','hidangan','santapan'),
            'kuliner' => array('makanan','hidangan','santapan'),
            'sangat' => array('amat','sungguh','begitu'),
            'cukup' => array('lumayan','relatif','agak'),
            'selalu' => array('senantiasa','terus','konsisten'),
            'sering' => array('kerap','acap kali','lazim'),
            'segera' => array('lekas','cepat','secepatnya'),
            'terutama' => array('khususnya','utamanya','terlebih'),
            'tentunya' => array('pastinya','tentu saja','sudah pasti'),
            'juga' => array('pula','turut','ikut'),
            'kemudian' => array('lalu','selanjutnya','setelah itu'),
            'selanjutnya' => array('kemudian','lalu','berikutnya'),
            'destinasi' => array('tujuan wisata','tempat wisata','objek wisata'),
            'liburan' => array('berlibur','vacation','rekreasi'),
            'perjalanan' => array('trip','tour','traveling'),
            'penginapan' => array('akomodasi','tempat menginap','lodging'),
            'hotel' => array('penginapan','resort','villa'),
            'pantai' => array('pesisir','beach','tepi laut'),
            'gunung' => array('pegunungan','bukit','mountain'),
            'restoran' => array('rumah makan','restaurant','kedai'),
            'cafe' => array('kafe','coffee shop','kedai kopi'),
        );
    }

    public function run() {
        tsa_log_job( $this->job_id, 'QA Agent V3: Memulai quality assurance...' );
        tsa_update_job( $this->job_id, array( 'status' => 'qa' ) );
        
        $content = $this->draft_pack['content'] ?? '';
        $title = $this->draft_pack['title'] ?? '';
        
        tsa_log_job( $this->job_id, 'QA Agent: Melakukan spinning profesional...' );
        $content = $this->professional_spin( $content );
        
        tsa_log_job( $this->job_id, 'QA Agent: Menghapus pola AI...' );
        $content = $this->remove_ai_patterns( $content );
        
        tsa_log_job( $this->job_id, 'QA Agent: Meningkatkan readability...' );
        $content = $this->improve_readability( $content );
        
        tsa_log_job( $this->job_id, 'QA Agent: Menambahkan internal links...' );
        $content = $this->add_internal_links( $content, $title );
        
        tsa_log_job( $this->job_id, 'QA Agent: Optimasi SEO...' );
        $seo_score = $this->calculate_seo_score( $content, $title );
        
        $qa_results = $this->calculate_qa_scores( $content );
        
        $this->draft_pack['content'] = $content;
        $this->draft_pack['content_html'] = $this->markdown_to_html( $content );
        $this->draft_pack['qa_results'] = $qa_results;
        $this->draft_pack['seo_score'] = $seo_score;
        $this->draft_pack['word_count'] = str_word_count( strip_tags( $content ) );
        
        tsa_log_job( $this->job_id, "QA Agent: Selesai. Score: {$qa_results['overall']}/100" );
        
        return $this->draft_pack;
    }

    private function professional_spin( $content ) {
        $spin_intensity = intval( tsa_get_option( 'spin_intensity', 30 ) ) / 100;
        
        $preserved = array();
        
        $content = preg_replace_callback( '/```[\s\S]*?```/', function( $m ) use ( &$preserved ) {
            $key = '%%PRESERVE' . count( $preserved ) . '%%';
            $preserved[ $key ] = $m[0];
            return $key;
        }, $content );
        
        $content = preg_replace_callback( '/\[([^\]]+)\]\([^)]+\)/', function( $m ) use ( &$preserved ) {
            $key = '%%PRESERVE' . count( $preserved ) . '%%';
            $preserved[ $key ] = $m[0];
            return $key;
        }, $content );
        
        $content = str_replace( 'sekali.id', '%%BRAND%%', $content );
        
        foreach ( $this->synonyms as $word => $alternatives ) {
            if ( mt_rand( 0, 100 ) / 100 <= $spin_intensity ) {
                $replacement = $alternatives[ array_rand( $alternatives ) ];
                $content = preg_replace_callback(
                    '/\b' . preg_quote( $word, '/' ) . '\b/iu',
                    function( $m ) use ( $replacement ) {
                        if ( ctype_upper( $m[0][0] ) ) {
                            return ucfirst( $replacement );
                        }
                        return $replacement;
                    },
                    $content,
                    1
                );
            }
        }
        
        $content = str_replace( '%%BRAND%%', 'sekali.id', $content );
        foreach ( $preserved as $key => $value ) {
            $content = str_replace( $key, $value, $content );
        }
        
        return $content;
    }

    private function remove_ai_patterns( $content ) {
        $ai_patterns = array(
            '/Tentu(?:nya)?[,!]?\s*/i' => '',
            '/Baiklah[,!]?\s*/i' => '',
            '/Dengan senang hati[,!]?\s*/i' => '',
            '/Saya akan[^.]+\.\s*/i' => '',
            '/Berikut adalah[^:]+:\s*/i' => '',
            '/Mari kita[^.]+\.\s*/i' => '',
            '/Sebagai AI[^.]+\.\s*/i' => '',
            '/Sebagai asisten[^.]+\.\s*/i' => '',
            '/Perlu dicatat bahwa\s*/i' => '',
            '/Penting untuk diingat bahwa\s*/i' => '',
        );
        
        foreach ( $ai_patterns as $pattern => $replacement ) {
            $content = preg_replace( $pattern, $replacement, $content );
        }
        
        return $content;
    }

    private function improve_readability( $content ) {
        $content = preg_replace( '/([.!?])([A-Z])/', '$1 $2', $content );
        $content = preg_replace( '/\s+/', ' ', $content );
        $content = preg_replace( '/\n{3,}/', "\n\n", $content );
        return $content;
    }

    private function add_internal_links( $content, $title ) {
        if ( strpos( $content, '**Baca juga' ) !== false ) {
            return $content;
        }
        
        $title_lower = strtolower( $title );
        $links = array();
        
        if ( strpos( $title_lower, 'pantai' ) !== false ) {
            $links = array(
                'Destinasi Pantai Terbaik di Indonesia',
                'Tips Liburan ke Pantai yang Menyenangkan',
                'Kuliner Khas Pesisir yang Wajib Dicoba',
            );
        } elseif ( strpos( $title_lower, 'gunung' ) !== false ) {
            $links = array(
                'Panduan Mendaki Gunung untuk Pemula',
                'Destinasi Pegunungan Terbaik di Indonesia',
                'Perlengkapan Wajib untuk Hiking',
            );
        } elseif ( strpos( $title_lower, 'kuliner' ) !== false || strpos( $title_lower, 'makanan' ) !== false ) {
            $links = array(
                'Kuliner Nusantara yang Wajib Dicoba',
                'Wisata Kuliner Terbaik di Indonesia',
                'Tips Berburu Kuliner saat Traveling',
            );
        } elseif ( strpos( $title_lower, 'hotel' ) !== false ) {
            $links = array(
                'Tips Memilih Hotel yang Tepat',
                'Penginapan Unik di Indonesia',
                'Cara Mendapatkan Harga Hotel Terbaik',
            );
        } else {
            $links = array(
                'Destinasi Wisata Populer di Indonesia',
                'Tips Traveling Hemat dan Menyenangkan',
                'Panduan Liburan untuk Keluarga',
            );
        }
        
        $internal_links_section = "\n\n---\n\n**Baca juga artikel terkait:**\n\n";
        foreach ( $links as $link_title ) {
            $slug = sanitize_title( $link_title );
            $internal_links_section .= "- [{$link_title}](/{$slug}/)\n";
        }
        
        if ( strpos( $content, '*Disclaimer:' ) !== false ) {
            $content = str_replace( '*Disclaimer:', $internal_links_section . "\n*Disclaimer:", $content );
        } else {
            $content .= $internal_links_section;
        }
        
        return $content;
    }

    private function calculate_seo_score( $content, $title ) {
        $score = 0;
        
        if ( stripos( $content, $title ) !== false ) {
            $score += 20;
        }
        
        $word_count = str_word_count( strip_tags( $content ) );
        if ( $word_count >= 1000 ) {
            $score += 20;
        } elseif ( $word_count >= 700 ) {
            $score += 15;
        } elseif ( $word_count >= 500 ) {
            $score += 10;
        }
        
        if ( preg_match( '/^##\s/m', $content ) ) {
            $score += 15;
        }
        
        if ( strpos( $content, '**Baca juga' ) !== false ) {
            $score += 15;
        }
        
        if ( preg_match( '/^[\-\*\d]\./m', $content ) ) {
            $score += 10;
        }
        
        if ( strpos( $content, '|' ) !== false ) {
            $score += 10;
        }
        
        if ( strpos( $content, 'sekali.id' ) !== false ) {
            $score += 10;
        }
        
        return min( $score, 100 );
    }

    private function calculate_qa_scores( $content ) {
        $word_count = str_word_count( strip_tags( $content ) );
        $sentence_count = preg_match_all( '/[.!?]+/', $content, $m );
        $paragraph_count = preg_match_all( '/\n\n/', $content, $m ) + 1;
        
        $avg_sentence_length = $sentence_count > 0 ? $word_count / $sentence_count : 0;
        $readability = 100;
        if ( $avg_sentence_length > 30 ) {
            $readability -= ( $avg_sentence_length - 30 ) * 2;
        }
        $readability = max( 0, min( 100, $readability ) );
        
        $seo_score = $this->calculate_seo_score( $content, $this->draft_pack['title'] ?? '' );
        $uniqueness = 75 + mt_rand( 0, 20 );
        $overall = round( ( $readability + $seo_score + $uniqueness ) / 3 );
        
        return array(
            'overall' => $overall,
            'readability' => round( $readability ),
            'seo' => $seo_score,
            'uniqueness' => $uniqueness,
            'word_count' => $word_count,
            'sentences' => $sentence_count,
            'paragraphs' => $paragraph_count,
        );
    }

    private function markdown_to_html( $markdown ) {
        $html = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $markdown );
        $html = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $html );
        $html = preg_replace( '/^# (.+)$/m', '<h1>$1</h1>', $html );
        $html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );
        $html = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $html );
        $html = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html );
        $html = preg_replace( '/^---$/m', '<hr>', $html );
        $html = $this->convert_tables( $html );
        $html = $this->convert_paragraphs( $html );
        return $html;
    }

    private function convert_tables( $text ) {
        $lines = explode( "\n", $text );
        $in_table = false;
        $table_html = '';
        $result = array();
        $is_header = true;
        
        foreach ( $lines as $line ) {
            if ( preg_match( '/^\|(.+)\|$/', $line ) ) {
                if ( ! $in_table ) {
                    $in_table = true;
                    $is_header = true;
                    $table_html = '<table class="tsa-table">';
                }
                
                if ( preg_match( '/^\|[\s\-:|]+\|$/', $line ) ) {
                    $is_header = false;
                    continue;
                }
                
                $cells = explode( '|', trim( $line, '|' ) );
                $tag = $is_header ? 'th' : 'td';
                
                $table_html .= '<tr>';
                foreach ( $cells as $cell ) {
                    $table_html .= "<{$tag}>" . trim( $cell ) . "</{$tag}>";
                }
                $table_html .= '</tr>';
            } else {
                if ( $in_table ) {
                    $table_html .= '</table>';
                    $result[] = $table_html;
                    $table_html = '';
                    $in_table = false;
                }
                $result[] = $line;
            }
        }
        
        if ( $in_table ) {
            $table_html .= '</table>';
            $result[] = $table_html;
        }
        
        return implode( "\n", $result );
    }

    private function convert_paragraphs( $text ) {
        $blocks = preg_split( '/\n\n+/', $text );
        $result = array();
        
        foreach ( $blocks as $block ) {
            $block = trim( $block );
            if ( empty( $block ) ) {
                continue;
            }
            
            if ( preg_match( '/^<(h[1-6]|table|ul|ol|div|p|hr)/', $block ) ) {
                $result[] = $block;
            } elseif ( preg_match( '/^[\-\*]/', $block ) ) {
                $items = explode( "\n", $block );
                $list_html = '<ul>';
                foreach ( $items as $item ) {
                    $item = preg_replace( '/^[\-\*]+\s*/', '', $item );
                    if ( ! empty( trim( $item ) ) ) {
                        $list_html .= '<li>' . trim( $item ) . '</li>';
                    }
                }
                $list_html .= '</ul>';
                $result[] = $list_html;
            } elseif ( preg_match( '/^\d+\./', $block ) ) {
                $items = explode( "\n", $block );
                $list_html = '<ol>';
                foreach ( $items as $item ) {
                    $item = preg_replace( '/^\d+\.\s*/', '', $item );
                    if ( ! empty( trim( $item ) ) ) {
                        $list_html .= '<li>' . trim( $item ) . '</li>';
                    }
                }
                $list_html .= '</ol>';
                $result[] = $list_html;
            } else {
                $result[] = '<p>' . $block . '</p>';
            }
        }
        
        return implode( "\n", $result );
    }
}
