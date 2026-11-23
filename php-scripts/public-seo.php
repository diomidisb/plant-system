<?php
function ps_public_seo(string $path, string $title, string $description, bool $home = false): void
{
    $url = ps_url($path);
    $title = html_entity_decode(ps_translate_text($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $description = html_entity_decode(ps_translate_text($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    ?>
    <title><?= ps_e($title) ?></title>
    <meta name="description" content="<?= ps_e($description) ?>">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <meta name="theme-color" content="#216e4d">
    <link rel="canonical" href="<?= ps_e($url) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Plant System Hellas">
    <meta property="og:title" content="<?= ps_e($title) ?>">
    <meta property="og:description" content="<?= ps_e($description) ?>">
    <meta property="og:url" content="<?= ps_e($url) ?>">
    <meta property="og:locale" content="<?= ps_language() === 'el' ? 'el_GR' : 'en_GB' ?>">
    <meta property="og:image" content="<?= ps_e(ps_url('media/kaliergia.png')) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= ps_e($title) ?>">
    <meta name="twitter:description" content="<?= ps_e($description) ?>">
    <meta name="twitter:image" content="<?= ps_e(ps_url('media/kaliergia.png')) ?>">
    <?php
    if ($home) {
        $data = ['@context' => 'https://schema.org','@type' => 'WebSite','name' => 'Plant System Hellas','alternateName' => ['Plant System','PlantSystem','plantsystemhellas'],'url' => ps_url('/'),'description' => $description,'inLanguage' => ps_language()];
        echo '<script type="application/ld+json">'.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT).'</script>';
    }
}
