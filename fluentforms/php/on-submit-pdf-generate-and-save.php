<?php 

add_action('fluentform/submission_inserted', 'generate_fluentpdf_from_template', 10, 3);

function generate_fluentpdf_from_template($entry_id, $form_data, $form)
{
    if ((int) $form->id !== 514) {
        return;
    }

    $log_prefix = 'Fluent PDF entry ' . $entry_id . ': ';

    try {
        if (!function_exists('wpFluent') || !function_exists('wpFluentForm')) {
            throw new \Exception('Fluent Forms functions are not available');
        }

        if (!class_exists('\FluentPdf\Modules\FluentForms\FluentFormsIntegration')) {
            throw new \Exception('Fluent PDF integration class is not available');
        }

        $feed = wpFluent()
            ->table('fluentform_form_meta')
            ->where('form_id', $form->id)
            ->where('meta_key', '_pdf_feeds')
            ->first();

        if (!$feed) {
            throw new \Exception('No PDF feed found for form ' . $form->id);
        }

        $settings = json_decode($feed->value, true);

        if (!is_array($settings)) {
            throw new \Exception('PDF feed settings are invalid for feed ' . $feed->id);
        }

        $settings['id'] = $feed->id;

        $pdfManager   = new \FluentPdf\Modules\FluentForms\FluentFormsIntegration(wpFluentForm());
        $templates    = $pdfManager->getAvailableTemplates($form);
        $template_key = \FluentForm\Framework\Helpers\ArrayHelper::get($settings, 'template_key');

        if (empty($template_key)) {
            throw new \Exception('PDF template key is empty for feed ' . $feed->id);
        }

        if (empty($templates[$template_key]['class'])) {
            throw new \Exception('No template class found for template key "' . $template_key . '"');
        }

        $class = $templates[$template_key]['class'];

        if (!class_exists($class)) {
            throw new \Exception('Template class does not exist: ' . $class);
        }

        $instance = new $class(wpFluentForm());

        $file_name = $settings['name'] . '_' . $entry_id . '_' . $feed->id;
        $file_name = \FluentForm\App\Services\FormBuilder\ShortCodeParser::parse($file_name, $entry_id, $form_data);
        $file_name = sanitize_title($file_name, 'pdf-file', 'display');

        if (is_multisite()) {
            $file_name .= '_' . get_current_blog_id();
        }

        $pdf_file_path = $instance->outputPDF($entry_id, $settings, $file_name, false);

        if (!$pdf_file_path) {
            throw new \Exception('PDF generation returned an empty file path');
        }

        error_log($log_prefix . 'SUCCESS - PDF created at ' . $pdf_file_path);
    } catch (\Throwable $e) {
        error_log($log_prefix . 'ERROR - ' . $e->getMessage());
    }
}
