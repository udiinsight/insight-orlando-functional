<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
     

/**
 * FAQ Schema Markup from H2 headings in single posts
 * Extracts H2 tags as questions, next content as answers
 */

if (!defined('ABSPATH')) exit;

add_action('wp_head', 'orlando_faq_schema_from_h2', 10);

function orlando_faq_schema_from_h2() {
    if (!is_singular('post')) return;

    $post = get_post();
    if (!$post) return;

    $content = apply_filters('the_content', $post->post_content);

    // Extract all H2 tags
    preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $content, $h2_matches, PREG_OFFSET_CAPTURE);

    if (empty($h2_matches[0])) return;

    $faq_items = [];

    foreach ($h2_matches[0] as $index => $h2_match) {
        $question_raw = $h2_matches[1][$index][0];
        $question     = wp_strip_all_tags($question_raw);
        $question     = html_entity_decode($question, ENT_QUOTES, 'UTF-8');

        // Find content between this H2 and the next H2 (or end of content)
        $start = $h2_match[1] + strlen($h2_match[0]);
        $end   = isset($h2_matches[0][$index + 1])
            ? $h2_matches[0][$index + 1][1]
            : strlen($content);

        $answer_html = substr($content, $start, $end - $start);

        // Strip tags and clean up whitespace
        $answer = wp_strip_all_tags($answer_html);
        $answer = html_entity_decode($answer, ENT_QUOTES, 'UTF-8');
        $answer = preg_replace('/\s+/', ' ', trim($answer));

        // Skip if answer is empty
        if (empty($answer) || empty($question)) continue;

        $faq_items[] = [
            '@type'          => 'Question',
            'name'           => $question,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $answer,
            ],
        ];
    }

    if (empty($faq_items)) return;

    $schema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $faq_items,
    ];

    echo '<script type="application/ld+json">'
        . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        . '</script>' . "\n";
}
    // End Code Snippet Code

}, 10);