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
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css" rel="stylesheet" />

<!--  omnivalt_parcel_terminal_carrier [begin] -->
<script type="text/javascript">
    var omnivalt_parcel_terminal_carrier_id = {$omnivalt_parcel_terminal_carrier_id};
    var omnivalt_parcel_terminal_error = '{l s='Please select parcel terminal' mod='omnivaltshipping'}';
    var omnivaltdelivery_controller = '{$link->getModuleLink('omnivaltshipping', 'ajax') nofilter}';
</script>
<!--  omnivalt_parcel_terminal_carrier [end] -->
{addJsDef omnivaltdelivery_controller=$link->getModuleLink('omnivaltshipping', 'ajax')}

{if isset($omniva_api_key) and $omniva_api_key}

<script>
    var omnivaSearch = "{l s='Įveskite adresą paieškos laukelyje, norint surasti paštomatus'}";
    {literal}
        var modal = document.getElementById('omnivaLtModal');
        var btn = document.getElementById("show-omniva-map");
        window.document.onclick = function(event) {
            if (event.target == modal || event.target.id == 'omnivaLtModal' || event.target.id == 'terminalsModal') {
                document.getElementById('omnivaLtModal').style.display = "none";
            } else if(event.target.id == 'show-omniva-map') {
                document.getElementById('omnivaLtModal').style.display = "block";
                /* document.querySelector('.found_terminals').innerHTML = omnivaSearch; */
            }
        }
    {/literal}
</script>
<div id="omnivaLtModal" class="modal">
  <div class="omniva-modal-content">
    <div class="omniva-modal-header">
      <span class="close" id="terminalsModal">&times;</span>
      <h5 style="display: inline">{l s='Omniva paštomatai'}</h5>
      <!--        <h5 style="display: inline; padding: 10px;display: inline-block;">{l s='Omniva paštomatai'}</h5> -->
    </div>
    <div class="omniva-modal-body" style="/*overflow: hidden;*/">
        <div id="map-omniva-terminals">
        </div>
        <div class="omniva-search-bar" >
            <h3 style="margin-top: 0px;">{l s='Paštomatų adresai'}</h3>
            <div id="omniva-search"></div>
            <!--
            <input id="address-omniva" type="textbox" class="omniva-search"  height="48" placeholder="{l s='Surasti pagal adresą'}">
            <div style="width: 98%; display: flex; justify-content: flex-end">
                <input type="button" class="btn-address" value="{l s='Surasti'}" onclick="codeAddress()">
                <input type="button" class="btn-address-gps" onclick="findNearest()" value="{l s='Arčiausiai manęs'}"/>
            </div>
            -->
            <div class="found_terminals scrollbar" id="style-8"></div>
        </div>
    </div>
  </div>
</div>
{/if}
