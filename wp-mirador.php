<?php
/*
Plugin Name: WP Mirador 3
Description: Embeds a IIIF Mirador 3 Viewer to Wordpress using the shortcode [mirador manifest='...'].  Optional attribute include: height (including half or third), width, align, canvas (which canvas to display by default, starting from 0), view (gallery or single), and minimal (removes toolbars).
Version: 0.0.1
Text Domain: mirador
*/

class WPMirador
{
    function __construct()
    {
        $this->width = "100%";
        $this->height = "600px";

        add_action('wp_enqueue_scripts', [$this, 'add_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'add_admin_scripts']);
        add_shortcode('mirador', [$this, 'shortcode']);
        add_filter('the_content', [$this, 'add_mirador']);
        add_action('add_meta_boxes', [$this, 'meta_box']);
        add_action('save_post', [$this, 'save_meta_box']);
        add_action('admin_menu', [$this, 'wpmirador_settings_page']);
        add_action('admin_init', [$this, 'wpmirador_save']);
    }


    function add_scripts()
    {
        wp_register_script('mirador', "https://unpkg.com/mirador@latest/dist/mirador.min.js");
        wp_enqueue_script('mirador');
    }

    function add_admin_scripts()
    {
        $data = stripslashes(get_option("collection_manifest"));
        wp_register_script('wp_mirador', plugins_url('js/script.js', __FILE__), ['jquery'], '1.1', true);
        wp_enqueue_script('wp_mirador');    
        wp_add_inline_script('wp_mirador', 'const collection_manifest = "' . $data. '";', $position = 'after');
    }

    function meta_box()
    {
        add_meta_box('mirador', __('Mirador Viewer', 'textdomain'), [$this, 'meta_box_content'], 'post');
        add_meta_box('mirador', __('Mirador Viewer', 'textdomain'), [$this, 'meta_box_content'], 'page');
    }



    function meta_box_content()
    {
        global $post;
        if (!$manifest = get_post_meta($post->ID, '_mirador_manifest', true)) {
            $manifest = "";
        }
        if (!$width = get_post_meta($post->ID, '_mirador_width', true)) {
            $width = "100%";
        }
        if (!$height = get_post_meta($post->ID, '_mirador_height', true)) {
            $height = "600px";
        }
        if (!$canvas = get_post_meta($post->ID, '_mirador_canvas', true)) {
            $canvas = 1;
        }
        if (!$view = get_post_meta($post->ID, '_mirador_view', true)) {
            $view = "single";
        }
        if (!$minimal = get_post_meta($post->ID, '_mirador_minimal', true)) {
            $minimal = "";
        }
        ?>
       <div>
       
	<label for="mirador_manifest">Enter a IIIF manifest URL
       <input name="mirador_manifest" type="text" id="mirador_manifest" placeholder="Manifest URL" class="regular-text" style='width:100%;' value="<?php echo $manifest; ?>"></label>
       
       <?php if($option = get_option("collection_manifest")) {  ?>
       <p><label for="choose_manifest">or choose an existing one: <select id="choose_manifest"></select></label></p>
	<?php } ?>
       
       <p><label for="mirador_width">Width: <input name="mirador_width" type="text" id="mirador_width" placeholder="Width" value="<?php echo $width; ?>" style='width:80px;'></label> <label for="mirador_height" style="margin-left: 2em;">Height: <input name="mirador_height" type="text" id="mirador_height" placeholder="Height" value="<?php echo $height; ?>" style='width:80px;'></label></p>
       <p><label for="mirador_canvas">Page: <input name="mirador_canvas" type="text" id="mirador_canvas" placeholder="Canvas" value="<?php echo $canvas; ?>" class="regular-text" style='width:80px;'></label><label for="mirador_view" style="margin-left: 2em;">Default view: <select name="mirador_view" type="text" id="mirador_view"> 
        <?php if ($view == 'gallery') { ?>
         <option value=''>Single</option>
         <option value='gallery' selected>Gallery</option>
          <?php } else { ?>
         <option value='' selected>Single</option>
         <option value='gallery'>Gallery</option>
         <?php } ?>

        </select>
        <label for="mirador_minimal" style="margin-left: 2em;">Minimal</label> 
        <?php if ($minimal == 1) { ?>
          <input id="mirador_minimal" name="mirador_minimal" type="checkbox" value="1" checked /></label>
          <?php } else { ?>
          <input id="mirador_minimal" name="mirador_minimal" type="checkbox" value="1" /></label>
          <?php } ?>
        </p>
       </div>
       <?php
    }

    function save_meta_box($post_id)
    {
        if (array_key_exists('mirador_manifest', $_POST)) {
            add_option("manifest", $_POST['mirador_manifest']);

            update_post_meta($post_id, '_mirador_manifest', $_POST['mirador_manifest']);
            update_post_meta($post_id, '_mirador_width', $_POST['mirador_width']);
            update_post_meta($post_id, '_mirador_height', $_POST['mirador_height']);
            update_post_meta($post_id, '_mirador_canvas', $_POST['mirador_canvas']);
            update_post_meta($post_id, '_mirador_view', $_POST['mirador_view']);
            update_post_meta($post_id, '_mirador_minimal', $_POST['mirador_minimal']);
        }
    }

    /***
     * Construct condig json based on the type of manifest
     ***********************************************/

    function getBaseConfig($divId)
    {
        $manifestobj = json_decode(file_get_contents($this->manifest));

        if (isset($manifestobj->{'@type'}) && $manifestobj->{'@type'} == "sc:Manifest") {
            $this->type = "manifest";
        } elseif (isset($manifestobj->type) && $manifestobj->type == "Manifest") {
            $this->type = "manifest";
            if (is_object($manifestobj->label)) {
                $objIterator = new ArrayIterator($manifestobj->label);
                $label = $objIterator->current()[0];            
                
            } elseif (is_array($manifestobj->label)) {
                $label = $manifestobj->label[0];
            } else {
                $this->label = $manifestobj->label;
            }
        } elseif (isset($manifestobj->{'@type'}) && $manifestobj->{'@type'} == "sc:Collection") {
            $this->type = "collection";
        } elseif (isset($manifestobj->type) && $manifestobj->type == "Collection") {
            $this->type = "collection";
        }

        $this->config = new StdClass();
        $this->config->window = array("allowFullscreen"=>true);
        $this->config->label = "Label";
        $this->config->id = $divId;

        switch ($this->type) {
            case 'collection':
                $collection = new StdClass();
                $collection->manifestId = "mirador";
                $config->windows = [$collection];

                break;
            case 'catalog':
                $this->config->catalog = [];

                $manifests = [];
                $windows = [];
                foreach ($this->manifestobj->manifests as $i => $m) {
                    $o = new StdClass();
                    $o->manifestId = $m->{'@id'};
                    if ($i < 2) {
                        $windows[] = $o;
                    }
                    $manifests[] = $o;
                }
                $this->config->windows = $windows;
                $this->config->catalog = $manifests;

                break;
            case 'manifest':
                $m = new StdClass();
                if (isset($this->view)) {
                    $m->view = $this->view;
                }
                $m->manifestId = $this->manifest;
                if (isset($this->canvas)) {
                    $m->canvasIndex = $this->canvas;
                }
                $this->config->windows = [$m];
                break;
        }

        if ($this->type == 'manifest') {

            if (isset($this->canvas)) {
                $this->config->windows[0]->canvasIndex = $this->canvas + 1;
            }
            if (isset($this->view)) {
                $this->config->windows[0]->view = $this->view;
            }
            if (isset($this->minimal)) {
                $this->config->workspace = (object) ["showZoomControls" => true];
                $this->config->workspaceControlPanel = (object) ["enabled" => false];
            }
        }
    }

    /****
     * the [manifest] shortcode
     **************************************/
    function shortcode($atts)
    {
        if (isset($atts['manifest'])) {
            $this->manifest = $atts['manifest'];

            if (isset($atts['width'])) {
                $this->width = $atts['width'];
            }
            if (isset($atts['height'])) {
                $this->height = $atts['height'];
            }
            if (isset($atts['canvas'])) {
                $this->canvas = $atts['canvas'];
            }
            if (isset($atts['page'])) {
                $this->canvas = $atts['page'] - 1;
            }            
            if (isset($atts['view'])) {
                $this->view = $atts['view'];
            }
            if (isset($atts['minimal'])) {
                $this->minimal = $atts['minimal'];
            }

            $rand = str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
            $this->getBaseConfig("miradorviewer{$rand}");

            $viewer = $this->display("miradorviewer{$rand}");
            return $viewer;
        }
    }

    /***
     * Insert mirador if page meta variable exist
     ***********************************************/
    function add_mirador($content)
    {
        global $post;

        if ($manifest = get_post_meta($post->ID, '_mirador_manifest', true)) {
            $this->manifest = $manifest;

            if ($width = get_post_meta($post->ID, '_mirador_width', true)) {
                $this->width = $width;
            }
            if ($height = get_post_meta($post->ID, '_mirador_height', true)) {
                $this->height = $height;
            }
            if ($canvas = get_post_meta($post->ID, '_mirador_canvas', true)) {
                $this->canvas = $canvas - 1;
            }
            if ($view = get_post_meta($post->ID, '_mirador_view', true)) {
                $this->view = $view;
            }
            if ($minimal = get_post_meta($post->ID, '_mirador_minimal', true)) {
                $this->minimal = $minimal;
            }

            $rand = str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);

            $this->getBaseConfig("miradorviewer{$rand}");

            $viewer = $this->display("miradorviewer{$rand}");
            return $viewer . $content;
        } else {
            return $content;
        }
    }

    /***
     * Insert the code for the viewer
     ***********************************************/
    function display($divId)
    {
        $configjson = json_encode($this->config, JSON_PRETTY_PRINT);

        $output = "    <div id='mirador-container' style='width:{$this->width};height:{$this->height};position:relative;overflow-y:hidden;overflow-y:hidden;'>";
        $output .= "    <div id='{$divId}'></div>";
        $output .= "    </div>";
        $output .= "    <script type='text/javascript'>";
        $output .= "     var miradorInstance = Mirador.viewer({$configjson});";
        $output .= "    </script>";

        return $output;
    }
    
    
    /****************** SETTINGS PAGE *****************/
    
    function wpmirador_settings_page()
    {
        add_options_page('Mirador', 'Mirador', 'manage_options', 'mirador', [$this, 'wpmirador_settings_page_html']);
    }

    function wpmirador_settings_page_html()
    {
        if (!($option = get_option("collection_manifest"))) {
            $option = "";
        } ?>
    <h2>IIIF</h2>
    <form action="options-general.php?page=mirador" method="post">
        <!--<p><textarea name="collection_manifest" style="width:80%;height:600px;"><?php echo stripslashes($option); ?></textarea></p>-->
        <p><label for="collection_manifest">Collection manifest</label> <input type="text" id="collection_manifest" name="collection_manifest" style="width:60%;" value="<?php echo stripslashes($option); ?>"/></p>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save'); ?>" />
    </form>
    <?php
    }

    function wpmirador_save()
    {
        if (isset($_POST['collection_manifest'])) {
            if (!($option = get_option("collection_manifest"))) {
                add_option("collection_manifest", "this is the data");
            } else {
                update_option("collection_manifest", $_POST['collection_manifest']);
            }
        }
    }
    
}

new WPMirador();

