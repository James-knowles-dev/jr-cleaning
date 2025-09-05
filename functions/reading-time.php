<?php
//
// Add an 'estimated read time' to posts that use the Flexible Content page builder.
// This won't work for the standard WordPress WYSIWYG - had to be the 'includes' ACF field

function reading_time($the_post_ID){
	$total_word_count = 0; 
	
	$all_fields = get_field('includes', $the_post_ID, false); // Field name of flexible content field
	foreach ($all_fields as $field) { 
		foreach ($field as $key => $value) { 
			if ($key === 'acf_fc_layout') {
				continue;
			}
			$total_word_count = $total_word_count + str_word_count(strip_tags($value));
		}
	}
	$readingtime = ceil($total_word_count / 200);
	if ($readingtime <= 1) { 
		$timer = " min";
	} else {
		$timer = " mins";
	}
	if ($readingtime == 0) {
		$totalreadingtime = "1" . $timer . " read";
	} else {
		$totalreadingtime = $readingtime . $timer . " read";
	}
	return $totalreadingtime;
}

//
// To add reading time to posts, add the following: 
// echo reading_time(get_the_ID());
//