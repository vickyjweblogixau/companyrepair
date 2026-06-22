<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($page_title ?? 'Dashboard'); ?> — <?php echo get_bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        #wpadminbar { display: none !important; }
        html { margin-top: 0 !important; }
    </style>
</head>
<body class="ltr light bod-dashboard-page">
