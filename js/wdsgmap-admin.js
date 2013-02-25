jQuery(document).ready(function($) {

    var G = google.maps;
    var map;
    var marker;
    var defaultloc;
    var lat = $('#wdsgmap_latitude').val();
    var lon = $('#wdsgmap_longitude').val();

    defaultloc = new G.LatLng(37.5, -97.7);

    // Set up our map canvas with default coordinates for the US
    map = new G.Map(document.getElementById('map_canvas'), {
        zoom: 4,
        center: defaultloc,
        mapTypeId: G.MapTypeId.ROADMAP
    });

    // Set a single marker. Don't assign it a position yet.
    marker = new G.Marker({
        map: map,
        draggable: true
    });

    // Set our marker position
    function wds_map_markers_set( lat, lon ) {
        marker.setPosition(new G.LatLng(lat, lon));
        map.setCenter(marker.position);
        map.setZoom(16);
        $("#wdsgmap_latitude").val(lat);
        $("#wdsgmap_longitude").val(lon);
    }


    // Set our marker position if we have one already saved
    $('#map_canvas').ready(function(event){
        if (lat && lon) {
            wds_map_markers_set(lat, lon);
        }
    });

    // Listen for marker movement and update coordinates
    G.event.addListener(marker, 'dragend', function(evt){
        $("#wdsgmap_latitude").val(evt.latLng.lat().toFixed(6));
        $("#wdsgmap_longitude").val(evt.latLng.lng().toFixed(6));
    });

   // Lookup coordinates from Google
   $('#wdsgmap_address_search_submit').click( function( event ) {

        // Stop the default submission from happening
        event.preventDefault();

        // Grab our form value
        var address = $('#wdsgmap_address').val();

        $.ajax({
            type : "post",
            dataType : "json",
            url : wdsgmapAjax.ajaxurl,
            data : { "action": "wds_gmap_address_search", "address": address },
            success : function(response) {
                wds_map_markers_set(response.latitude, response.longitude);
            }
        });
    });

   // Clear our map and reset to original state
   $('#wdsgmap_address_clear').click( function( event ) {

        // Stop the default submission from happening
        event.preventDefault();

        // Clear our form values
        $("#wdsgmap_address").val('');
        $("#wdsgmap_latitude").val('');
        $("#wdsgmap_longitude").val('');

        // Clear our marker and reset our map
        marker.setPosition();
        map.setCenter(defaultloc);
        map.setZoom(4);
    });
});
