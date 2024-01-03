<?php
/*
Plugin Name: WP Mirador
Description: Adds an embeddable IIIF Mirador Viewer to Wordpress using the shortcode [mirador manifest='...'].  Optional attribute include: height (including half or third), height, align, canvas (the index of the canvas within the manifest to display by default), view (gallery), and minimal (removes toolbars).
Version: 0.0.1
Text Domain: mirador
*/

class WPMirador
{
    function __construct()
    {
        $this->template = dirname( __FILE__ ) . "/template.tpl";
    
        add_action( 'wp_enqueue_scripts', array ( $this, 'add_scripts' ) );
        add_filter('query_vars', array( $this, 'allow_query_vars' ));
        add_action('init', array($this, 'url_rewrites'));
        //add_action('init', array( $this, 'mirador' ));
        //add_action('parse_query', array( $this, 'fullscreen_mirador' ));
        add_shortcode( 'mirador', array( $this, 'shortcode') ); 
    }
   

    function add_scripts() {
	wp_register_script('mirador', "https://unpkg.com/mirador@latest/dist/mirador.min.js");
	wp_enqueue_script ( 'mirador' );		
    }  



    function allow_query_vars($query_vars)
    {
        $query_vars[] = 'iiif';
        $query_vars[] = 'collection';
        $query_vars[] = 'catalog';
        $query_vars[] = 'manifest';
        
        return $query_vars;
    }



    function url_rewrites()
    {
        flush_rewrite_rules(true);
        add_rewrite_rule('iiif/mirador$', 'index.php', 'top');
    } 
    
    /***
    * Parse the manifest and 
    * build the config object
    *******************************************************/
    function parseManifest($manifest) {
      $this->manifest = $manifest;
      $this->manifestobj = json_decode(file_get_contents($manifest));
      $this->type = strtolower(str_replace("sc:","",$this->manifestobj->{'@type'}));
      
      if(isset($atts['catalog'])) { $this->type = "catalog"; } 
      
      $this->config = $this->buildConfig($this->type);
    }
    

    /****
    * the [manifest] shortcode
    **************************************/
    function shortcode($atts) {
	if(isset($atts['manifest'])) {
	
	   // we must have a manifest
	   $this->parseManifest($atts['manifest']);
	
	   if(isset($atts['width'])) { 
	     if($atts['width'] == "half") {  $width = "50%";  }
	     elseif($atts['width'] == "third") {  $width = "33%";  }
	     else {  $width = $atts['width']."px";  }
	     }
	   else { $width = "100%"; }
	   if(isset($atts['height'])) { $height = $atts['height']."px"; } else { $height = "700px"; }
	   
	   if(isset($atts['align'])) { $float = " float:".$atts['align']; } else { $float = ""; }
	   
	   if($this->type == 'manifest') {
	     if(isset($atts['canvas'])) 	{ $this->config->windows[0]->canvasIndex = $atts['canvas']; }
	     if(isset($atts['view'])) 	{ $this->config->windows[0]->view = $atts['view']; }
	     //if(isset($atts['thumbnails'])) 	{ $this->config->windows[0]->thumbnailNavigationPosition = $atts['thumbnails']; }
	     if(isset($atts['minimal'])) 	{ 
		$this->config->workspace = (object) array("showZoomControls"=> true);
		$this->config->workspaceControlPanel = (object) array("enabled"=> false); 
	     }
	     if(isset($atts['from-the-page']) && isset($atts['canvas'])) 	{ 
		$this->config->transcripts = $this->manifestobj->sequences[0]->canvases[$atts['canvas']]->otherContent;
	     }	     
	   }  
	  
	   
	  	$configjson = json_encode($this->config, JSON_PRETTY_PRINT); 
		  
		$output = "    <div id='miradorviewer' style='width:{$width};height:{$height};position:relative;{$float}'></div>";
		$output .= "    <script type='text/javascript'>";
		$output .= "     var miradorInstance = Mirador.viewer({$configjson});";			
		$output .= "    </script>";
  
	   return $output;	   
	}
   
    }
    
    
    
 
    function displayTemplate($config) {
       $template_string = file_get_contents($this->template);
       $template_string = str_replace("[[config]]", json_encode($config),$template_string);
       echo $template_string;
       die();    
    }
    
    
    function buildConfig( $type, $catalog = false ) {
	$config = new StdClass();
	$config->id = "miradorviewer";
	switch($type) {
	 case 'collection':
	 
		$collection = new StdClass();
		$collection->manifestId = "mirador";
		$config->windows = array($collection);

	 break;
	 case 'catalog':
	 
		$config->catalog = array();

		$manifests = array();
		$windows = array();
		foreach($this->manifestobj->manifests as $i=>$m) {
		 $o = new StdClass();
		 $o->manifestId = $m->{'@id'};
		 if($i<2) { 
		   $windows[] = $o;
		  }         
		 $manifests[] = $o;
		}
		$config->windows = $windows;
		$config->catalog = $manifests;

	 break;	 
	 case 'manifest':
		$manifest = new StdClass();
		if(isset($this->view)) { $manifest->view = $this->view; }
		$manifest->manifestId = $this->manifest;
		if(isset($this->canvas)) { $manifest->canvasIndex = $this->canvas; }
		$config->windows = array($manifest);
	 break;
	}
	   
      return $config;
    }
    
    
    function fullscreen_mirador()
    {
        
	if(get_query_var('collection')) { $type = "collection"; }
	elseif(get_query_var('catalog')) { $type = "catalog"; }
	elseif(get_query_var('manifest')) { $type = "manifest"; }
	else { $type = ""; }
	
	$config = $this->buildConfig($type);
	$this->displayTemplate($config);

    }
    
}



/*
	  if(isset($atts['width'])) { $width = $atts['width']."px"; } else { $width = "100%"; }
	  if(isset($atts['height'])) { $height = $atts['height']."px"; } else { $height = "700px"; }
		$output = "    <div id='miradorviewer' style='width:{$width};height:{$height};position:relative;'></div>";
		$output .= "    <script type='text/javascript'>";
		$output .= "     var miradorInstance = Mirador.viewer({";
		$output .= "       id: 'miradorviewer',";
		$output .= "       windows: [{";
		$output .= "         loadedManifest: 'https://iiif.harvardartmuseums.org/manifests/object/299843',";
		$output .= "         canvasIndex: 2,";
		$output .= "       },";
		$output .= "       {";
		$output .= "         loadedManifest: 'https://media.nga.gov/public/manifests/nga_highlights.json',";
		$output .= "         thumbnailNavigationPosition: 'off',";
		$output .= "       }],";
		$output .= "       workspace: {  showZoomControls: true },";
		$output .= "       workspaceControlPanel: { enabled: false }";
		$output .= "     });";
		$output .= "    </script>	";
		  
        return $output;

*/

new WPMirador();
