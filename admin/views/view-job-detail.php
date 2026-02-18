<?php
/**
 * Job Detail View V4 - Project Hyperion
 * Professional Article Preview with 7-Stage Pipeline
 *
 * @package    TravelSEO_Autopublisher
 * @subpackage TravelSEO_Autopublisher/admin/views
 * @version    4.0.0
 */

if (!defined('ABSPATH')) exit;

global $wpdb;

$job_id = isset($_GET['job_id']) ? absint($_GET['job_id']) : 0;
if (!$job_id) {
    echo '<div class="notice notice-error"><p>Invalid job ID.</p></div>';
    return;
}

$table_jobs = $wpdb->prefix . 'tsa_jobs';
$job = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_jobs WHERE id = %d", $job_id));

if (!$job) {
    echo '<div class="notice notice-error"><p>Job not found.</p></div>';
    return;
}

// Parse JSON data
$research_pack = json_decode($job->research_pack ?: '{}', true);
$draft_pack = json_decode($job->draft_pack ?: '{}', true);
$hyperion = $research_pack['_hyperion'] ?? array();
$stages_completed = $hyperion['stages_completed'] ?? 0;

// Status info
$statuses = array(
    'queued'      => array('label' => 'Queued', 'icon' => 'clock', 'color' => '#6b7280'),
    'researching' => array('label' => 'Researching', 'icon' => 'search', 'color' => '#3b82f6'),
    'writing'     => array('label' => 'Writing', 'icon' => 'edit', 'color' => '#8b5cf6'),
    'qa'          => array('label' => 'Polishing', 'icon' => 'visibility', 'color' => '#f59e0b'),
    'images'      => array('label' => 'Finishing', 'icon' => 'format-image', 'color' => '#06b6d4'),
    'ready'       => array('label' => 'Ready', 'icon' => 'yes-alt', 'color' => '#10b981'),
    'pushed'      => array('label' => 'Published', 'icon' => 'admin-post', 'color' => '#059669'),
    'failed'      => array('label' => 'Failed', 'icon' => 'warning', 'color' => '#ef4444'),
);
$status_info = $statuses[$job->status] ?? array('label' => ucfirst($job->status), 'icon' => 'marker', 'color' => '#6b7280');

// Get content
$content_html = '';
if (!empty($draft_pack['content'])) {
    $content_html = $draft_pack['content'];
} elseif (!empty($hyperion['editor']['article_html'])) {
    $content_html = $hyperion['editor']['article_html'];
} elseif (!empty($hyperion['stylist']['article_html'])) {
    $content_html = $hyperion['stylist']['article_html'];
} elseif (!empty($hyperion['synthesizer']['article_html'])) {
    $content_html = $hyperion['synthesizer']['article_html'];
}

// Convert markdown remnants to HTML
if (!empty($content_html)) {
    $content_html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content_html);
    $content_html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $content_html);
    $content_html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content_html);
    $content_html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $content_html);
    // Wrap plain text lines in <p> if not already HTML
    $lines = explode("\n", $content_html);
    $processed = '';
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (empty($trimmed)) continue;
        if (preg_match('/^<(h[1-6]|p|table|thead|tbody|tr|th|td|ul|ol|li|blockquote|div|hr|!--|a|strong|em|span|img|figure|section|article|nav|header|footer|style)/', $trimmed)) {
            $processed .= $line . "\n";
        } elseif (preg_match('/^<\//', $trimmed)) {
            $processed .= $line . "\n";
        } elseif (strlen($trimmed) > 5) {
            $processed .= '<p>' . $trimmed . "</p>\n";
        }
    }
    if (!empty($processed)) $content_html = $processed;
}

// Scores
$seo_score = $draft_pack['seo_score'] ?? $hyperion['editor']['seo_score'] ?? array();
$readability = $draft_pack['readability'] ?? $hyperion['editor']['readability'] ?? array();
$word_count = $draft_pack['word_count'] ?? 0;
if (!$word_count && !empty($content_html)) {
    $word_count = str_word_count(strip_tags($content_html));
}
$reading_time = ceil($word_count / 200);
$formatting_stats = $draft_pack['formatting_stats'] ?? $hyperion['stylist']['formatting_stats'] ?? array();
$category = $draft_pack['category'] ?? '';
$tags = $draft_pack['tags'] ?? array();
$meta_desc = $draft_pack['meta_description'] ?? '';
$images = $draft_pack['image_suggestions'] ?? $hyperion['connector']['image_suggestions'] ?? array();
?>

<style>
.tsa-job-detail { max-width: 1400px; }

.tsa-status-bar {
    display: flex; justify-content: space-between; align-items: center;
    background: #fff; padding: 15px 20px; border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;
}
.tsa-status-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 20px; font-weight: 600; font-size: 13px;
    background: <?php echo esc_attr($status_info['color']); ?>; color: #fff;
}

/* Hyperion 7-Stage Progress */
.tsa-hyperion-progress {
    background: #fff; padding: 25px; border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;
}
.tsa-hyperion-title { font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 15px; }
.tsa-hyperion-stages { display: flex; justify-content: space-between; position: relative; }
.tsa-hyperion-stage { display: flex; flex-direction: column; align-items: center; flex: 1; position: relative; z-index: 1; }
.tsa-hyperion-stages::before {
    content: ''; position: absolute; top: 16px; left: 7%; right: 7%; height: 3px; background: #e5e7eb; z-index: 0;
}
.tsa-hyperion-dot {
    width: 34px; height: 34px; border-radius: 50%; background: #e5e7eb;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 12px; color: #9ca3af; transition: all 0.3s;
}
.tsa-hyperion-stage.done .tsa-hyperion-dot { background: #10b981; color: #fff; }
.tsa-hyperion-stage.active .tsa-hyperion-dot { background: #3b82f6; color: #fff; animation: hpulse 2s infinite; }
.tsa-hyperion-stage.error .tsa-hyperion-dot { background: #ef4444; color: #fff; }
@keyframes hpulse { 0%,100%{box-shadow:0 0 0 0 rgba(59,130,246,0.4)} 50%{box-shadow:0 0 0 8px rgba(59,130,246,0)} }
.tsa-hyperion-label { margin-top: 8px; font-size: 11px; color: #9ca3af; text-align: center; }
.tsa-hyperion-stage.done .tsa-hyperion-label,
.tsa-hyperion-stage.active .tsa-hyperion-label { color: #111827; font-weight: 600; }

/* Tabs */
.tsa-tabs { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
.tsa-tab-nav { display: flex; border-bottom: 1px solid #e5e7eb; background: #f9fafb; flex-wrap: wrap; }
.tsa-tab-link { padding: 14px 22px; text-decoration: none; color: #6b7280; font-weight: 500; border-bottom: 2px solid transparent; transition: all 0.2s; font-size: 14px; }
.tsa-tab-link:hover { color: #111827; background: #fff; }
.tsa-tab-link.active { color: #3b82f6; background: #fff; border-bottom-color: #3b82f6; }
.tsa-tab-content { display: none; padding: 30px; }
.tsa-tab-content.active { display: block; }

/* Article Preview */
.tsa-article-preview { max-width: 800px; margin: 0 auto; font-family: 'Georgia','Times New Roman',serif; line-height: 1.85; color: #1a1a1a; }
.tsa-article-header { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb; }
.tsa-article-title { font-size: 30px; font-weight: 700; line-height: 1.3; margin: 0 0 12px 0; color: #111827; }
.tsa-article-meta { display: flex; flex-wrap: wrap; gap: 18px; font-size: 13px; color: #6b7280; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; }
.tsa-article-meta-item { display: flex; align-items: center; gap: 5px; }
.tsa-article-excerpt { font-size: 17px; color: #4b5563; font-style: italic; margin-top: 15px; padding-left: 18px; border-left: 3px solid #3b82f6; }

.tsa-article-body { font-size: 17px; }
.tsa-article-body h2 { font-size: 24px; font-weight: 700; margin: 35px 0 18px 0; color: #111827; padding-bottom: 8px; border-bottom: 2px solid #e5e7eb; }
.tsa-article-body h3 { font-size: 20px; font-weight: 600; margin: 28px 0 14px 0; color: #374151; }
.tsa-article-body p { margin: 0 0 18px 0; text-align: justify; }
.tsa-article-body ul,.tsa-article-body ol { margin: 0 0 18px 0; padding-left: 25px; }
.tsa-article-body li { margin-bottom: 8px; line-height: 1.7; }
.tsa-article-body a { color: #3b82f6; text-decoration: underline; }
.tsa-article-body a:hover { color: #1d4ed8; }
.tsa-article-body strong { font-weight: 700; color: #111827; }
.tsa-article-body em { font-style: italic; }
.tsa-article-body table { width: 100%; border-collapse: collapse; margin: 22px 0; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; font-size: 15px; }
.tsa-article-body table th,.tsa-article-body table td { padding: 11px 14px; text-align: left; border: 1px solid #e5e7eb; }
.tsa-article-body table th { background: #f3f4f6; font-weight: 600; color: #374151; }
.tsa-article-body table tr:nth-child(even) { background: #f9fafb; }
.tsa-article-body blockquote { margin: 22px 0; padding: 18px 22px; background: #f3f4f6; border-left: 4px solid #3b82f6; font-style: italic; color: #4b5563; border-radius: 0 6px 6px 0; }
.tsa-article-body hr { border: none; border-top: 1px solid #e5e7eb; margin: 28px 0; }

/* Score Cards */
.tsa-score-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap: 15px; margin-bottom: 25px; }
.tsa-score-card { background: #f9fafb; border-radius: 8px; padding: 18px; text-align: center; }
.tsa-score-val { font-size: 32px; font-weight: 700; }
.tsa-score-val.good { color: #10b981; }
.tsa-score-val.warn { color: #f59e0b; }
.tsa-score-val.bad { color: #ef4444; }
.tsa-score-lbl { font-size: 13px; color: #6b7280; margin-top: 4px; }

.tsa-action-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
.tsa-action-buttons .button { display: inline-flex; align-items: center; gap: 5px; }

.tsa-empty-state { text-align: center; padding: 50px 20px; color: #6b7280; }
.tsa-empty-state .dashicons { font-size: 48px; width: 48px; height: 48px; margin-bottom: 12px; opacity: 0.4; }
</style>

<div class="wrap tsa-job-detail">
    <h1 class="wp-heading-inline">
        <a href="<?php echo admin_url('admin.php?page=travelseo-autopublisher-jobs'); ?>" style="text-decoration:none;">← Back</a>
        &nbsp;|&nbsp; Job #<?php echo esc_html($job->id); ?>
    </h1>
    <hr class="wp-header-end">

    <!-- Status Bar -->
    <div class="tsa-status-bar">
        <div style="display:flex;align-items:center;gap:15px;">
            <span class="tsa-status-badge">
                <span class="dashicons dashicons-<?php echo esc_attr($status_info['icon']); ?>"></span>
                <?php echo esc_html($status_info['label']); ?>
            </span>
            <?php if ($word_count > 0) : ?>
                <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;background:#dbeafe;color:#1e40af;border-radius:4px;font-size:12px;font-weight:500;">
                    <span class="dashicons dashicons-editor-paragraph" style="font-size:14px;width:14px;height:14px;"></span>
                    <?php echo number_format($word_count); ?> kata &middot; <?php echo $reading_time; ?> menit baca
                </span>
            <?php endif; ?>
            <span style="color:#6b7280;font-size:13px;">
                <?php echo esc_html(human_time_diff(strtotime($job->created_at))); ?> ago
            </span>
        </div>
        <div class="tsa-action-buttons">
            <?php if ($job->status === 'ready') : ?>
                <a href="<?php echo admin_url('admin.php?page=travelseo-autopublisher-jobs&action=push&job_id=' . $job->id . '&_wpnonce=' . wp_create_nonce('tsa_push_job') . '&publish_mode=draft'); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-edit"></span> Save as Draft
                </a>
                <a href="<?php echo admin_url('admin.php?page=travelseo-autopublisher-jobs&action=push&job_id=' . $job->id . '&_wpnonce=' . wp_create_nonce('tsa_push_job') . '&publish_mode=publish'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-yes"></span> Publish Now
                </a>
            <?php endif; ?>
            <?php if ($job->status === 'pushed' && $job->post_id) : ?>
                <a href="<?php echo get_edit_post_link($job->post_id); ?>" class="button button-primary" target="_blank">
                    <span class="dashicons dashicons-edit"></span> Edit in WordPress
                </a>
                <a href="<?php echo get_permalink($job->post_id); ?>" class="button button-secondary" target="_blank">
                    <span class="dashicons dashicons-external"></span> View Post
                </a>
            <?php endif; ?>
            <?php if ($job->status === 'failed') : ?>
                <a href="<?php echo admin_url('admin.php?page=travelseo-autopublisher-jobs&action=retry&job_id=' . $job->id . '&_wpnonce=' . wp_create_nonce('tsa_retry_job')); ?>" class="button button-primary">
                    <span class="dashicons dashicons-update"></span> Retry
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hyperion 7-Stage Progress -->
    <div class="tsa-hyperion-progress">
        <div class="tsa-hyperion-title">Project Hyperion Pipeline &mdash; <?php echo $stages_completed; ?>/7 Stages Complete</div>
        <div class="tsa-hyperion-stages">
            <?php
            $stage_labels = array(
                'oracle'      => 'Oracle',
                'architect'   => 'Architect',
                'council'     => 'Council',
                'synthesizer' => 'Synthesizer',
                'stylist'     => 'Stylist',
                'editor'      => 'Editor',
                'connector'   => 'Connector',
            );
            $current_stage = $hyperion['current_stage'] ?? 'oracle';
            $stage_idx = 0;
            foreach ($stage_labels as $skey => $slabel) :
                $stage_idx++;
                $cls = '';
                if ($stage_idx <= $stages_completed) $cls = 'done';
                elseif ($skey === $current_stage && $job->status !== 'ready' && $job->status !== 'pushed') $cls = 'active';
                if ($job->status === 'failed' && $stage_idx === $stages_completed + 1) $cls = 'error';
            ?>
                <div class="tsa-hyperion-stage <?php echo $cls; ?>">
                    <div class="tsa-hyperion-dot">
                        <?php if ($cls === 'done') : ?>
                            <span class="dashicons dashicons-yes" style="font-size:16px;width:16px;height:16px;"></span>
                        <?php elseif ($cls === 'error') : ?>
                            <span class="dashicons dashicons-no" style="font-size:16px;width:16px;height:16px;"></span>
                        <?php else : ?>
                            <?php echo $stage_idx; ?>
                        <?php endif; ?>
                    </div>
                    <div class="tsa-hyperion-label"><?php echo $slabel; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Content Tabs -->
    <div class="tsa-tabs">
        <nav class="tsa-tab-nav">
            <a href="#preview" class="tsa-tab-link active">Preview</a>
            <a href="#metadata" class="tsa-tab-link">Metadata</a>
            <a href="#research" class="tsa-tab-link">Research</a>
            <a href="#images" class="tsa-tab-link">Images</a>
            <a href="#scores" class="tsa-tab-link">Scores</a>
            <a href="#log" class="tsa-tab-link">Log</a>
        </nav>

        <!-- Preview Tab -->
        <div id="preview" class="tsa-tab-content active">
            <?php if (!empty($content_html)) : ?>
                <article class="tsa-article-preview">
                    <header class="tsa-article-header">
                        <h1 class="tsa-article-title"><?php echo esc_html($draft_pack['title'] ?? $job->title_input); ?></h1>
                        <div class="tsa-article-meta">
                            <?php if (!empty($category)) : ?>
                                <span class="tsa-article-meta-item"><span class="dashicons dashicons-category" style="font-size:14px;width:14px;height:14px;"></span> <?php echo esc_html($category); ?></span>
                            <?php endif; ?>
                            <?php if ($word_count > 0) : ?>
                                <span class="tsa-article-meta-item"><span class="dashicons dashicons-editor-paragraph" style="font-size:14px;width:14px;height:14px;"></span> <?php echo number_format($word_count); ?> kata</span>
                            <?php endif; ?>
                            <span class="tsa-article-meta-item"><span class="dashicons dashicons-clock" style="font-size:14px;width:14px;height:14px;"></span> <?php echo $reading_time; ?> menit baca</span>
                        </div>
                        <?php if (!empty($meta_desc)) : ?>
                            <p class="tsa-article-excerpt"><?php echo esc_html($meta_desc); ?></p>
                        <?php endif; ?>
                    </header>
                    <div class="tsa-article-body">
                        <?php echo wp_kses_post($content_html); ?>
                    </div>
                    <?php if (!empty($tags)) : ?>
                        <div style="margin-top:25px;padding-top:18px;border-top:1px solid #e5e7eb;">
                            <strong style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:13px;">Tags:</strong>
                            <?php foreach ($tags as $tag) : ?>
                                <span style="display:inline-block;background:#e5e7eb;padding:3px 10px;border-radius:4px;margin:3px;font-size:12px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
                                    <?php echo esc_html($tag); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php else : ?>
                <div class="tsa-empty-state">
                    <span class="dashicons dashicons-edit"></span>
                    <p>Konten sedang diproses. Status: <strong><?php echo esc_html($status_info['label']); ?></strong></p>
                    <?php if ($stages_completed > 0) : ?>
                        <p style="font-size:13px;"><?php echo $stages_completed; ?>/7 tahap selesai</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Metadata Tab -->
        <div id="metadata" class="tsa-tab-content">
            <table class="widefat fixed striped">
                <tbody>
                    <tr><th style="width:200px;">Title</th><td><strong><?php echo esc_html($draft_pack['title'] ?? $job->title_input); ?></strong></td></tr>
                    <tr><th>Slug</th><td><code><?php echo esc_html(sanitize_title($job->title_input)); ?></code></td></tr>
                    <tr><th>Meta Description</th><td><?php echo esc_html($meta_desc); ?> <?php if (!empty($meta_desc)) : ?><span style="color:#6b7280;font-size:12px;">(<?php echo strlen($meta_desc); ?> chars)</span><?php endif; ?></td></tr>
                    <tr><th>Word Count</th><td><?php echo number_format($word_count); ?> kata</td></tr>
                    <tr><th>Reading Time</th><td><?php echo $reading_time; ?> menit</td></tr>
                    <tr><th>Category</th><td><?php echo esc_html($category ?: '-'); ?></td></tr>
                    <tr><th>Tags</th><td><?php echo esc_html(implode(', ', $tags)); ?></td></tr>
                    <tr><th>Pipeline</th><td>Project Hyperion (<?php echo $stages_completed; ?>/7 stages)</td></tr>
                    <tr><th>SEO Score</th><td><?php echo ($seo_score['overall'] ?? '-'); ?>/100</td></tr>
                    <tr><th>Readability</th><td><?php echo ($readability['score'] ?? '-'); ?>/100</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Research Tab -->
        <div id="research" class="tsa-tab-content">
            <?php if (!empty($hyperion['oracle'])) : ?>
                <h3>Knowledge Graph (Oracle)</h3>
                <?php
                $kg = $hyperion['oracle']['knowledge_graph'] ?? array();
                if (!empty($kg)) :
                ?>
                    <table class="widefat fixed striped" style="margin-bottom:20px;">
                        <tbody>
                            <tr><th style="width:200px;">Type</th><td><?php echo esc_html($kg['type'] ?? '-'); ?></td></tr>
                            <?php if (!empty($kg['entities'])) : ?>
                                <tr><th>Entities</th><td>
                                    <?php foreach ($kg['entities'] as $ek => $ev) : ?>
                                        <strong><?php echo esc_html($ek); ?>:</strong> <?php echo esc_html(is_array($ev) ? implode(', ', $ev) : $ev); ?><br>
                                    <?php endforeach; ?>
                                </td></tr>
                            <?php endif; ?>
                            <?php if (!empty($kg['facts'])) : ?>
                                <tr><th>Facts</th><td>
                                    <ul style="margin:0;padding-left:20px;">
                                        <?php foreach ((array)$kg['facts'] as $fact) : ?>
                                            <li><?php echo esc_html(is_array($fact) ? json_encode($fact) : $fact); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if (!empty($hyperion['oracle']['sources'])) : ?>
                    <h3>Sources (<?php echo count($hyperion['oracle']['sources']); ?>)</h3>
                    <ul>
                        <?php foreach ($hyperion['oracle']['sources'] as $src) : ?>
                            <li><a href="<?php echo esc_url($src['url'] ?? '#'); ?>" target="_blank"><?php echo esc_html($src['title'] ?? $src['url'] ?? 'Source'); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php else : ?>
                <div class="tsa-empty-state">
                    <span class="dashicons dashicons-search"></span>
                    <p>Research data belum tersedia.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Images Tab -->
        <div id="images" class="tsa-tab-content">
            <?php if (!empty($images)) : ?>
                <h3>Image Suggestions (<?php echo count($images); ?>)</h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:15px;">
                    <?php foreach ($images as $img) : ?>
                        <div style="background:#f9fafb;padding:15px;border-radius:8px;border:1px solid #e5e7eb;">
                            <div style="font-weight:600;margin-bottom:8px;"><?php echo esc_html(ucfirst(str_replace('_', ' ', $img['position'] ?? 'Image'))); ?></div>
                            <div style="color:#6b7280;font-size:13px;margin-bottom:5px;"><?php echo esc_html($img['description'] ?? ''); ?></div>
                            <code style="font-size:12px;background:#e5e7eb;padding:2px 6px;border-radius:3px;"><?php echo esc_html($img['keyword'] ?? ''); ?></code>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="tsa-empty-state">
                    <span class="dashicons dashicons-format-image"></span>
                    <p>Image suggestions belum tersedia.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Scores Tab -->
        <div id="scores" class="tsa-tab-content">
            <div class="tsa-score-grid">
                <div class="tsa-score-card">
                    <div class="tsa-score-val <?php echo ($seo_score['overall'] ?? 0) >= 70 ? 'good' : (($seo_score['overall'] ?? 0) >= 50 ? 'warn' : 'bad'); ?>">
                        <?php echo ($seo_score['overall'] ?? 0); ?>
                    </div>
                    <div class="tsa-score-lbl">SEO Score</div>
                </div>
                <div class="tsa-score-card">
                    <div class="tsa-score-val <?php echo ($readability['score'] ?? 0) >= 70 ? 'good' : (($readability['score'] ?? 0) >= 50 ? 'warn' : 'bad'); ?>">
                        <?php echo ($readability['score'] ?? 0); ?>
                    </div>
                    <div class="tsa-score-lbl">Readability</div>
                </div>
                <div class="tsa-score-card">
                    <div class="tsa-score-val good"><?php echo number_format($word_count); ?></div>
                    <div class="tsa-score-lbl">Word Count</div>
                </div>
                <?php if (!empty($formatting_stats)) : ?>
                    <div class="tsa-score-card">
                        <div class="tsa-score-val good"><?php echo ($formatting_stats['bold'] ?? 0); ?></div>
                        <div class="tsa-score-lbl">Bold Elements</div>
                    </div>
                    <div class="tsa-score-card">
                        <div class="tsa-score-val good"><?php echo ($formatting_stats['tables'] ?? 0); ?></div>
                        <div class="tsa-score-lbl">Tables</div>
                    </div>
                    <div class="tsa-score-card">
                        <div class="tsa-score-val good"><?php echo ($formatting_stats['lists'] ?? 0); ?></div>
                        <div class="tsa-score-lbl">Lists</div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($seo_score['details'])) : ?>
                <h3>SEO Detail Scores</h3>
                <table class="widefat fixed striped">
                    <thead><tr><th>Metric</th><th>Score</th></tr></thead>
                    <tbody>
                        <?php foreach ($seo_score['details'] as $metric => $score) : ?>
                            <tr>
                                <td><?php echo esc_html(ucwords(str_replace('_', ' ', $metric))); ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div style="flex:1;background:#e5e7eb;border-radius:4px;height:8px;max-width:200px;">
                                            <div style="width:<?php echo min(100, $score); ?>%;background:<?php echo $score >= 70 ? '#10b981' : ($score >= 50 ? '#f59e0b' : '#ef4444'); ?>;height:100%;border-radius:4px;"></div>
                                        </div>
                                        <span style="font-weight:600;font-size:13px;"><?php echo $score; ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Log Tab -->
        <div id="log" class="tsa-tab-content">
            <?php if (!empty($job->log)) : ?>
                <pre style="background:#1f2937;color:#e5e7eb;padding:20px;border-radius:8px;overflow:auto;max-height:500px;font-size:13px;line-height:1.6;"><?php echo esc_html($job->log); ?></pre>
            <?php else : ?>
                <div class="tsa-empty-state">
                    <span class="dashicons dashicons-text-page"></span>
                    <p>Log belum tersedia.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabLinks = document.querySelectorAll('.tsa-tab-link');
    const tabContents = document.querySelectorAll('.tsa-tab-content');
    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            tabLinks.forEach(l => l.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            document.getElementById(targetId).classList.add('active');
        });
    });
});
</script>
