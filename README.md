# TravelSEO Autopublisher

**Automate SEO content creation for travel blogs with a multi-agent pipeline, from research to publishing.**

---

## Deskripsi

TravelSEO Autopublisher adalah plugin WordPress canggih yang dirancang untuk mengotomatiskan pembuatan konten SEO berkualitas tinggi untuk blog travel, kuliner, dan destinasi. Plugin ini menggunakan pipeline multi-agen cerdas untuk menangani seluruh proses, mulai dari riset kata kunci dan scraping kompetitor hingga penulisan artikel, optimasi SEO, dan penjadwalan gambar.

Dengan TravelSEO Autopublisher, Anda dapat membuat puluhan artikel unik dan teroptimasi hanya dengan memberikan daftar judul. Plugin ini bekerja di latar belakang, memungkinkan Anda untuk fokus pada aspek lain dari website Anda sementara konten dibuat secara otomatis.

## Fitur Utama

*   **Pembuatan Artikel Otomatis**: Cukup masukkan daftar judul (misalnya, "Pantai Kuta Bali", "Wisata Candi Borobudur"), dan plugin akan membuat artikel lengkap untuk setiap judul.
*   **Pipeline Multi-Agen**: Proses pembuatan konten dibagi menjadi beberapa agen cerdas:
    1.  **Agen Riset**: Melakukan scraping pada 5 hasil pencarian teratas untuk mengumpulkan poin-poin penting, data strategis, dan kata kunci.
    2.  **Agen Penulis**: Menulis draf artikel lengkap berdasarkan hasil riset, termasuk struktur heading, paragraf, dan daftar.
    3.  **Agen QA (Quality Assurance)**: Menganalisis dan memoles konten untuk memastikan kualitas, keterbacaan, dan orisinalitas. Melakukan spin pada kalimat agar lebih natural dan lolos dari deteksi AI.
    4.  **Agen Gambar**: Menganalisis konten dan merekomendasikan penempatan gambar, lengkap dengan sugesti kata kunci pencarian gambar.
*   **Mode Fleksibel (Tanpa API & Dengan API)**:
    *   **Mode Gratis (Default)**: Plugin dapat berfungsi sepenuhnya **tanpa memerlukan API key** dengan menggunakan metode scraping dan pemrosesan teks internal.
    *   **Mode API (Opsional)**: Untuk kualitas konten yang lebih tinggi, Anda dapat menambahkan API key dari OpenAI (GPT-3.5/4), DeepSeek, OpenRouter, dll.
*   **Manajemen Antrian (Queue)**: Semua tugas dijalankan di latar belakang menggunakan Action Scheduler (jika tersedia) atau WP Cron, sehingga tidak akan memperlambat website Anda.
*   **Dasbor Intuitif**: Pantau status semua pekerjaan (jobs), lihat statistik, dan kelola campaign dari satu tempat.
*   **Pratinjau & Editor**: Lihat pratinjau artikel yang sudah jadi sebelum dipublikasikan. Dengan satu klik, kirim artikel ke editor WordPress default untuk penyesuaian akhir.
*   **Optimasi SEO**: Plugin secara otomatis membuat meta title, meta description, kategori, dan tag yang relevan. Terintegrasi dengan Yoast SEO dan Rank Math.
*   **Kustomisasi Mudah**: Atur mode publikasi (draft, publish, schedule), target jumlah kata, bahasa, dan gaya penulisan untuk setiap campaign.

## Instalasi

1.  Unduh file `travelseo-autopublisher.zip`.
2.  Masuk ke dasbor WordPress Anda.
3.  Buka **Plugins > Add New**.
4.  Klik **Upload Plugin**.
5.  Pilih file `travelseo-autopublisher.zip` yang sudah Anda unduh.
6.  Klik **Install Now**, lalu **Activate Plugin**.

Setelah aktivasi, menu baru bernama **TravelSEO AI** akan muncul di sidebar admin Anda.

## Cara Penggunaan

### 1. Konfigurasi Pengaturan (Opsional)

Sebelum membuat campaign pertama Anda, disarankan untuk meninjau pengaturan di **TravelSEO AI > Settings**.

*   **API Settings**: Jika Anda ingin menggunakan AI dari OpenAI atau penyedia lain, masukkan API key dan endpoint Anda di sini. Jika dibiarkan kosong, plugin akan berjalan dalam mode gratis.
*   **Image API Settings**: Masukkan API key dari Unsplash atau Pexels jika Anda ingin fitur auto-fetch gambar.
*   **Default Article Settings**: Atur jumlah kata, mode publikasi, dan bahasa default untuk semua campaign.

### 2. Buat Campaign Baru

1.  Buka **TravelSEO AI > New Campaign**.
2.  **Campaign Name**: Beri nama untuk grup artikel ini (misalnya, "Artikel Wisata Jawa Tengah").
3.  **Article Titles**: Masukkan semua judul artikel yang ingin Anda buat, **satu judul per baris**.
    ```
    Candi Borobudur
    Dataran Tinggi Dieng
    Lawang Sewu Semarang
    Keraton Surakarta Hadiningrat
    ```
4.  **Article Settings**: Sesuaikan mode publikasi, jumlah kata, bahasa, dan gaya penulisan jika perlu.
5.  **Category & Tags**: Pilih apakah kategori akan dibuat otomatis atau Anda ingin memilih dari yang sudah ada.
6.  **Image Settings**: Pilih mode penanganan gambar (hanya rekomendasi, cari di Media Library, atau auto-fetch dari API).
7.  Klik **Create Campaign & Start Processing**.

### 3. Pantau Proses

Buka **TravelSEO AI > Jobs** untuk melihat status semua artikel Anda.

*   **Queued**: Menunggu untuk diproses.
*   **Processing**: Sedang dalam tahap riset, penulisan, atau QA.
*   **Ready**: Artikel selesai dibuat dan siap untuk dipublikasikan.
*   **Pushed**: Artikel sudah dikirim ke Posts (sebagai draft atau sudah publish).
*   **Failed**: Terjadi kesalahan saat proses.

### 4. Pratinjau dan Publikasikan

1.  Di halaman **Jobs**, klik **View** pada artikel yang berstatus **Ready**.
2.  Anda akan melihat halaman detail pekerjaan dengan pratinjau lengkap artikel, metadata, data riset, dan hasil QA.
3.  Jika Anda puas dengan hasilnya, gunakan tombol di bagian atas untuk:
    *   **Create Draft**: Mengirim artikel ke **Posts > All Posts** sebagai draft.
    *   **Publish Now**: Langsung mempublikasikan artikel.
4.  Anda juga dapat mengeditnya lebih lanjut di editor WordPress sebelum publikasi.

## FAQ (Pertanyaan yang Sering Diajukan)

**Q: Apakah plugin ini benar-benar gratis?**

A: Ya, plugin ini dapat berfungsi penuh dalam mode gratis tanpa API key. Mode gratis menggunakan scraping web dan template untuk menghasilkan konten. Kualitasnya mungkin tidak setinggi jika menggunakan API, tetapi tetap menghasilkan artikel yang terstruktur dengan baik.

**Q: Apakah konten yang dihasilkan unik dan lolos plagiarisme?**

A: Agen QA kami memiliki fitur untuk memoles ulang kalimat (spinning) dan melakukan pengecekan duplikasi internal untuk memastikan setiap artikel yang dihasilkan unik dalam website Anda. Namun, untuk jaminan 100% lolos dari tool plagiarisme eksternal, disarankan untuk mereview kembali atau menggunakan API AI berkualitas tinggi.

**Q: Bisakah saya menggunakan API selain OpenAI?**

A: Ya. Selama penyedia API tersebut kompatibel dengan endpoint OpenAI (seperti OpenRouter, DeepSeek, dll.), Anda bisa memasukkan endpoint dan API key-nya di halaman pengaturan.

**Q: Apa yang terjadi jika proses sebuah artikel gagal?**

A: Artikel tersebut akan ditandai sebagai "Failed". Anda dapat melihat log error di halaman detail pekerjaan dan mencoba memprosesnya kembali dengan mengklik tombol "Retry".

## Fitur Lanjutan (v1.1.0+)

### 1. Topical Authority Cluster Builder

Fitur ini memungkinkan Anda untuk mengelompokkan artikel terkait ke dalam "cluster" topik untuk membangun otoritas topikal di mata mesin pencari.

**Cara Mengaktifkan:**
1. Buka **TravelSEO AI > Settings**
2. Scroll ke bagian **Advanced SEO Automation**
3. Aktifkan toggle **Topical Authority Cluster**
4. Simpan pengaturan

**Cara Menggunakan:**
1. Menu **Clusters** akan muncul di sidebar
2. Klik **Add New Cluster** untuk membuat cluster baru
3. Isi nama cluster (misalnya "Wisata Bali") dan pillar keyword (misalnya "wisata bali")
4. Saat membuat artikel baru, plugin akan otomatis menyarankan cluster yang sesuai berdasarkan judul
5. Artikel dalam cluster yang sama akan saling terhubung melalui internal linking

**Manfaat SEO:**
- Membangun topical authority untuk niche tertentu
- Meningkatkan internal linking secara natural
- Membantu Google memahami struktur konten website Anda
- Meningkatkan peluang ranking untuk keyword kompetitif

## Changelog

**1.1.0 - 08/01/2026**
*   Added: GitHub Auto-Update feature
*   Added: Advanced SEO Automation feature flags
*   Added: Topical Authority Cluster Builder (Feature #1)
*   Improved: Database schema with additional fields
*   Improved: Admin UI with toggle switches for features

**1.0.0 - 08/01/2026**
*   Initial release.

---

