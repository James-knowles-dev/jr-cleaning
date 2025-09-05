<?php
if (have_rows('includes')):
    while (have_rows('includes')):
        the_row();

        $rows = get_row_layout();
        $layout = str_replace('_', '-', $rows);

        // Only proceed if layout is not empty
        if (!empty($layout)) {
            $template_path = 'includes/components/' . $layout . '.php';
            $template_file = locate_template($template_path);
            
            // Only include if the template file exists
            if ($template_file) {
                include($template_file);
            }
        }

    endwhile;
endif;
?>