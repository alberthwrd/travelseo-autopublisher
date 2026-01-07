<?php
/**
 * Summarizer Agent - Merangkum Research Data
 * 
 * Agent ini bertugas membaca semua data mentah dari Research Agent
 * dan mensintesisnya menjadi satu dokumen riset yang koheren dan terstruktur.
 * Ini adalah langkah kunci sebelum penulisan artikel.
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/includes/agents
 * @version    1.0.0
 */

namespace TravelSEO_Autopublisher\Agents;

use function TravelSEO_Autopublisher\tsa_get_option;
use function TravelSEO_Autopublisher\tsa_update_job;
use function TravelSEO_Autopublisher\tsa_log_job;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Summarizer_Agent {

    /**
     * Job ID
     */
    private $job_id;

    /**
     * Research pack dari Research Agent
     */
    private $research_pack;

    /**
     * Constructor
     */
    public function __construct( $job_id, $research_pack ) {
        $this->job_id = $job_id;
        $this->research_pack = $research_pack;
    }

    /**
     * Run summarization process
     */
    public function run() {
        tsa_log_job( $this->job_id, 'Summarizer Agent: Memulai proses rangkuman...' );
        
        $title = $this->research_pack['title'] ?? '';
        $content_type = $this->research_pack['content_type'] ?? 'umum';
        $combined_text = $this->research_pack['combined_text'] ?? '';
        $extracted_info = $this->research_pack['extracted_info'] ?? array();
        
        // Check if AI API is available
        $use_ai = $this->has_ai_api();
        
        if ( $use_ai ) {
            tsa_log_job( $this->job_id, 'Summarizer Agent: Menggunakan AI untuk rangkuman...' );
            $summary = $this->summarize_with_ai( $title, $content_type, $combined_text, $extracted_info );
        } else {
            tsa_log_job( $this->job_id, 'Summarizer Agent: Menggunakan metode lokal untuk rangkuman...' );
            $summary = $this->summarize_locally( $title, $content_type, $combined_text, $extracted_info );
        }
        
        tsa_log_job( $this->job_id, 'Summarizer Agent: Rangkuman selesai. ' . strlen( $summary ) . ' karakter' );
        
        return array(
            'title'        => $title,
            'content_type' => $content_type,
            'summary'      => $summary,
            'keywords'     => $this->research_pack['keywords'] ?? array(),
            'info'         => $extracted_info,
            'source_count' => $this->research_pack['source_count'] ?? 0,
        );
    }

    /**
     * Check if AI API is available
     */
    private function has_ai_api() {
        $api_key = tsa_get_option( 'openai_api_key', '' );
        return ! empty( $api_key );
    }

    /**
     * Summarize using AI
     */
    private function summarize_with_ai( $title, $content_type, $combined_text, $extracted_info ) {
        $api_key = tsa_get_option( 'openai_api_key', '' );
        $api_url = tsa_get_option( 'openai_api_url', 'https://api.openai.com/v1/chat/completions' );
        $model = tsa_get_option( 'openai_model', 'gpt-3.5-turbo' );
        
        // Truncate if too long
        if ( strlen( $combined_text ) > 12000 ) {
            $combined_text = substr( $combined_text, 0, 12000 ) . "\n\n[Teks dipotong karena terlalu panjang]";
        }
        
        $prompt = $this->build_summarizer_prompt( $title, $content_type, $combined_text, $extracted_info );
        
        $response = wp_remote_post( $api_url, array(
            'timeout' => 120,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => json_encode( array(
                'model'       => $model,
                'messages'    => array(
                    array(
                        'role'    => 'system',
                        'content' => 'Anda adalah asisten riset ahli yang bertugas merangkum dan mensintesis data dari berbagai sumber menjadi dokumen riset yang terstruktur dan komprehensif dalam Bahasa Indonesia.',
                    ),
                    array(
                        'role'    => 'user',
                        'content' => $prompt,
                    ),
                ),
                'temperature' => 0.3,
                'max_tokens'  => 3000,
            ) ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            tsa_log_job( $this->job_id, 'Summarizer Agent: AI Error - ' . $response->get_error_message() );
            return $this->summarize_locally( $title, $content_type, $combined_text, $extracted_info );
        }
        
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( isset( $body['choices'][0]['message']['content'] ) ) {
            return $body['choices'][0]['message']['content'];
        }
        
        return $this->summarize_locally( $title, $content_type, $combined_text, $extracted_info );
    }

    /**
     * Build summarizer prompt
     */
    private function build_summarizer_prompt( $title, $content_type, $combined_text, $extracted_info ) {
        $info_text = '';
        if ( ! empty( $extracted_info ) ) {
            $info_text = "\n\nInformasi yang sudah diekstrak:\n";
            foreach ( $extracted_info as $key => $value ) {
                if ( ! empty( $value ) ) {
                    if ( is_array( $value ) ) {
                        $info_text .= "- {$key}: " . implode( ', ', $value ) . "\n";
                    } else {
                        $info_text .= "- {$key}: {$value}\n";
                    }
                }
            }
        }
        
        return "Tugas Anda adalah membaca dan merangkum data riset berikut tentang \"{$title}\" (tipe: {$content_type}).

DATA RISET MENTAH:
{$combined_text}
{$info_text}

INSTRUKSI:
1. Baca semua data dengan teliti
2. Identifikasi dan ekstrak semua fakta penting
3. Buang informasi yang tidak relevan, iklan, atau noise
4. Organisasikan informasi ke dalam kategori yang logis
5. Tulis ringkasan riset yang komprehensif dan terstruktur

FORMAT OUTPUT (gunakan format ini):

## Ringkasan Umum
[Deskripsi singkat tentang {$title} dalam 2-3 paragraf]

## Informasi Praktis
- Lokasi/Alamat: [info]
- Harga Tiket: [info dengan detail jika ada]
- Jam Operasional: [info]
- Kontak: [jika ada]

## Fasilitas yang Tersedia
[List fasilitas]

## Aktivitas dan Pengalaman
[Apa yang bisa dilakukan pengunjung]

## Tips Berkunjung
[Tips praktis untuk pengunjung]

## Hal yang Perlu Diperhatikan
[Warning atau catatan penting]

## Fakta Menarik
[Fakta unik atau sejarah singkat jika ada]

## Rekomendasi Sekitar
[Tempat makan, penginapan, atau destinasi terdekat jika ada info]

Pastikan semua informasi akurat berdasarkan data yang diberikan. Jika informasi tidak tersedia, tulis 'Informasi tidak tersedia dari sumber'.";
    }

    /**
     * Summarize locally without AI
     */
    private function summarize_locally( $title, $content_type, $combined_text, $extracted_info ) {
        $summary = "## Ringkasan Riset: {$title}\n\n";
        $summary .= "Tipe Konten: " . ucfirst( $content_type ) . "\n\n";
        
        // Overview
        $summary .= "### Ringkasan Umum\n\n";
        
        // Extract first good paragraph as overview
        $overview = $this->extract_best_paragraph( $combined_text, $title );
        if ( ! empty( $overview ) ) {
            $summary .= $overview . "\n\n";
        } else {
            $summary .= "{$title} merupakan salah satu destinasi yang menarik untuk dikunjungi. ";
            $summary .= "Tempat ini menawarkan pengalaman yang unik bagi para pengunjung.\n\n";
        }
        
        // Practical info
        $summary .= "### Informasi Praktis\n\n";
        
        if ( ! empty( $extracted_info['alamat'] ) ) {
            $summary .= "**Alamat/Lokasi:** {$extracted_info['alamat']}\n\n";
        }
        
        if ( ! empty( $extracted_info['harga'] ) ) {
            $summary .= "**Harga Tiket:** {$extracted_info['harga']}\n\n";
        } else {
            $summary .= "**Harga Tiket:** Informasi harga dapat berubah, disarankan menghubungi pengelola.\n\n";
        }
        
        if ( ! empty( $extracted_info['jam_buka'] ) ) {
            $summary .= "**Jam Operasional:** {$extracted_info['jam_buka']}\n\n";
        } else {
            $summary .= "**Jam Operasional:** Disarankan mengecek jam operasional terbaru sebelum berkunjung.\n\n";
        }
        
        // Facilities
        $summary .= "### Fasilitas\n\n";
        if ( ! empty( $extracted_info['fasilitas'] ) ) {
            foreach ( $extracted_info['fasilitas'] as $fasilitas ) {
                $summary .= "- {$fasilitas}\n";
            }
            $summary .= "\n";
        } else {
            $summary .= "Informasi fasilitas tidak tersedia dari sumber.\n\n";
        }
        
        // Activities
        $summary .= "### Aktivitas yang Bisa Dilakukan\n\n";
        if ( ! empty( $extracted_info['aktivitas'] ) ) {
            foreach ( $extracted_info['aktivitas'] as $aktivitas ) {
                $summary .= "- {$aktivitas}\n";
            }
            $summary .= "\n";
        } else {
            $summary .= "- Menikmati suasana dan pemandangan\n";
            $summary .= "- Berfoto dan dokumentasi\n";
            $summary .= "- Bersantai bersama keluarga atau teman\n\n";
        }
        
        // Tips
        $summary .= "### Tips Berkunjung\n\n";
        $summary .= $this->generate_tips( $content_type, $title );
        
        // Extract additional paragraphs for more context
        $summary .= "### Informasi Tambahan\n\n";
        $additional = $this->extract_additional_info( $combined_text, $title );
        if ( ! empty( $additional ) ) {
            $summary .= $additional . "\n\n";
        }
        
        // Source count
        $source_count = $this->research_pack['source_count'] ?? 0;
        $summary .= "---\n\n";
        $summary .= "*Ringkasan ini dibuat dari {$source_count} sumber referensi.*\n";
        
        return $summary;
    }

    /**
     * Extract best paragraph from text
     */
    private function extract_best_paragraph( $text, $title ) {
        $paragraphs = preg_split( '/\n\n+/', $text );
        $title_words = explode( ' ', strtolower( $title ) );
        
        $best_paragraph = '';
        $best_score = 0;
        
        foreach ( $paragraphs as $p ) {
            $p = trim( $p );
            
            // Skip short or too long paragraphs
            if ( strlen( $p ) < 100 || strlen( $p ) > 600 ) {
                continue;
            }
            
            // Skip if starts with special characters
            if ( preg_match( '/^[#\-\*\|]/', $p ) ) {
                continue;
            }
            
            // Calculate relevance score
            $score = 0;
            $p_lower = strtolower( $p );
            
            foreach ( $title_words as $word ) {
                if ( strlen( $word ) > 3 && strpos( $p_lower, $word ) !== false ) {
                    $score += 10;
                }
            }
            
            // Bonus for informative words
            $info_words = array( 'merupakan', 'adalah', 'terletak', 'berada', 'menawarkan', 'memiliki', 'terkenal', 'populer' );
            foreach ( $info_words as $word ) {
                if ( strpos( $p_lower, $word ) !== false ) {
                    $score += 5;
                }
            }
            
            if ( $score > $best_score ) {
                $best_score = $score;
                $best_paragraph = $p;
            }
        }
        
        return $best_paragraph;
    }

    /**
     * Extract additional info from text
     */
    private function extract_additional_info( $text, $title ) {
        $paragraphs = preg_split( '/\n\n+/', $text );
        $additional = array();
        
        foreach ( $paragraphs as $p ) {
            $p = trim( $p );
            
            // Look for paragraphs with useful info
            if ( strlen( $p ) > 80 && strlen( $p ) < 400 ) {
                // Skip if already used or not informative
                if ( preg_match( '/^[#\-\*\|]/', $p ) ) {
                    continue;
                }
                
                // Check for useful content
                $useful_patterns = array(
                    '/sejarah/i',
                    '/didirikan/i',
                    '/dibangun/i',
                    '/unik/i',
                    '/menarik/i',
                    '/terkenal/i',
                    '/terbaik/i',
                    '/rekomendasi/i',
                );
                
                foreach ( $useful_patterns as $pattern ) {
                    if ( preg_match( $pattern, $p ) ) {
                        $additional[] = $p;
                        break;
                    }
                }
            }
            
            if ( count( $additional ) >= 3 ) {
                break;
            }
        }
        
        return implode( "\n\n", $additional );
    }

    /**
     * Generate tips based on content type
     */
    private function generate_tips( $content_type, $title ) {
        $tips = array();
        
        switch ( $content_type ) {
            case 'destinasi':
                $tips = array(
                    'Datang di pagi hari untuk menghindari keramaian dan mendapatkan pengalaman lebih nyaman.',
                    'Bawa perlengkapan yang sesuai seperti topi, sunscreen, dan air minum.',
                    'Cek cuaca sebelum berkunjung untuk persiapan yang lebih baik.',
                    'Simpan barang berharga dengan aman dan selalu waspada.',
                    'Patuhi peraturan yang berlaku di lokasi untuk keselamatan bersama.',
                );
                break;
                
            case 'kuliner':
                $tips = array(
                    'Datang di luar jam makan siang untuk menghindari antrian panjang.',
                    'Coba menu andalan yang menjadi favorit pengunjung.',
                    'Tanyakan tingkat kepedasan jika tidak terbiasa dengan makanan pedas.',
                    'Reservasi terlebih dahulu jika berkunjung di akhir pekan.',
                    'Cek review terbaru untuk rekomendasi menu.',
                );
                break;
                
            case 'hotel':
                $tips = array(
                    'Booking jauh-jauh hari untuk mendapatkan harga terbaik.',
                    'Cek fasilitas yang tersedia sesuai kebutuhan Anda.',
                    'Baca review terbaru dari tamu sebelumnya.',
                    'Tanyakan tentang promo atau paket khusus.',
                    'Konfirmasi check-in dan check-out time sebelum kedatangan.',
                );
                break;
                
            default:
                $tips = array(
                    'Persiapkan perjalanan dengan baik sebelum berangkat.',
                    'Bawa perlengkapan yang diperlukan.',
                    'Cek informasi terbaru sebelum berkunjung.',
                    'Patuhi peraturan yang berlaku.',
                    'Jaga kebersihan dan kelestarian lingkungan.',
                );
        }
        
        $output = '';
        foreach ( $tips as $tip ) {
            $output .= "- {$tip}\n";
        }
        
        return $output . "\n";
    }
}
