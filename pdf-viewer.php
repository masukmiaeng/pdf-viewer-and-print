<?php
/**
 * Plugin Name: PDF Viewer & Print
 * Description: Upload and display PDF files with print functionality and responsive design
 * Version: 1.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PDFViewerPrint {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('pdf_viewer', array($this, 'pdf_viewer_shortcode'));
        add_action('wp_ajax_handle_pdf_upload', array($this, 'handle_pdf_upload'));
        add_action('wp_ajax_nopriv_handle_pdf_upload', array($this, 'handle_pdf_upload'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            'PDF Viewer Settings',
            'PDF Viewer',
            'manage_options',
            'pdf-viewer-settings',
            array($this, 'settings_page')
        );
    }
    
    public function admin_init() {
        register_setting('pdf_viewer_settings', 'pdf_viewer_file_url');
        register_setting('pdf_viewer_settings', 'pdf_viewer_file_id');
        
        wp_enqueue_media();
        wp_enqueue_script('pdf-viewer-admin', plugin_dir_url(__FILE__) . 'admin-script.js', array('jquery'), '1.0', true);
        wp_localize_script('pdf-viewer-admin', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style('pdf-viewer-style', plugin_dir_url(__FILE__) . 'style.css', array(), '1.0');
        wp_enqueue_script('pdf-viewer-script', plugin_dir_url(__FILE__) . 'script.js', array('jquery'), '1.0', true);
    }
    
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>PDF Viewer Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('pdf_viewer_settings');
                do_settings_sections('pdf_viewer_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Upload PDF File</th>
                        <td>
                            <input type="hidden" id="pdf_file_id" name="pdf_viewer_file_id" value="<?php echo esc_attr(get_option('pdf_viewer_file_id')); ?>" />
                            <input type="text" id="pdf_file_url" name="pdf_viewer_file_url" value="<?php echo esc_attr(get_option('pdf_viewer_file_url')); ?>" readonly style="width: 60%;" />
                            <button type="button" id="upload_pdf_button" class="button">Upload PDF</button>
                            <button type="button" id="remove_pdf_button" class="button" style="<?php echo get_option('pdf_viewer_file_url') ? '' : 'display:none;'; ?>">Remove PDF</button>
                            <p class="description">Upload a PDF file to display with the shortcode [pdf_viewer]</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <?php if (get_option('pdf_viewer_file_url')): ?>
            <div style="margin-top: 20px;">
                <h3>Shortcode Usage</h3>
                <p>Use this shortcode to display the PDF: <code>[pdf_viewer]</code></p>
                <p>You can also add custom width: <code>[pdf_viewer width="800px"]</code></p>
            </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var mediaUploader;
            $('#upload_pdf_button').click(function(e) {
                e.preventDefault();
                
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                
                mediaUploader = wp.media.frames.file_frame = wp.media({
                    title: 'Choose PDF File',
                    button: {
                        text: 'Choose PDF'
                    },
                    library: {
                        type: 'application/pdf'
                    },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#pdf_file_url').val(attachment.url);
                    $('#pdf_file_id').val(attachment.id);
                    $('#remove_pdf_button').show();
                });
                
                mediaUploader.open();
            });
            
            $('#remove_pdf_button').click(function(e) {
                e.preventDefault();
                $('#pdf_file_url').val('');
                $('#pdf_file_id').val('');
                $(this).hide();
            });
        });
        </script>
        <?php
    }
    
    public function pdf_viewer_shortcode($atts) {
        $atts = shortcode_atts(array(
            'width' => '100%',
            'height' => '600px'
        ), $atts);
        
        $pdf_url = get_option('pdf_viewer_file_url');
        
        if (!$pdf_url) {
            return '<p style="color: red;">No PDF file uploaded. Please upload a PDF from the admin settings.</p>';
        }
        
        ob_start();
        ?>
        <div class="pdf-viewer-container" style="width: <?php echo esc_attr($atts['width']); ?>;">
            <div class="pdf-controls">
                <button id="print-pdf-btn" class="print-btn">
                    <span class="print-icon">🖨️</span> Print PDF
                </button>
            </div>
            <div class="pdf-embed-container">
                <object data="<?php echo esc_url($pdf_url); ?>#toolbar=0&navpanes=0&scrollbar=0" type="application/pdf" width="100%" height="<?php echo esc_attr($atts['height']); ?>">
                    <embed src="<?php echo esc_url($pdf_url); ?>#toolbar=0&navpanes=0&scrollbar=0" type="application/pdf" width="100%" height="<?php echo esc_attr($atts['height']); ?>">
                        <iframe src="<?php echo esc_url($pdf_url); ?>#toolbar=0&navpanes=0&scrollbar=0" width="100%" height="<?php echo esc_attr($atts['height']); ?>" frameborder="0">
                            <div class="pdf-fallback">
                                <p>Your browser doesn't support PDF viewing. <a href="<?php echo esc_url($pdf_url); ?>" target="_blank">Click here to download the PDF</a></p>
                            </div>
                        </iframe>
                    </embed>
                </object>
            </div>
        </div>
        
        <!-- Print Dialog Modal -->
        <div id="print-dialog-modal" class="print-modal" style="display: none;">
            <div class="print-modal-content">
                <div class="print-modal-header">
                    <h3>Print Options</h3>
                    <button class="close-modal">&times;</button>
                </div>
                <div class="print-modal-body">
                    <div class="print-option-group">
                        <label>Copies:</label>
                        <select id="print-copies">
                            <option value="1" selected>01</option>
                            <option value="2">02</option>
                            <option value="3">03</option>
                            <option value="4">04</option>
                            <option value="5">05</option>
                        </select>
                    </div>
                    
                    <div class="print-option-group">
                        <label>Paper size:</label>
                        <select id="print-paper-size">
                            <option value="letter" selected>Letter</option>
                            <option value="a4">A4</option>
                            <option value="legal">Legal</option>
                            <option value="tabloid">Tabloid</option>
                        </select>
                    </div>
                    
                    <div class="print-option-group">
                        <label>Orientation:</label>
                        <select id="print-orientation">
                            <option value="portrait" selected>Portrait</option>
                            <option value="landscape">Landscape</option>
                        </select>
                    </div>
                    
                    <div class="print-option-group">
                        <label>Color:</label>
                        <select id="print-color">
                            <option value="color" selected>Color</option>
                            <option value="grayscale">Grayscale</option>
                        </select>
                    </div>
                </div>
                <div class="print-modal-footer">
                    <button id="confirm-print" class="btn-print">
                        <span class="print-icon">🖨️</span> Print
                    </button>
                </div>

                <div class="pdf-embed-container">
                <object data="<?php echo esc_url($pdf_url); ?>#toolbar=0&navpanes=0&scrollbar=0" type="application/pdf" width="100%" height="<?php echo esc_attr($atts['height']); ?>">
                    <embed src="<?php echo esc_url($pdf_url); ?>#toolbar=0&navpanes=0&scrollbar=0" type="application/pdf" width="100%" height="<?php echo esc_attr($atts['height']); ?>">
                        <iframe src="<?php echo esc_url($pdf_url); ?>#toolbar=0&navpanes=0&scrollbar=0" width="100%" height="<?php echo esc_attr($atts['height']); ?>" frameborder="0">
                            <div class="pdf-fallback">
                                <p>Your browser doesn't support PDF viewing. <a href="<?php echo esc_url($pdf_url); ?>" target="_blank">Click here to download the PDF</a></p>
                            </div>
                        </iframe>
                    </embed>
                </object>
            </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Print button click handler
            $('#print-pdf-btn').click(function() {
                if (window.innerWidth <= 768) {
                    // Mobile: Show custom print dialog
                    $('#print-dialog-modal').fadeIn(300);
                } else {
                    // Desktop: Direct print
                    printPDF();
                }
            });
            
            // Modal close handlers
            $('.close-modal, #cancel-print').click(function() {
                $('#print-dialog-modal').fadeOut(300);
            });
            
            // Confirm print button
            $('#confirm-print').click(function() {
                $('#print-dialog-modal').fadeOut(300);
                printPDF();
            });
            
            // Close modal when clicking outside
            $('#print-dialog-modal').click(function(e) {
                if (e.target === this) {
                    $(this).fadeOut(300);
                }
            });
            
            function printPDF() {
                var printWindow = window.open('<?php echo esc_url($pdf_url); ?>', '_blank');
                printWindow.onload = function() {
                    printWindow.print();
                };
            }
        });
        </script>
        
        <style>
            #toolbar {
                display: none !important;
            }
        .pdf-viewer-container {
            margin: 20px auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
        }
        
        .pdf-controls {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            text-align: right;
        }
        
        .print-btn {
            background: #007cba;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .print-btn:hover {
            background: #005a87;
        }
        
        .print-icon {
            font-size: 16px;
        }
        
        .pdf-embed-container {
            position: relative;
            width: 100%;
        }
        
        .pdf-fallback {
            display: none;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            color: #6c757d;
        }
        
        /* Print Modal Styles */
        .print-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .print-modal-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }
        
        .print-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #5cb3cc;
            color: white;
            border-radius: 12px 12px 0 0;
        }
        
        .print-modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 500;
        }
        
        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.3s ease;
        }
        
        .close-modal:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .print-modal-body {
            padding: 15px;
            display: flex;
            top: 0px;
            background: #5cb3cc;
        }
        
        .print-option-group {
            margin-bottom: 20px;
        }
        
        .print-option-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .print-option-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            background: white;
        }
        
        .btn-cancel, .btn-print {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #545b62;
        }
        
        .btn-print {
            background: #f0ad4e;
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
            width: 86px !important;
            position: absolute;
            top: 10px;
            right: 46px;
        }
        
        .btn-print:hover {
            background: #ec971f;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .pdf-viewer-container {
                margin: 10px;
                border-radius: 6px;
            }
            
            .pdf-controls {
                padding: 10px;
                text-align: center;
            }
            
            .print-btn {
                width: 100%;
                justify-content: center;
                padding: 12px;
                font-size: 16px;
            }
            
            .pdf-embed-container embed {
                height: 400px !important;
            }
            
            .print-modal-content {
                margin: 20px;
                width: calc(100% - 40px);
            }
            
            .print-modal-header {
                padding: 15px;
            }
            
            .print-modal-body {
                padding: 15px;
                position: relative;
            }
            
            .print-modal-footer {
                flex-direction: column;
            }
            
            .btn-cancel, .btn-print {
                width: 100%;
                padding: 12px;
                font-size: 16px;
            }
        }
        
        @media (max-width: 480px) {
            .pdf-embed-container embed {
                height: 300px !important;
            }
        }
        
        /* Handle PDF loading errors */
        @media screen {
            embed[type="application/pdf"] {
                width: 100%;
                height: 600px;
            }
        }
        
        /* Fallback for browsers that don't support PDF embed */
        @supports not (display: flex) {
            .pdf-fallback {
                display: block !important;
            }
            .pdf-embed-container embed {
                display: none;
            }
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
}

// Initialize the plugin
new PDFViewerPrint();

// Add activation hook to create necessary options
register_activation_hook(__FILE__, function() {
    add_option('pdf_viewer_file_url', '');
    add_option('pdf_viewer_file_id', '');
});

// Add deactivation hook to clean up options
register_deactivation_hook(__FILE__, function() {
    delete_option('pdf_viewer_file_url');
    delete_option('pdf_viewer_file_id');
});
?>