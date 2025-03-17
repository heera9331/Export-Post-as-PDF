<?php
/**
 * Plugin Name: Export Post as PDF
 * Plugin URI: https://github.com/heera9331/Export-Post-as-PDF
 * Description: Adds a button to export posts as PDF.
 * Version: 1.0
 * Author: Heera Singh Lodhi
 * Text Domain: export-post-as-pdf
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
  exit;
}

define('EXPORT_AS_POST_PDF_PATH', plugin_dir_path(__FILE__));
define('EXPORT_AS_POST_PDF_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class Export_Post_As_PDF
{

  /**
   * Constructor.
   */
  public function __construct()
  {
    add_filter('the_content', [$this, 'add_pdf_export_button']);
    add_action('template_redirect', [$this, 'export_post_as_pdf']);
  }

  /**
   * Append the PDF export button to single post content.
   *
   * @param string $content The post content.
   * @return string Modified post content with export button.
   */
  public function add_pdf_export_button($content)
  {
    if (is_single()) {
      $post_id = get_the_ID();
      // Build the export URL and add a nonce for verification.
      $export_url = wp_nonce_url(
        add_query_arg('export_pdf', $post_id, home_url()),
        'export_pdf_nonce_' . $post_id,
        '_wpnonce'
      );

      $button = sprintf(
        '<p><a href="%s" class="pdf-export-button">%s</a></p>',
        esc_url($export_url),
        esc_html__('Export as PDF', 'export-post-as-pdf')
      );

      return $button . $content;
    }
    return $content;
  }

  /**
   * Generate and output the PDF.
   */
  public function export_post_as_pdf()
  {
    if (isset($_GET['export_pdf'])) {

      $post_id = absint($_GET['export_pdf']);

      // Verify nonce before proceeding.
      if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'export_pdf_nonce_' . $post_id)) {
        wp_die(__('Security check failed. Please try again.', 'export-post-as-pdf'));
      }


      $post = get_post($post_id);

      if ($post) {
        // Initialize Dompdf with remote resources enabled.
        $dompdf = new Dompdf(['isRemoteEnabled' => true]);

        // Use the file system path to load the CSS file.
        $style_path = EXPORT_AS_POST_PDF_PATH . 'assets/css/pdf-style.php';
        $pdf_styles = '';
        if (file_exists($style_path)) {
          $pdf_styles = file_get_contents($style_path);
        }

        $post_url = get_permalink($post_id);

        $author_name = get_the_author_meta('display_name', $post->post_author);
        // Build HTML content.
        $html = '<html><head>' . $pdf_styles . '</head><body>
        <header> <strong>' . strtoupper($author_name) . '</strong> - ' . $post_url . '</header>
        ';

        $html .= '<h1>' . esc_html($post->post_title) . '</h1>';
        $html .= apply_filters('the_content', $post->post_content);
        $html .= '</body></html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Output PDF headers and content.
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="post-' . $post_id . '.pdf"');
        echo $dompdf->output();
        exit;
      }
    }
  }
}

new Export_Post_As_PDF();
