<?php
/**
 * New users widget edit view
 */

echo elgg_view('object/widget/edit/num_display', [
	'entity' => elgg_extract('entity', $vars),
]);
