jQuery('document').ready(function(){
if (omniva_country == "LV"){
     jQuery('a[href^="https://www.omniva.lt/verslo/siuntos_sekimas"]').attr('href','https://www.omniva.lv/privats/sutijuma_atrasanas_vieta?barcode=' + omniva_tracking);
}
if (omniva_country == "EE"){
     jQuery('a[href^="https://www.omniva.lt/verslo/siuntos_sekimas"]').attr('href','https://www.omniva.ee/era/jalgimine?barcode=' + omniva_tracking);
}
});