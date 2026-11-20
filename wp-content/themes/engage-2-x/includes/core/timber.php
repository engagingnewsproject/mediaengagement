<?php
/**
 * Timber initialization and configuration
 */

Timber\Timber::init();

Timber::$dirname = ['templates'];

/**
 * By default, Timber does NOT autoescape values. Want to enable Twig's autoescape?
 * No prob! Just set this value to true
 */
Timber::$autoescape = true;

/**
 * Get width and height for an attachment from WordPress metadata (reliable when file path is missing).
 *
 * @param int $attachment_id WordPress attachment post ID.
 * @return array{width?: int, height?: int} Associative array with 'width' and 'height' keys, or empty if unavailable.
 */
function engage_attachment_image_dimensions(int $attachment_id): array
{
	$meta = wp_get_attachment_metadata($attachment_id);
	if (!is_array($meta) || empty($meta['width']) || empty($meta['height'])) {
		return [];
	}
	return [
		'width'  => (int) $meta['width'],
		'height' => (int) $meta['height'],
	];
}

/**
 * Decode HTML entities for Twig (e.g. &#8217; to a real apostrophe) so autoescape
 * does not turn already-encoded titles into visible &amp;#8217;.
 *
 * @param mixed $value Value from Twig; non-strings are returned unchanged.
 * @return mixed
 */
function engage_decode_entities($value)
{
	if (!is_string($value)) {
		return $value;
	}
	return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

add_filter('timber/twig/functions', function (array $functions): array {
	$functions['attachment_image_dimensions'] = [
		'callable' => 'engage_attachment_image_dimensions',
	];
	return $functions;
});

add_filter('timber/twig/filters', function (array $filters): array {
	$filters['decode_entities'] = [
		'callable' => 'engage_decode_entities',
	];
	return $filters;
});

// Decode entities on titles sitewide so Twig autoescape does not double-encode
// values that WordPress already stored as &#8217; / &amp; etc.
add_filter('the_title', 'engage_decode_entities', 20);
add_filter('document_title_parts', static function (array $parts): array {
	foreach (['title', 'page', 'tagline', 'site'] as $key) {
		if (!empty($parts[$key]) && is_string($parts[$key])) {
			$parts[$key] = engage_decode_entities($parts[$key]);
		}
	}
	return $parts;
}); 