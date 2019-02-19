
function popTemplate(id, name, city, address, comment) {
  return {
  title: name,
  content: "<b>"+city+"</b><br> " +
              "<b>"+address+"</b><br> " +
              comment+"<br>  " +
              "<Button onclick='terminalSelected("+id+");' class='omniva-btn'>"+select_terminal+"</Button>",
  }
}

var base_url = window.location.origin;
var map, geocoder, markerAddress, opp = true;
var image = base_url+'/modules/omnivaltshipping/sasi.png';
var locator_img = base_url+'/modules/omnivaltshipping/locator_img.png';
var view, goToLayer, zoomTo, findNearest;

function toRad(Value) {
return Value * Math.PI / 180;
}

function calcCrow(lat1, lon1, lat2, lon2) {
  var R = 6371;
  var dLat = toRad(lat2-lat1);
  var dLon = toRad(lon2-lon1);
  var lat1 = toRad(lat1);
  var lat2 = toRad(lat2);
  var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
  Math.sin(dLon/2) * Math.sin(dLon/2) * Math.cos(lat1) * Math.cos(lat2); 
  var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); 
  var d = R * c;
  return d;
}

function terminalSelected(terminal) {
  omnivaSelect = document.getElementsByName("omnivalt_parcel_terminal");
  var container = document.querySelector("select[name='omnivalt_parcel_terminal']");
  var matches = document.querySelectorAll(".omnivaOption");
  for (var i = 0; i < matches.length; i++) {
    node = matches[i]
    if ( node.value == terminal) {
      node.selected = 'selected';
    } else {
      node.selected = false;
    }
  }
    
  $('select[name="omnivalt_parcel_terminal"]').show();
  $('select[name="omnivalt_parcel_terminal"]').trigger("change");
  document.getElementById('omnivaLtModal').style.display = "none";
}

function selectToMap(terminal_id) {
  view.when(function(){
    view.graphics.forEach(function(graphic){ 
        var omniva = Object.assign({}, graphic.omniva);
        if(graphic.omniva.id == terminal_id) {
            view.zoom = 13
            view.goTo(graphic);
            var popup = view.popup;
            popup.title =  omniva.name,
            popup.content = "<b>"+omniva.city+"</b><br><b>"+omniva.address+"</b><br><br>"+omniva.comment+"<br>"+
                "<Button onclick='terminalSelected("+omniva.id+");' class='omniva-btn'>"+select_terminal+"</Button>",                    
            popup.location = graphic.geometry;      
            popup.open();    
        }
    }); 
  });
}

function loadError(oError) {
  throw new URIError("The script " + oError.target.src + " didn't load correctly.");
}
function affixScriptToHead(url, onloadFunction) {
    var newScript = document.createElement("script");
    newScript.onerror = loadError;
    if (onloadFunction) { newScript.onload = onloadFunction; }
    document.head.appendChild(newScript);
    newScript.src = url;
  }
  affixScriptToHead("https://js.arcgis.com/4.9/", function () { 

  var element = document.getElementById('omniva-search');
  element.addEventListener('keypress', function(evt) {
    var isEnter = evt.keyCode == 13;
    if (isEnter) {
      evt.preventDefault();
      selection = document.querySelector(".esri-search__suggestions-list > li");
      if (selection)
        selection.click();
    }
  });
require([
  "esri/Map",
  "esri/views/MapView",
  "esri/Graphic",
  "esri/widgets/Search",
  "esri/tasks/Locator"
], function(
  Map, MapView, Graphic, Search, Locator
) {

  var map = new Map({
    basemap: "streets-navigation-vector"
  });

   view = new MapView({
    center: [24.105078, 56.946285],
    container: "map-omniva-terminals",
    map: map,
    zoom: 5
  });

  var markerSymbol = {
    type: "picture-marker",
    url: "/modules/omnivaltshipping/sasi.png",
    width: "24px",
   height: "30px"
  };

    for (i = 0; i < locations.length; i++) {  
        var graphic = new Graphic({
            geometry: {
                type: "point",
                longitude: locations[i][2],
                latitude: locations[i][1],
            },
            omniva: {
                name: locations[i][0],
                city: locations[i][4],
                address: locations[i][5],
                id: locations[i][3],
                comment: locations[i][6]
            },
            symbol: markerSymbol,
                popupTemplate: popTemplate(locations[i][3], locations[i][0], locations[i][4], locations[i][5], locations[i][6])
            })
            view.graphics.add(graphic);
        }

        /* Search widget*/
        searchLoc = new Locator({ url: "https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer" });
        var searchWidget = new Search({
            view: view,
            position: "top-left",
            enableInfoWindow: false,
            popupEnabled: false,
            minSuggestCharacters:4,
            includeDefaultSources:false,
            container: "omniva-search",
        });

        sources = [{
                locator: searchLoc,
                placeholder: text_search_placeholder,
                resultSymbol: {
                    type: "picture-marker",
                    url: locator_img,
                    size: 24,
                    width: 24,
                    height: 24,
                    xoffset: 0,
                    yoffset: 0
                }
            }
        ]

        searchWidget.sources = sources;


        zoomTo = function(graphic, id) {
            terminalDetails(id);
            view.graphics.forEach(function(graphic){ 
                var omniva = Object.assign({}, graphic.omniva);
                if (graphic && graphic.omniva && graphic.omniva.id == id) {
                    view.zoom = 15
                    view.goTo(graphic)
                    var popup = view.popup;
                    popup.title =  omniva.name,
                    popup.content = "<b>"+omniva.city+"</b><br><b>"+omniva.address+"</b><br>"+omniva.comment+"<br>"+
                    "<Button onclick='terminalSelected("+omniva.id+");' class='omniva-btn'>"+select_terminal+"</Button>",                    
                    popup.location = graphic.geometry;      
                    popup.open();    
                }
            });  
        }

        function terminalDetails(id) {
            terminals = document.querySelectorAll(".omniva-details")
            for(i=0; i <terminals.length; i++) {
                terminals[i].style.display = 'none';
            }
            id = 'omn-'+id;
            dispOmniva = document.getElementById(id)
            if(dispOmniva)
                dispOmniva.style.display = 'block';
        }

       findNearest = function() {
            navigator.geolocation.getCurrentPosition(function(loc) {
                findClosest(loc.coords.latitude, loc.coords.longitude)
            })
        }

        function findClosest(lat, lng) {
            view.zoom = 12
            view.center = [lng, lat];
            filteredGRAF = view.graphics.map(function(graphic){
                    var latitude = graphic.geometry.latitude
                    var longitude = graphic.geometry.longitude
                    var distance = calcCrow(lat, lng, latitude, longitude)
                    graphic.geometry.distance =distance.toFixed(2)
                    return graphic
            });

            /* Exception for ie compiler having 2014 and lower versions *//*
            if (filteredGRAF && filteredGRAF._items && filteredGRAF._items.length ) {
                filteredGRAF = filteredGRAF._items;
            }*/

            filteredGRAF.sort(function(a, b) {
                var distOne = a.geometry.distance
                var distTwo = b.geometry.distance
                if (parseFloat(distOne) < parseFloat(distTwo)) {
                    return -1;
                }
                if (parseFloat(distOne) > parseFloat(distTwo)) {
                    return 1;
                }
                return 0;
            })

        if (filteredGRAF.length > 0) {
            filteredGRAF = filteredGRAF.slice(1, 16);
            var count = 15, counter = 0, html = '';

            filteredGRAF.forEach(function(terminal){

                var omniva = terminal.omniva;
                var termGraphic = terminal;
                var destination = [terminal.geometry.longitude, terminal.geometry.latitude]

                var goTo = {
                        target: destination,
                        zoom: 5
                        }

                counter++;
                html += '<li onclick="zoomTo(['+destination+'],'+omniva.id+')" ><div style="widthh:60%;"><a class="omniva-li">'+counter+'. <b>'+omniva.name+'</b></a> <b>'+terminal.geometry.distance+' km.</b>\
                            <div align="left" id="omn-'+omniva.id+'" class="omniva-details" style="display:none;">'+omniva.name+' <br/>\
                            '+omniva.address+'<br/>\
                            <button class="btn-marker" style="font-size:14px; padding:0px 5px;margin-bottom:10px; margin-top:5px;height:25px;" onclick="terminalSelected('+omniva.id+')">'+select_terminal+'</button>\
                            </div>\
                            </div></li>';
            })

            document.querySelector('.found_terminals').innerHTML = '<ol class="omniva-terminals-list" start="1">'+html+'</ol>';
        }
    }

    searchWidget.on("select-result", function(event) {
        latitude = event.result.feature.geometry.latitude;
	    longitude = event.result.feature.geometry.longitude;
	    findClosest(latitude, longitude);
        return true;
    });
});
})