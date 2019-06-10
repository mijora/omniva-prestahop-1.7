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

{if isset($omniva_api_key) and $omniva_api_key != false } 
  <button type="button" id="show-omniva-map" class="btn-marker">
    <!--<i id="show-omniva-map" class="fa fa-map-marker fa-lg" aria-hidden="true"></i>-->
      {l s='Show in the map' mod='omnivaltshipping'}<i id="" class="material-icons">add_location</i>
  </button>
{/if}
</div>
