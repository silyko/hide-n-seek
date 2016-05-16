<?php
session_start();
$_SESSION["is_hiding"] = false;
$is_hider = "false";
if (isset($_GET["hider"])){
    $secret_file = ".pwhash";
    if (!(isset($_GET["pw"]) && password_verify($_GET["pw"], file_get_contents($secret_file)))){
        die("Unathorized hider");
    }
    $is_hider = "true";
    $_SESSION["is_hiding"] = true;
}
?>
<!DOCTYPE html>
<html>
  <head>
     <meta name="author" content="Simon Kokkendorff" >
     <meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />
     <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
     <title>Fangeleg</title>
     <link rel="stylesheet" href="./jq/jquery.mobile-1.4.5.min.css" />
     <script src="./jq/jquery-1.12.3.min.js"></script>
     <script src="./jq/jquery.mobile-1.4.5.min.js"></script>
     <link rel="stylesheet" href="./openlayers/ol.css" type="text/css">
     <link rel="stylesheet" href="style.css" type="text/css">
     <script src="./openlayers/ol.js"></script>
   </head>
  <body>
    <div id="map" class="map"></div>
    <div id="various" data-role="controlgroup" data-type="vertical" data-theme="b">
        <button id="zoomtome">Zoom til mig</button>
        <button id="otherbutton">Zoom til gemmer</button>
    </div>
    <div id="message">Fangeleg</div>
    <script>
      var isHider = <?php echo($is_hider);?>;
      var view = new ol.View({
        /* center on Enghave Parken */
        center: [1396325, 7492270],
        zoom: 18
      });
      var myLastPos = [0.0, 0.0];
      var nPosts = 0;
      var osm = new ol.layer.Tile({
            source: new ol.source.OSM({url: "https://{a-c}.tile.openstreetmap.org/{z}/{x}/{y}.png"})
      });
      
      var map = new ol.Map({
        layers: [osm],
        target: 'map',
        controls: ol.control.defaults({attributionOptions:({collapsible: true})}),
        view: view
      });
      if (isHider){
          trackingOptions = {enableHighAccuracy: true,
                             maximumAge: 3000};
      }
      else{
          trackingOptions = {enableHighAccuracy: true,
                             maximumAge: 9000};
      }
      var geolocation = new ol.Geolocation({
        projection: view.getProjection(),
        tracking: true,
        trackingOptions: trackingOptions
      });

     
      $('#zoomtome').click(function(){
          var coordinates = geolocation.getPosition();
          if (coordinates){
              map.getView().setCenter(coordinates);
          }
          else{
              alert("Har ikke fundet dig endnu - vent på position!");
          }
      });
      
      if (isHider){
          $('#otherbutton').click(function(){
              var coordinates = geolocation.getPosition();
              if (coordinates){
                  sendPosition(coordinates, true);
              }
          });
      }
      else{
          $('#otherbutton').click(function(){
              if (hiderPositionFeature){
                  var geom = hiderPositionFeature.getGeometry();
                  if (geom){
                    map.getView().setCenter(geom.getCoordinates());
                  }
                  else{
                    alert("Ingen der skjuler sig endnu?");
                  }
              }
            });
      }
      
      // handle geolocation error.
      geolocation.on('error', function(error) {
         alert(error.message);
      });

      var myAccuracyFeature = new ol.Feature();
      geolocation.on('change:accuracyGeometry', function() {
        myAccuracyFeature.setGeometry(geolocation.getAccuracyGeometry());
      });

      var myPositionFeature = new ol.Feature();
      myPositionFeature.setStyle(new ol.style.Style({
        image: new ol.style.Circle({
          radius: 6,
          fill: new ol.style.Fill({
            color: '#3399CC'
          }),
          stroke: new ol.style.Stroke({
            color: '#fff',
            width: 2
          })
        })
      }));
      var features = [myPositionFeature, myAccuracyFeature];
      
      var hiderPositionFeature = null;
      /* special case for seekers */
      if (!isHider){
          hiderPositionFeature = new ol.Feature();
          hiderPositionFeature.setStyle(new ol.style.Style({
            image: new ol.style.Circle({
              radius: 8,
              fill: new ol.style.Fill({
                color: 'red'
              }),
              stroke: new ol.style.Stroke({
                color: '#fff',
                width: 2
              })
            })
          }));
          features.push(hiderPositionFeature);
      }
      

      geolocation.on('change:position', function() {
          var coordinates = geolocation.getPosition();
          myPositionFeature.setGeometry(coordinates ? new ol.geom.Point(coordinates) : null);
          if (coordinates && isHider){
              sendPosition(coordinates, false);
          }
          else if (coordinates){
              updateDistance();
          }
              
        });

      new ol.layer.Vector({
        map: map,
        source: new ol.source.Vector({
          features: features
        })
      });
      
      function updateDistance(){
          /* update the distance field */
          var myCoords = geolocation.getPosition();
          if ((!myCoords) || (!hiderPositionFeature))
               return;
          var hiderGeom = hiderPositionFeature.getGeometry();
          if (!hiderGeom)
              return;
          var hiderCoords = hiderGeom.getCoordinates()
          var dist = Math.sqrt(Math.pow(myCoords[0] - hiderCoords[0], 2) + Math.pow(myCoords[1] - hiderCoords[1], 2));
          if (dist < 10){
              $('#message').text("FANGET!!");
          }
          else{
              $('#message').text("Afstand til mål: " + dist.toFixed(2) + " m");
          }
      };
      
      function sendPosition(coords, force){
          var dist = Math.sqrt(Math.pow(coords[0] - myLastPos[0], 2) + Math.pow(coords[1] - myLastPos[1], 2));
          if ((dist > 7.5 && dist > 0.5 * geolocation.getAccuracy()) || dist > 75 || force){
              myLastPos = coords.slice();
              
              $.post("set_pos.php", {"x": coords[0], "y": coords[1]})
              .success(function(){
                  nPosts += 1;
                  $('#message').text("Position opdateret - n: " + nPosts);
              })
              .fail(function(){
                  $('#message').text("Opdatering fejlede.");
              });
                  
          }
          else{
              $('#message').text("Ikke nok bevægelse, dist: " + dist.toFixed(2));
          }
       };
       
      function updateHider(data){
      /* call on ajax sucess */
          var coords = [data.x, data.y];
          hiderPositionFeature.setGeometry(new ol.geom.Point(coords));
          updateDistance();
      };
       
      function getHider(){
      /* perform ajax call */
          if (isHider){
              return;
          }
          $.ajax({url: "get_pos.php", success: updateHider, cache: false, dataType: "json"});
      };
       
      /* for seeker - get updates for hider position */
      if (!isHider){
          $('#message').text("Hvem gemmer sig?");
          window.setInterval(getHider, 7500);
      }
      else{
          $('#message').text("Gem dig!");
          $('#otherbutton').text("Send position");
      }
       
    </script>
  </body>
</html>
