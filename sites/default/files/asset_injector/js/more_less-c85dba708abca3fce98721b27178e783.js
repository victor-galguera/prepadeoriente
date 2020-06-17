/*jQuery(document).ready(function() {
    // Configure/customize these variables.
    var showChar = 300;  // How many characters are shown by default
    var ellipsestext = "...";
    var moretext = "más >";
    var lesstext = "menos";
    

    jQuery('.field--name-field-descripcion-contenido').each(function() {
        var content = jQuery(this).html();
 
        //console.log(content.length);
        
        if(content.length > showChar) {
          
 
            var c = content.substr(0, showChar);
            var h = content.substr(showChar, content.length - showChar);
            //console.log(h);
 
            var htmlvar = c + '<span class="moreellipses">' + ellipsestext+ '&nbsp;</span><span class="morecontent"><span>' + h + '</span>&nbsp;&nbsp;<a href="" class="morelink">' + moretext + '</a></span>';
 			      
 			      console.log(htmlvar);
 			      jQuery(this).html("");
 			      
            //jQuery(this).html(html);
        }
 
    });
 
    jQuery(".morelink").click(function(){
        if(jQuery(this).hasClass("less")) {
            jQuery(this).removeClass("less");
            jQuery(this).html(moretext);
        } else {
            jQuery(this).addClass("less");
            jQuery(this).html(lesstext);
        }
        jQuery(this).parent().prev().toggle();
        jQuery(this).prev().toggle();
        return false;
    });
});*/