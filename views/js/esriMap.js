function kukuFunck(){
    alert('ddf');
}
var goToLayer, kukuFunck;
require([
  "esri/Map",
  "esri/views/MapView",
  "esri/Graphic",
  "esri/widgets/Search"
], function(
  Map, MapView, Graphic, Search
) {

  var map = new Map({
    basemap: "streets-navigation-vector"
  });

  var view = new MapView({
    center: [23, 55.4],
    container: "map-omniva-terminals",
    map: map,
    zoom: 6
  });

  /*************************
   * Create a point graphic
   *************************/

  // First create a point geometry (this is the location of the Titanic)
  var point = {
    type: "point", // autocasts as new Point()
    longitude: 23.892429,
    latitude: 54.896870
  };

  // Create a symbol for drawing the point
  var markerSymbol = {
    type: "picture-marker",  // autocasts as new PictureMarkerSymbol()
    url: "https://www.omniva.lt/theme/post24/img/sasi.png",
    width: "24px",
   height: "30px"
  };
  var goToLayer = function(zoomLayer) {console.log('goToLayer');alert('goToLayer');
    if(view && view.graphics){
      view.goTo([23,55]);
    }  
  }
  // Create a graphic and add the geometry and symbol to it
  var pointGraphic = new Graphic({
    geometry: point,
    symbol: markerSymbol,
    popupTemplate: { // autocasts as new PopupTemplate()
      title: "{Name}",
      content: "As of 2010, the population in this area was <b>{POP2010:NumberFormat}</b> " +
            "and the density was <b>{POP10_SQMI:NumberFormat}</b> sq mi. " +
            "As of 2013, the population here was <b>{POP2013:NumberFormat}</b> " +
            "and the density was <b>{POP13_SQMI:NumberFormat}</b> sq mi. <br/> <br/>" +
            "Percent change is {POP2013:populationChange} <Button >Pasirinkti</Button>"
    }
  });

  var measureThisAction = {
    title: "Measure Length",
    id: "measure-this",
    image: "Measure_Distance16.png",
    className: 'select-terminal-btn',
    content: "kuku"
  };

  var popTemp = {
      title: "{Name}",
      content: "NO click As of 2010, the population in this area was <b>{POP2010:NumberFormat}</b> " +
            "and the density was <b>{POP10_SQMI:NumberFormat}</b> sq mi. " +
            "As of 2013, the population here was <b>{POP2013:NumberFormat}</b> " +
            "and the density was <b>{POP13_SQMI:NumberFormat}</b> sq mi. <br/> <br/>" +
            "Percent change is {POP2013:populationChange} <Button onclick='kukuFunck();'>Pasirinkti</Button>",
      actions: [measureThisAction]
    }

  var point2 = new Graphic({
    geometry: {
    type: "point", // autocasts as new Point()
    longitude: 23.992429,
    latitude: 54.996870,
  },
  symbol: markerSymbol,
    popupTemplate: popTemp
  })

  var searchWidget = new Search({
    view: view,
    container: "search"
  });

  function findClosest(latitude, longitude) {
    alert('looking for closest marker'+ latitude+' '+longitude);
  }
  searchWidget.on("select-result", function(event){
console.log("The selected search result: ", event.result.feature.geometry, event.result.feature.geometry.x, event.result.feature.geometry.y, 
  event.result.feature.geometry.z, 
  event.result.feature.geometry.latitude,
  event.result.feature.geometry.longitude);
  findClosest(event.result.feature.geometry.latitude, event.result.feature.geometry.longitude);
});

  view.popup.on("trigger-action", function(evt) {
    if (evt.action.id === "measure-this") {
      alert('selected terminal');
    }
  });

  view.graphics.addMany([pointGraphic, point2]);

}); 