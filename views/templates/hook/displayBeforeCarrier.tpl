{*
* 2007-2014 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
*         DISCLAIMER   *
* *************************************** */
/* Do not edit or add to this file if you wish to upgrade Prestashop to newer
* versions in the future.
* ****************************************************
*}

<div id="omnivalt_parcel_terminal_carrier_details" style="display: none; margin-top: 10px;">
    <select class="select2" name="omnivalt_parcel_terminal" style = "width:100%;">{$parcel_terminals nofilter}</select>

    <style>
        {literal}
            #omnivalt_parcel_terminal_carrier_details{ margin-bottom: 5px }
        {/literal}
    </style>
{if isset($omniva_api_key) and $omniva_api_key != false }
  <script type="text/javascript">
    const DEBUG = true;
    var locations = {$terminals_list|@json_encode nofilter}
    if (typeof(DEBUG) !== 'undefined')
      console.log('TYPE OF UNPARSED', typeof(locations), locations);
    
    var select_terminal = "{l s='Pasirinkti terminalą'}";
    var text_search_placeholder = "įveskite adresą";
  </script>
  <button type="button" id="show-omniva-map" class="btn btn-basic btn-sm omniva-btn">
    <!--<i id="show-omniva-map" class="fa fa-map-marker-alt fa-lg" aria-hidden="true"></i>-->
    <i id="show-omniva-map" class="material-icons">add_location</i>
  </button>
{/if}
</div>