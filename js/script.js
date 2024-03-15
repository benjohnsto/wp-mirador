
jQuery(document).ready(function(){

console.log(collection_manifest);
	
	
		fetch(collection_manifest)
		  .then(response => {
			  if (!response.ok) {
			      throw new Error(response.statusText);
			  }
			  return response.json();
		  })
		  .then(data => {
		  
		     jQuery.each(data.manifests, function(i,v){
		       var url = v['@id'];
		       var label = v.label;
		       jQuery("#choose_manifest").append("<option value='"+url+"'>"+label+"</option>");
		     });
		    
		  })	
		

	jQuery(document).on("change", "#choose_manifest", function(e){
	  jQuery("#mirador_manifest").val(this.value);
	});



});



